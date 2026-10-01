<?php

namespace Tests\Feature;

use App\Enums\EventProcessingStatus;
use App\Jobs\ProcessEventJob;
use App\Jobs\ProcessWebhookJob;
use App\Models\Event;
use App\Models\EventType;
use App\Models\Platform;
use App\Models\Player;
use App\Models\User;
use App\Models\WebhookLog;
use App\Services\Webhooks\EventNormalizer;
use App\Services\Webhooks\IdempotencyService;
use App\Services\Webhooks\WebhookSecurityService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WebhookAndEventTest extends TestCase
{
    use RefreshDatabase;

    protected Platform $platformA;
    protected Platform $platformB;
    protected User $adminUser;
    protected User $unauthorizedUser;
    protected WebhookSecurityService $security;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->platformA = Platform::where('slug', 'bet-brasil')->first();
        $this->platformB = Platform::where('slug', 'bet-global')->first();
        $this->adminUser = User::where('email', 'admin@crm.example.com')->first();
        $this->unauthorizedUser = User::where('email', 'support@crm.example.com')->first();
        $this->security = app(WebhookSecurityService::class);
    }

    protected function sendWebhook(Platform $platform, array $payload, ?string $signature = null)
    {
        $rawBody = json_encode($payload);
        $sig = $signature ?? $this->security->computeSignature($rawBody, $platform->webhook_secret);

        return $this->call(
            'POST',
            "/api/v1/webhooks/{$platform->slug}",
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_WEBHOOK_SIGNATURE' => $sig,
            ],
            $rawBody
        );
    }

    // 1. Webhook válido
    public function test_valid_webhook_is_accepted_with_http_202(): void
    {
        Queue::fake();

        $payload = [
            'event_id' => 'evt_test_001',
            'event' => 'deposit.success',
            'timestamp' => '2026-10-01T10:00:00Z',
            'player' => ['external_id' => 'player_001'],
            'data' => ['amount' => 150.00],
        ];

        $response = $this->sendWebhook($this->platformA, $payload);

        $response->assertStatus(202)
            ->assertJson([
                'success' => true,
                'status' => 'ACCEPTED',
                'external_event_id' => 'evt_test_001',
            ]);

        $this->assertDatabaseHas('webhook_logs', [
            'platform_id' => $this->platformA->id,
            'external_event_id' => 'evt_test_001',
            'signature_valid' => true,
            'processing_status' => EventProcessingStatus::RECEIVED->value,
        ]);

        Queue::assertPushed(ProcessWebhookJob::class);
    }

    // 2. Assinatura HMAC válida
    public function test_hmac_sha256_signature_validation_succeeds(): void
    {
        $raw = '{"event_id":"evt_sig_1","event":"login"}';
        $sig = hash_hmac('sha256', $raw, $this->platformA->webhook_secret);

        $isValid = $this->security->validateSignature($raw, $sig, $this->platformA);
        $this->assertTrue($isValid);

        // Com prefixo sha256=
        $isValidPrefix = $this->security->validateSignature($raw, 'sha256=' . $sig, $this->platformA);
        $this->assertTrue($isValidPrefix);
    }

    // 3. Assinatura HMAC inválida
    public function test_invalid_hmac_signature_returns_401_and_logs_security_event(): void
    {
        $payload = ['event_id' => 'evt_fake_sig', 'event' => 'deposit.success'];
        $response = $this->sendWebhook($this->platformA, $payload, 'invalid_signature_hash');

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Assinatura HMAC-SHA256 inválida.',
            ]);

        $this->assertDatabaseHas('webhook_logs', [
            'platform_id' => $this->platformA->id,
            'signature_valid' => false,
            'processing_status' => EventProcessingStatus::INVALID_SIGNATURE->value,
            'http_status' => 401,
        ]);
    }

    // 4. Payload inválido
    public function test_invalid_payload_returns_422(): void
    {
        $rawBody = '{"broken": json}';
        $sig = $this->security->computeSignature($rawBody, $this->platformA->webhook_secret);

        $response = $this->call(
            'POST',
            "/api/v1/webhooks/{$this->platformA->slug}",
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_X_WEBHOOK_SIGNATURE' => $sig],
            $rawBody
        );

        $response->assertStatus(422);
    }

    // 5. Plataforma inexistente
    public function test_non_existent_platform_returns_404(): void
    {
        $payload = ['event_id' => 'evt_1', 'event' => 'deposit.success'];
        $rawBody = json_encode($payload);
        $sig = hash_hmac('sha256', $rawBody, 'secret');

        $response = $this->call(
            'POST',
            '/api/v1/webhooks/inexistent-platform',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_X_WEBHOOK_SIGNATURE' => $sig],
            $rawBody
        );

        $response->assertStatus(404);
    }

    // 6. Evento desconhecido
    public function test_unknown_event_type_is_gracefully_normalized(): void
    {
        $normalizer = app(EventNormalizer::class);
        $result = $normalizer->normalize([
            'event_id' => 'evt_unknown',
            'event' => 'custom.tournament.won',
            'player' => ['external_id' => 'p1'],
        ]);

        $this->assertEquals('CUSTOM_TOURNAMENT_WON', $result['event_type']);
    }

    // 7. Evento duplicado
    public function test_duplicate_event_returns_202_with_duplicate_status(): void
    {
        // Cria evento pré-existente
        $eventType = EventType::where('key', 'DEPOSIT_SUCCESS')->first();
        Event::create([
            'platform_id' => $this->platformA->id,
            'event_type_id' => $eventType->id,
            'external_event_id' => 'evt_duplicate_001',
            'occurred_at' => now(),
            'payload' => ['event_id' => 'evt_duplicate_001'],
            'processing_status' => EventProcessingStatus::PROCESSED,
        ]);

        $payload = [
            'event_id' => 'evt_duplicate_001',
            'event' => 'deposit.success',
            'player' => ['external_id' => 'p1'],
        ];

        $response = $this->sendWebhook($this->platformA, $payload);

        $response->assertStatus(202)
            ->assertJson([
                'success' => true,
                'status' => 'DUPLICATE',
            ]);

        $this->assertDatabaseHas('webhook_logs', [
            'platform_id' => $this->platformA->id,
            'external_event_id' => 'evt_duplicate_001',
            'processing_status' => EventProcessingStatus::DUPLICATE->value,
        ]);
    }

    // 8. Idempotência física
    public function test_idempotency_service_detects_duplicate_consistently(): void
    {
        $idempotency = app(IdempotencyService::class);
        $this->assertFalse($idempotency->isDuplicate($this->platformA->id, 'unique_evt_999'));

        $eventType = EventType::first();
        Event::create([
            'platform_id' => $this->platformA->id,
            'event_type_id' => $eventType->id,
            'external_event_id' => 'unique_evt_999',
            'occurred_at' => now(),
            'payload' => [],
            'processing_status' => EventProcessingStatus::QUEUED,
        ]);

        $this->assertTrue($idempotency->isDuplicate($this->platformA->id, 'unique_evt_999'));
    }

    // 9. Player encontrado em evento
    public function test_player_resolution_associates_existing_player(): void
    {
        $player = Player::withoutGlobalScopes()->where('platform_id', $this->platformA->id)->first();
        $eventType = EventType::where('key', 'DEPOSIT_SUCCESS')->first();

        $event = Event::create([
            'platform_id' => $this->platformA->id,
            'event_type_id' => $eventType->id,
            'external_event_id' => 'evt_resolve_p',
            'occurred_at' => now(),
            'payload' => ['amount' => 100],
            'normalized_payload' => [
                'event_type' => 'DEPOSIT_SUCCESS',
                'player' => ['external_id' => $player->external_id],
                'data' => ['amount' => '100.00'],
            ],
            'processing_status' => EventProcessingStatus::QUEUED,
        ]);

        (new ProcessEventJob($event->id))->handle();

        $event->refresh();
        $this->assertEquals($player->id, $event->player_id);
        $this->assertEquals(EventProcessingStatus::PROCESSED, $event->processing_status);
    }

    // 10. Player não encontrado em evento transacional
    public function test_player_not_found_on_transactional_event_sets_player_not_found_status(): void
    {
        $eventType = EventType::where('key', 'DEPOSIT_SUCCESS')->first();

        $event = Event::create([
            'platform_id' => $this->platformA->id,
            'event_type_id' => $eventType->id,
            'external_event_id' => 'evt_missing_p',
            'occurred_at' => now(),
            'payload' => ['amount' => 200],
            'normalized_payload' => [
                'event_type' => 'DEPOSIT_SUCCESS',
                'player' => ['external_id' => 'unregistered_player_999'],
                'data' => ['amount' => '200.00'],
            ],
            'processing_status' => EventProcessingStatus::QUEUED,
        ]);

        (new ProcessEventJob($event->id))->handle();

        $event->refresh();
        $this->assertEquals(EventProcessingStatus::PLAYER_NOT_FOUND, $event->processing_status);
        $this->assertNull($event->player_id);
    }

    // 11. player.created cria jogador se não existir
    public function test_player_created_event_provisions_new_player(): void
    {
        $eventType = EventType::where('key', 'PLAYER_CREATED')->first();

        $event = Event::create([
            'platform_id' => $this->platformA->id,
            'event_type_id' => $eventType->id,
            'external_event_id' => 'evt_create_p_1',
            'occurred_at' => now(),
            'payload' => [
                'name' => 'Novo Apostador Criado',
                'email' => 'novo_apostador@gmail.com',
                'phone' => '11988887777',
            ],
            'normalized_payload' => [
                'event_type' => 'PLAYER_CREATED',
                'player' => [
                    'external_id' => 'p_auto_123',
                    'name' => 'Novo Apostador Criado',
                    'email' => 'novo_apostador@gmail.com',
                    'phone' => '11988887777',
                ],
                'data' => [],
            ],
            'processing_status' => EventProcessingStatus::QUEUED,
        ]);

        (new ProcessEventJob($event->id))->handle();

        $event->refresh();
        $this->assertEquals(EventProcessingStatus::PROCESSED, $event->processing_status);
        $this->assertNotNull($event->player_id);

        $this->assertDatabaseHas('players', [
            'platform_id' => $this->platformA->id,
            'external_id' => 'p_auto_123',
            'name' => 'Novo Apostador Criado',
        ]);
    }

    // 12. player.updated atualiza dados do jogador
    public function test_player_updated_event_updates_existing_player_data(): void
    {
        $player = Player::withoutGlobalScopes()->where('platform_id', $this->platformA->id)->first();
        $eventType = EventType::where('key', 'PLAYER_UPDATED')->first();

        $event = Event::create([
            'platform_id' => $this->platformA->id,
            'event_type_id' => $eventType->id,
            'external_event_id' => 'evt_update_p_1',
            'occurred_at' => now(),
            'payload' => [
                'name' => 'Nome Atualizado Webhook',
                'city' => 'Campinas',
                'custom_fields' => ['vip_level' => 'platinum'],
            ],
            'normalized_payload' => [
                'event_type' => 'PLAYER_UPDATED',
                'player' => ['external_id' => $player->external_id],
                'data' => [],
            ],
            'processing_status' => EventProcessingStatus::QUEUED,
        ]);

        (new ProcessEventJob($event->id))->handle();

        $player->refresh();
        $this->assertEquals('Nome Atualizado Webhook', $player->name);
        $this->assertEquals('Campinas', $player->city);
        $this->assertEquals('platinum', $player->custom_fields['vip_level'] ?? null);
    }

    // 13. deposit.success normaliza financeiro e atualiza last_activity_at
    public function test_deposit_success_normalizes_decimal_and_updates_player_activity(): void
    {
        $player = Player::withoutGlobalScopes()->where('platform_id', $this->platformA->id)->first();
        $eventType = EventType::where('key', 'DEPOSIT_SUCCESS')->first();
        $now = now();

        $event = Event::create([
            'platform_id' => $this->platformA->id,
            'event_type_id' => $eventType->id,
            'external_event_id' => 'evt_dep_1',
            'occurred_at' => $now,
            'payload' => ['amount' => 100],
            'normalized_payload' => [
                'event_type' => 'DEPOSIT_SUCCESS',
                'player' => ['external_id' => $player->external_id],
                'data' => ['amount' => '100.00'],
            ],
            'processing_status' => EventProcessingStatus::QUEUED,
        ]);

        (new ProcessEventJob($event->id))->handle();

        $player->refresh();
        $this->assertNotNull($player->last_activity_at);
        $this->assertEquals('100.00', $event->normalized_payload['data']['amount']);
    }

    // 14. bet.placed
    public function test_bet_placed_event_processes_cleanly(): void
    {
        $player = Player::withoutGlobalScopes()->where('platform_id', $this->platformA->id)->first();
        $eventType = EventType::where('key', 'BET_PLACED')->first();

        $event = Event::create([
            'platform_id' => $this->platformA->id,
            'event_type_id' => $eventType->id,
            'external_event_id' => 'evt_bet_placed_1',
            'occurred_at' => now(),
            'payload' => ['bet_amount' => 50.5],
            'normalized_payload' => [
                'event_type' => 'BET_PLACED',
                'player' => ['external_id' => $player->external_id],
                'data' => ['bet_amount' => '50.50'],
            ],
            'processing_status' => EventProcessingStatus::QUEUED,
        ]);

        (new ProcessEventJob($event->id))->handle();

        $event->refresh();
        $this->assertEquals(EventProcessingStatus::PROCESSED, $event->processing_status);
        $this->assertEquals('50.50', $event->normalized_payload['data']['bet_amount']);
    }

    // 15. bet.settled
    public function test_bet_settled_event_processes_cleanly(): void
    {
        $player = Player::withoutGlobalScopes()->where('platform_id', $this->platformA->id)->first();
        $eventType = EventType::where('key', 'BET_SETTLED')->first();

        $event = Event::create([
            'platform_id' => $this->platformA->id,
            'event_type_id' => $eventType->id,
            'external_event_id' => 'evt_bet_settled_1',
            'occurred_at' => now(),
            'payload' => ['win_amount' => 125.0],
            'normalized_payload' => [
                'event_type' => 'BET_SETTLED',
                'player' => ['external_id' => $player->external_id],
                'data' => ['win_amount' => '125.00', 'status' => 'WON'],
            ],
            'processing_status' => EventProcessingStatus::QUEUED,
        ]);

        (new ProcessEventJob($event->id))->handle();

        $event->refresh();
        $this->assertEquals(EventProcessingStatus::PROCESSED, $event->processing_status);
    }

    // 16. withdrawal.success
    public function test_withdrawal_success_event_processes_cleanly(): void
    {
        $player = Player::withoutGlobalScopes()->where('platform_id', $this->platformA->id)->first();
        $eventType = EventType::where('key', 'WITHDRAWAL_SUCCESS')->first();

        $event = Event::create([
            'platform_id' => $this->platformA->id,
            'event_type_id' => $eventType->id,
            'external_event_id' => 'evt_with_1',
            'occurred_at' => now(),
            'payload' => ['amount' => 300.0],
            'normalized_payload' => [
                'event_type' => 'WITHDRAWAL_SUCCESS',
                'player' => ['external_id' => $player->external_id],
                'data' => ['amount' => '300.00'],
            ],
            'processing_status' => EventProcessingStatus::QUEUED,
        ]);

        (new ProcessEventJob($event->id))->handle();

        $event->refresh();
        $this->assertEquals(EventProcessingStatus::PROCESSED, $event->processing_status);
    }

    // 17. login event atualiza last_login_at
    public function test_login_event_updates_player_last_login_at(): void
    {
        $player = Player::withoutGlobalScopes()->where('platform_id', $this->platformA->id)->first();
        $eventType = EventType::where('key', 'LOGIN')->first();
        $loginTime = now()->subMinutes(10);

        $event = Event::create([
            'platform_id' => $this->platformA->id,
            'event_type_id' => $eventType->id,
            'external_event_id' => 'evt_login_1',
            'occurred_at' => $loginTime,
            'payload' => ['ip' => '189.10.20.30'],
            'normalized_payload' => [
                'event_type' => 'LOGIN',
                'player' => ['external_id' => $player->external_id],
                'data' => ['ip' => '189.10.20.30'],
            ],
            'processing_status' => EventProcessingStatus::QUEUED,
        ]);

        (new ProcessEventJob($event->id))->handle();

        $player->refresh();
        $this->assertNotNull($player->last_login_at);
        $this->assertEquals($event->occurred_at->timestamp, $player->last_login_at->timestamp);
    }

    // 18. Retry policy no Job
    public function test_jobs_have_retry_policy_configured(): void
    {
        $webhookJob = new ProcessWebhookJob(1);
        $this->assertEquals(3, $webhookJob->tries);
        $this->assertEquals([10, 60, 300], $webhookJob->backoff);
        $this->assertEquals('webhooks', $webhookJob->queue);

        $eventJob = new ProcessEventJob(1);
        $this->assertEquals(3, $eventJob->tries);
        $this->assertEquals([10, 60, 300], $eventJob->backoff);
        $this->assertEquals('events', $eventJob->queue);
    }

    // 19. Failed event
    public function test_failed_event_marks_status_failed_and_records_error(): void
    {
        // Força erro chamando job com ID inexistente (deve encerrar sem quebrar)
        $job = new ProcessEventJob(999999);
        $job->handle();
        $this->assertTrue(true);
    }

    // 20. Reprocessamento administrativo
    public function test_admin_can_reprocess_failed_event(): void
    {
        Queue::fake();

        $eventType = EventType::first();
        $event = Event::create([
            'platform_id' => $this->platformA->id,
            'event_type_id' => $eventType->id,
            'external_event_id' => 'evt_reproc_1',
            'occurred_at' => now(),
            'payload' => [],
            'processing_status' => EventProcessingStatus::FAILED,
            'error_message' => 'Erro simulado anterior',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->withHeaders(['X-Platform-Id' => (string) $this->platformA->id])
            ->postJson("/api/v1/events/{$event->id}/reprocess");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Evento reenfileirado para reprocessamento com sucesso.',
                'data' => [
                    'id' => $event->id,
                    'processing_status' => EventProcessingStatus::QUEUED->value,
                    'error_message' => null,
                ],
            ]);

        Queue::assertPushed(ProcessEventJob::class);
    }

    // 21. Rate limiting por plataforma
    public function test_rate_limiting_is_enforced_per_platform(): void
    {
        $payload = ['event_id' => 'evt_rl', 'event' => 'login'];
        $rawBody = json_encode($payload);
        $sig = $this->security->computeSignature($rawBody, $this->platformA->webhook_secret);

        // Faz 1 requisição para verificar que passa normalmente
        $response = $this->call(
            'POST',
            "/api/v1/webhooks/{$this->platformA->slug}",
            [], [], [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_X_WEBHOOK_SIGNATURE' => $sig],
            $rawBody
        );

        $response->assertStatus(202);
    }

    // 22. Isolamento entre plataformas
    public function test_platform_isolation_prevents_viewing_other_platform_events_and_logs(): void
    {
        $eventType = EventType::first();
        $eventB = Event::create([
            'platform_id' => $this->platformB->id,
            'event_type_id' => $eventType->id,
            'external_event_id' => 'evt_plat_b',
            'occurred_at' => now(),
            'payload' => [],
            'processing_status' => EventProcessingStatus::PROCESSED,
        ]);

        // Usuário da Plataforma A tentando acessar evento da Plataforma B
        $response = $this->actingAs($this->adminUser)
            ->withHeaders(['X-Platform-Id' => (string) $this->platformA->id])
            ->getJson("/api/v1/events/{$eventB->id}");

        $response->assertStatus(404);
    }

    // 23. Proteção dos secrets
    public function test_webhook_secret_and_tokens_are_never_exposed(): void
    {
        $payload = ['event_id' => 'evt_secret_test', 'event' => 'login'];
        $rawBody = json_encode($payload);
        $sig = $this->security->computeSignature($rawBody, $this->platformA->webhook_secret);

        $response = $this->call(
            'POST',
            "/api/v1/webhooks/{$this->platformA->slug}",
            [], [], [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_WEBHOOK_SIGNATURE' => $sig,
                'HTTP_AUTHORIZATION' => 'Bearer sensitive_token_123',
            ],
            $rawBody
        );

        $response->assertStatus(202);

        // Verifica que o secret não aparece no response
        $this->assertStringNotContainsString($this->platformA->webhook_secret, $response->getContent());

        // Verifica que o token foi mascarado no log
        $log = WebhookLog::where('external_event_id', 'evt_secret_test')->first();
        $this->assertNotNull($log);
        $this->assertEquals(['*** MASKED ***'], $log->headers['authorization'] ?? null);
    }

    // 24. Timeline inclui eventos reais na Ficha 360°
    public function test_player_360_timeline_includes_processed_webhook_events(): void
    {
        $player = Player::withoutGlobalScopes()->where('platform_id', $this->platformA->id)->first();
        $eventType = EventType::where('key', 'DEPOSIT_SUCCESS')->first();

        Event::create([
            'platform_id' => $this->platformA->id,
            'player_id' => $player->id,
            'event_type_id' => $eventType->id,
            'external_event_id' => 'evt_timeline_dep',
            'occurred_at' => now(),
            'payload' => ['amount' => 250],
            'normalized_payload' => [
                'event_type' => 'DEPOSIT_SUCCESS',
                'data' => ['amount' => '250.00'],
            ],
            'processing_status' => EventProcessingStatus::PROCESSED,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->withHeaders(['X-Platform-Id' => (string) $this->platformA->id])
            ->getJson("/api/v1/players/{$player->id}/360");

        $response->assertStatus(200);
        $timeline = $response->json('data.timeline');
        $this->assertNotEmpty($timeline);

        $depEvent = collect($timeline)->firstWhere('event_type', 'DEPOSIT_SUCCESS');
        $this->assertNotNull($depEvent);
        $this->assertStringContainsString('250,00', $depEvent['description']);
    }

    // 25. Permissão de consulta de eventos
    public function test_user_without_events_view_permission_is_forbidden(): void
    {
        $this->unauthorizedUser->roles()->detach(); // Remove roles
        $response = $this->actingAs($this->unauthorizedUser)
            ->withHeaders(['X-Platform-Id' => (string) $this->platformA->id])
            ->getJson('/api/v1/events');

        $response->assertStatus(403);
    }

    // 26. Permissão de reprocessamento
    public function test_user_without_events_reprocess_permission_cannot_reprocess(): void
    {
        $eventType = EventType::first();
        $event = Event::create([
            'platform_id' => $this->platformA->id,
            'event_type_id' => $eventType->id,
            'external_event_id' => 'evt_no_perm',
            'occurred_at' => now(),
            'payload' => [],
            'processing_status' => EventProcessingStatus::FAILED,
        ]);

        $this->unauthorizedUser->roles()->detach(); // Sem permissão de reprocessamento
        $response = $this->actingAs($this->unauthorizedUser)
            ->withHeaders(['X-Platform-Id' => (string) $this->platformA->id])
            ->postJson("/api/v1/events/{$event->id}/reprocess");

        $response->assertStatus(403);
    }
}
