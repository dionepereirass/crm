<?php

namespace Tests\Feature;

use App\DTOs\Providers\MessagePayload;
use App\Jobs\SendMessageJob;
use App\Models\Message;
use App\Models\MessageEvent;
use App\Models\Platform;
use App\Models\Provider;
use App\Models\ProviderCredential;
use App\Models\ProviderLog;
use App\Models\Role;
use App\Models\User;
use App\Services\Messaging\MessageService;
use App\Services\Providers\Contracts\MessageProviderInterface;
use App\Services\Providers\Drivers\BrevoEmailProvider;
use App\Services\Providers\Drivers\FakeEmailProvider;
use App\Services\Providers\Drivers\FakeSmsProvider;
use App\Services\Providers\Drivers\ZenviaSmsProvider;
use App\Services\Providers\MessageProviderResolver;
use App\Services\Providers\ProviderRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\TestCase;

class ProviderAndMessageTest extends TestCase
{
    use RefreshDatabase;

    protected Platform $betBrasil;
    protected Platform $betGlobal;

    protected User $adminUser;
    protected User $marketingUser;
    protected User $supportUser;
    protected User $analystUser;

    protected string $adminToken;
    protected string $marketingToken;
    protected string $supportToken;
    protected string $analystToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);

        $this->betBrasil = Platform::where('slug', 'bet-brasil')->firstOrFail();
        $this->betGlobal = Platform::where('slug', 'bet-global')->firstOrFail();

        $this->adminUser = User::where('email', 'admin@crm.example.com')->firstOrFail();
        $this->adminToken = $this->adminUser->createToken('test_admin')->plainTextToken;

        $this->marketingUser = User::where('email', 'marketing@crm.example.com')->firstOrFail();
        $this->marketingToken = $this->marketingUser->createToken('test_marketing')->plainTextToken;

        $this->supportUser = User::where('email', 'support@crm.example.com')->firstOrFail();
        $this->supportToken = $this->supportUser->createToken('test_support')->plainTextToken;

        $this->analystUser = User::where('email', 'analyst@crm.example.com')->firstOrFail();
        $this->analystToken = $this->analystUser->createToken('test_analyst')->plainTextToken;
    }

    /**
     * 1. Testes do ProviderRegistry
     */
    public function test_provider_registry_registers_and_resolves_drivers(): void
    {
        $this->assertTrue(ProviderRegistry::has('fake_email'));
        $this->assertTrue(ProviderRegistry::has('fake_sms'));
        $this->assertTrue(ProviderRegistry::has('brevo'));
        $this->assertTrue(ProviderRegistry::has('zenvia'));

        $emailDriver = ProviderRegistry::make('fake_email');
        $this->assertInstanceOf(FakeEmailProvider::class, $emailDriver);
        $this->assertEquals('EMAIL', $emailDriver->getChannel());

        $smsDriver = ProviderRegistry::make('fake_sms');
        $this->assertInstanceOf(FakeSmsProvider::class, $smsDriver);
        $this->assertEquals('SMS', $smsDriver->getChannel());

        $this->expectException(InvalidArgumentException::class);
        ProviderRegistry::make('non_existent_driver');
    }

    /**
     * 2. Fake Providers retornam sucesso e logam o disparo sem chamada externa
     */
    public function test_fake_providers_simulate_dispatch_successfully(): void
    {
        $emailDriver = new FakeEmailProvider();
        $payload = new MessagePayload(
            recipient: 'jogador@example.com',
            subject: 'Bem-vindo!',
            html: '<p>Olá!</p>'
        );

        $result = $emailDriver->send($payload);
        $this->assertTrue($result->success);
        $this->assertEquals('SENT', $result->status);
        $this->assertStringStartsWith('fake_email_', $result->providerMessageId);

        $smsDriver = new FakeSmsProvider();
        $smsPayload = new MessagePayload(
            recipient: '5511999999999',
            smsContent: 'Ola jogador!'
        );

        $smsResult = $smsDriver->send($smsPayload);
        $this->assertTrue($smsResult->success);
        $this->assertEquals('SENT', $smsResult->status);
        $this->assertStringStartsWith('fake_sms_', $smsResult->providerMessageId);
    }

    /**
     * 3. Brevo Driver: sucesso e health check
     */
    public function test_brevo_email_provider_success_and_health(): void
    {
        Http::fake([
            'https://api.brevo.com/v3/smtp/email' => Http::response(['messageId' => 'brevo_msg_123'], 201),
            'https://api.brevo.com/v3/account' => Http::response(['email' => 'admin@betcrm.com'], 200),
        ]);

        $brevo = new BrevoEmailProvider(['api_key' => 'xkeysib-mock-123']);
        $payload = new MessagePayload(recipient: 'test@email.com', subject: 'Assunto', html: '<p>Test</p>');
        $result = $brevo->send($payload);

        $this->assertTrue($result->success);
        $this->assertEquals('brevo_msg_123', $result->providerMessageId);

        $health = $brevo->validateConfiguration();
        $this->assertTrue($health->isHealthy());
    }

    public function test_brevo_email_provider_unauthorized_error(): void
    {
        Http::fake([
            'https://api.brevo.com/v3/smtp/email' => Http::response(['message' => 'Key not authorized'], 401),
        ]);

        $brevo = new BrevoEmailProvider(['api_key' => 'xkeysib-mock-123']);
        $payload = new MessagePayload(recipient: 'test@email.com', subject: 'Assunto', html: '<p>Test</p>');
        $failResult = $brevo->send($payload);

        $this->assertFalse($failResult->success);
        $this->assertEquals('UNAUTHORIZED', $failResult->errorCode);
    }

    public function test_brevo_email_provider_rate_limited_error(): void
    {
        Http::fake([
            'https://api.brevo.com/v3/smtp/email' => Http::response(['message' => 'Rate limit exceeded'], 429),
        ]);

        $brevo = new BrevoEmailProvider(['api_key' => 'xkeysib-mock-123']);
        $payload = new MessagePayload(recipient: 'test@email.com', subject: 'Assunto', html: '<p>Test</p>');
        $rateLimitResult = $brevo->send($payload);

        $this->assertFalse($rateLimitResult->success);
        $this->assertEquals('RATE_LIMITED', $rateLimitResult->errorCode);
    }

    public function test_brevo_email_provider_server_error(): void
    {
        Http::fake([
            'https://api.brevo.com/v3/smtp/email' => Http::response(['message' => 'Internal server error'], 500),
        ]);

        $brevo = new BrevoEmailProvider(['api_key' => 'xkeysib-mock-123']);
        $payload = new MessagePayload(recipient: 'test@email.com', subject: 'Assunto', html: '<p>Test</p>');
        $serverErrorResult = $brevo->send($payload);

        $this->assertFalse($serverErrorResult->success);
        $this->assertEquals('SERVER_ERROR', $serverErrorResult->errorCode);
    }

    public function test_brevo_email_provider_connection_timeout(): void
    {
        Http::fake([
            'https://api.brevo.com/v3/smtp/email' => function () {
                throw new ConnectionException('Connection timed out after 10000ms');
            },
        ]);

        $brevo = new BrevoEmailProvider(['api_key' => 'xkeysib-mock-123']);
        $payload = new MessagePayload(recipient: 'test@email.com', subject: 'Assunto', html: '<p>Test</p>');
        $timeoutResult = $brevo->send($payload);

        $this->assertFalse($timeoutResult->success);
        $this->assertEquals('CONNECTION_TIMEOUT', $timeoutResult->errorCode);
    }

    /**
     * 4. Zenvia Driver: sucesso e health check
     */
    public function test_zenvia_sms_provider_success_and_health(): void
    {
        Http::fake([
            'https://api.zenvia.com/v2/channels/sms/messages' => Http::response(['id' => 'zenvia_sms_999'], 200),
            'https://api.zenvia.com/v2/status' => Http::response(['status' => 'UP'], 200),
        ]);

        $zenvia = new ZenviaSmsProvider(['api_token' => 'zenvia-mock-token']);
        $payload = new MessagePayload(recipient: '5531998877661', smsContent: 'Seu código é 1234');
        $result = $zenvia->send($payload);

        $this->assertTrue($result->success);
        $this->assertEquals('zenvia_sms_999', $result->providerMessageId);

        $health = $zenvia->validateConfiguration();
        $this->assertTrue($health->isHealthy());
    }

    public function test_zenvia_sms_provider_unauthorized_error(): void
    {
        Http::fake([
            'https://api.zenvia.com/v2/channels/sms/messages' => Http::response(['message' => 'Invalid token'], 401),
        ]);

        $zenvia = new ZenviaSmsProvider(['api_token' => 'zenvia-mock-token']);
        $payload = new MessagePayload(recipient: '5531998877661', smsContent: 'Seu código é 1234');
        $failResult = $zenvia->send($payload);

        $this->assertFalse($failResult->success);
        $this->assertEquals('UNAUTHORIZED', $failResult->errorCode);
    }

    public function test_zenvia_sms_provider_rate_limited_error(): void
    {
        Http::fake([
            'https://api.zenvia.com/v2/channels/sms/messages' => Http::response(['message' => 'Too many requests'], 429),
        ]);

        $zenvia = new ZenviaSmsProvider(['api_token' => 'zenvia-mock-token']);
        $payload = new MessagePayload(recipient: '5531998877661', smsContent: 'Seu código é 1234');
        $rateLimitResult = $zenvia->send($payload);

        $this->assertFalse($rateLimitResult->success);
        $this->assertEquals('RATE_LIMITED', $rateLimitResult->errorCode);
    }

    public function test_zenvia_sms_provider_connection_timeout(): void
    {
        Http::fake([
            'https://api.zenvia.com/v2/channels/sms/messages' => function () {
                throw new ConnectionException('Zenvia gateway timeout');
            },
        ]);

        $zenvia = new ZenviaSmsProvider(['api_token' => 'zenvia-mock-token']);
        $payload = new MessagePayload(recipient: '5531998877661', smsContent: 'Seu código é 1234');
        $timeoutResult = $zenvia->send($payload);

        $this->assertFalse($timeoutResult->success);
        $this->assertEquals('CONNECTION_TIMEOUT', $timeoutResult->errorCode);
    }

    /**
     * 5. MessageService enfileira mensagem e despacha job para fila
     */
    public function test_message_service_enqueues_message_and_dispatches_job(): void
    {
        Queue::fake();

        $provider = Provider::create([
            'platform_id' => $this->betBrasil->id,
            'name' => 'Fake Email',
            'channel' => 'EMAIL',
            'driver' => 'fake_email',
            'status' => 'ACTIVE',
            'is_default' => true,
        ]);

        /** @var MessageService $messageService */
        $messageService = app(MessageService::class);

        $payload = new MessagePayload(
            recipient: 'player10@betbrasil.com',
            subject: 'Boas-vindas',
            html: '<p>Bem-vindo ao BET CRM!</p>'
        );

        $message = $messageService->send($this->betBrasil->id, $payload);

        $this->assertDatabaseHas('messages', [
            'id' => $message->id,
            'platform_id' => $this->betBrasil->id,
            'recipient' => 'player10@betbrasil.com',
            'status' => 'PENDING',
        ]);

        Queue::assertPushed(SendMessageJob::class, function ($job) use ($message) {
            return $job->messageId === $message->id;
        });
    }

    /**
     * 6. Idempotência estrita: mesma key na mesma plataforma não duplica registro; plataformas distintas podem reusar
     */
    public function test_strict_idempotency_prevents_duplicate_message_creation(): void
    {
        Queue::fake();

        Provider::create([
            'platform_id' => $this->betBrasil->id,
            'name' => 'Fake Email Brasil',
            'channel' => 'EMAIL',
            'driver' => 'fake_email',
            'status' => 'ACTIVE',
            'is_default' => true,
        ]);

        Provider::create([
            'platform_id' => $this->betGlobal->id,
            'name' => 'Fake Email Global',
            'channel' => 'EMAIL',
            'driver' => 'fake_email',
            'status' => 'ACTIVE',
            'is_default' => true,
        ]);

        /** @var MessageService $messageService */
        $messageService = app(MessageService::class);
        $idempotencyKey = 'idemp_key_unique_12345';

        $payload1 = new MessagePayload(
            recipient: 'jogador@email.com',
            subject: 'Promoção',
            html: '<p>Promo</p>',
            idempotencyKey: $idempotencyKey
        );

        $msg1 = $messageService->send($this->betBrasil->id, $payload1);
        $msg2 = $messageService->send($this->betBrasil->id, $payload1);

        $this->assertEquals($msg1->id, $msg2->id, 'Segunda chamada com mesma key deve retornar o mesmo registro.');
        $this->assertEquals(1, Message::where('platform_id', $this->betBrasil->id)->where('idempotency_key', $idempotencyKey)->count());

        // Outra plataforma pode usar a mesma idempotency key sem conflito
        $msgGlobal = $messageService->send($this->betGlobal->id, $payload1);
        $this->assertNotEquals($msg1->id, $msgGlobal->id);
        $this->assertEquals($this->betGlobal->id, $msgGlobal->platform_id);
    }

    /**
     * 7. Execução do SendMessageJob com sucesso altera status para SENT e registra logs
     */
    public function test_send_message_job_executes_successfully(): void
    {
        $provider = Provider::create([
            'platform_id' => $this->betBrasil->id,
            'name' => 'Fake Email Provedor',
            'channel' => 'EMAIL',
            'driver' => 'fake_email',
            'status' => 'ACTIVE',
            'is_default' => true,
        ]);

        $message = Message::create([
            'platform_id' => $this->betBrasil->id,
            'provider_id' => $provider->id,
            'channel' => 'EMAIL',
            'recipient' => 'carlos@email.com',
            'subject' => 'Depósito Confirmado',
            'status' => 'PENDING',
            'idempotency_key' => 'idemp_' . Str::random(10),
            'metadata' => ['html' => '<p>Seu depósito foi aprovado!</p>'],
        ]);

        $job = new SendMessageJob($message->id);
        app()->call([$job, 'handle']);

        $message->refresh();
        $this->assertEquals('SENT', $message->status);
        $this->assertNotNull($message->sent_at);
        $this->assertNotNull($message->provider_message_id);

        $this->assertDatabaseHas('message_events', [
            'message_id' => $message->id,
            'event_type' => 'SENT',
        ]);

        $this->assertDatabaseHas('provider_logs', [
            'provider_id' => $provider->id,
            'status' => 'SUCCESS',
            'action' => 'send',
        ]);
    }

    /**
     * 8. SendMessageJob trata erro não-reintentável marcando FAILED sem retry infinito
     */
    public function test_send_message_job_fails_non_retryable_error_immediately(): void
    {
        Http::fake([
            'https://api.brevo.com/v3/smtp/email' => Http::response(['message' => 'Invalid email address'], 400),
        ]);

        $provider = Provider::create([
            'platform_id' => $this->betBrasil->id,
            'name' => 'Brevo Produção',
            'channel' => 'EMAIL',
            'driver' => 'brevo',
            'status' => 'ACTIVE',
            'is_default' => true,
        ]);

        $cred = new ProviderCredential(['provider_id' => $provider->id]);
        $cred->setCredentials(['api_key' => 'test-key']);
        $cred->save();

        $message = Message::create([
            'platform_id' => $this->betBrasil->id,
            'provider_id' => $provider->id,
            'channel' => 'EMAIL',
            'recipient' => 'destinatario_invalido',
            'subject' => 'Alerta',
            'status' => 'PENDING',
            'idempotency_key' => 'idemp_' . Str::random(10),
            'metadata' => ['html' => '<p>Msg</p>'],
        ]);

        $job = new SendMessageJob($message->id);
        app()->call([$job, 'handle']);

        $message->refresh();
        $this->assertEquals('FAILED', $message->status);
        $this->assertEquals('BAD_REQUEST', $message->error_code);
        $this->assertNotNull($message->failed_at);
    }

    /**
     * 9. Isolamento multi-plataforma: usuário de Bet Brasil não acessa ou modifica provedor de Bet Global
     */
    public function test_multi_platform_isolation_for_providers(): void
    {
        $globalProvider = Provider::create([
            'platform_id' => $this->betGlobal->id,
            'name' => 'Zenvia Global',
            'channel' => 'SMS',
            'driver' => 'zenvia',
            'status' => 'ACTIVE',
        ]);

        // Tentativa de acesso via token de Bet Brasil deve ser negada
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->withHeader('X-Platform-Id', (string) $this->betBrasil->id)
            ->getJson("/api/v1/providers/{$globalProvider->id}");

        $response->assertStatus(404);

        // Tentativa de update
        $updateResponse = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->withHeader('X-Platform-Id', (string) $this->betBrasil->id)
            ->putJson("/api/v1/providers/{$globalProvider->id}", ['name' => 'Hack']);

        $updateResponse->assertStatus(404);
    }

    /**
     * 10. RBAC: usuário sem permissão (ANALYST / SUPPORT) não pode criar provedor ou testar envio
     */
    public function test_rbac_restricts_unauthorized_users(): void
    {
        // Support não pode criar provedor
        $response = $this->withHeader('Authorization', "Bearer {$this->supportToken}")
            ->withHeader('X-Platform-Id', (string) $this->betBrasil->id)
            ->postJson('/api/v1/providers', [
                'name' => 'Tentativa Maliciosa',
                'channel' => 'EMAIL',
                'driver' => 'fake_email',
            ]);

        $response->assertStatus(403);

        // Analyst não pode testar envio
        $testResponse = $this->withHeader('Authorization', "Bearer {$this->analystToken}")
            ->withHeader('X-Platform-Id', (string) $this->betBrasil->id)
            ->postJson('/api/v1/messages/test', [
                'channel' => 'EMAIL',
                'recipient' => 'test@email.com',
                'subject' => 'Teste',
                'content' => 'Conteúdo',
            ]);

        $testResponse->assertStatus(403);
    }

    /**
     * 11. Segurança: credenciais nunca são expostas na API, Logs ou Resources
     */
    public function test_credentials_are_never_exposed(): void
    {
        $secretKey = 'xkeysib-super-secret-production-token-987654';

        $createResponse = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->withHeader('X-Platform-Id', (string) $this->betBrasil->id)
            ->postJson('/api/v1/providers', [
                'name' => 'Brevo Produção Segura',
                'channel' => 'EMAIL',
                'driver' => 'brevo',
                'status' => 'ACTIVE',
                'api_key' => $secretKey,
            ]);

        $createResponse->assertStatus(201);
        $providerId = $createResponse->json('data.id');

        // Confirma que a API key não veio na resposta de criação
        $createResponse->assertJsonMissing(['api_key' => $secretKey]);
        $this->assertTrue($createResponse->json('data.credentials_configured'));

        // Confirma que a API key não aparece no endpoint de detalhes (show)
        $showResponse = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->withHeader('X-Platform-Id', (string) $this->betBrasil->id)
            ->getJson("/api/v1/providers/{$providerId}");

        $showResponse->assertStatus(200)
            ->assertJsonMissing(['api_key' => $secretKey])
            ->assertJsonMissing(['credentials' => $secretKey]);

        // Confirma que a credencial está criptografada no banco e não em texto puro
        $cred = ProviderCredential::where('provider_id', $providerId)->firstOrFail();
        $this->assertNotEquals($secretKey, $cred->encrypted_credentials);
        $this->assertStringNotContainsString($secretKey, $cred->encrypted_credentials);
        $this->assertEquals($secretKey, $cred->getDecryptedCredentials()['api_key']);
    }

    /**
     * 12. Endpoint administrativo de envio de teste síncrono (/api/v1/messages/test)
     */
    public function test_admin_synchronous_test_message_endpoint(): void
    {
        Provider::create([
            'platform_id' => $this->betBrasil->id,
            'name' => 'Fake Email Teste',
            'channel' => 'EMAIL',
            'driver' => 'fake_email',
            'status' => 'ACTIVE',
            'is_default' => true,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->marketingToken}")
            ->withHeader('X-Platform-Id', (string) $this->betBrasil->id)
            ->postJson('/api/v1/messages/test', [
                'channel' => 'EMAIL',
                'recipient' => 'admin@betbrasil.com',
                'subject' => 'Teste Operacional',
                'content' => 'Mensagem de validação de conectividade',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.message.status', 'SENT')
            ->assertJsonPath('data.provider.driver', 'fake_email');

        $this->assertDatabaseHas('messages', [
            'platform_id' => $this->betBrasil->id,
            'recipient' => 'admin@betbrasil.com',
            'status' => 'SENT',
        ]);
    }

    /**
     * 13. Health Check de Provedor (/api/v1/providers/{id}/health-check)
     */
    public function test_provider_health_check_endpoint(): void
    {
        $provider = Provider::create([
            'platform_id' => $this->betBrasil->id,
            'name' => 'Fake SMS Health',
            'channel' => 'SMS',
            'driver' => 'fake_sms',
            'status' => 'ACTIVE',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->withHeader('X-Platform-Id', (string) $this->betBrasil->id)
            ->postJson("/api/v1/providers/{$provider->id}/health-check");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.healthy', true)
            ->assertJsonPath('data.configured', true)
            ->assertJsonPath('data.authenticated', true);

        $this->assertDatabaseHas('provider_logs', [
            'provider_id' => $provider->id,
            'action' => 'health_check',
            'status' => 'SUCCESS',
        ]);
    }

    /**
     * 14. Webhook Ingestion de Provedor (/api/v1/providers/webhooks/{driver})
     */
    public function test_provider_webhook_ingestion_updates_status_and_records_event(): void
    {
        $message = Message::create([
            'platform_id' => $this->betBrasil->id,
            'channel' => 'EMAIL',
            'recipient' => 'jogador@email.com',
            'status' => 'SENT',
            'provider_message_id' => 'brevo_msg_ext_555',
            'idempotency_key' => 'idemp_' . Str::random(10),
        ]);

        $webhookPayload = [
            'event' => 'delivered',
            'messageId' => 'brevo_msg_ext_555',
            'event_id' => 'evt_unique_101',
            'ts' => time(),
        ];

        $response = $this->postJson('/api/v1/providers/webhooks/brevo', $webhookPayload);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('message_events', [
            'message_id' => $message->id,
            'event_type' => 'DELIVERED',
            'provider_event_id' => 'evt_unique_101',
        ]);

        // Repetição do mesmo webhook é idempotente
        $repeatResponse = $this->postJson('/api/v1/providers/webhooks/brevo', $webhookPayload);
        $repeatResponse->assertStatus(200)
            ->assertJsonPath('message', 'Evento já processado anteriormente (idempotente).');
    }
}
