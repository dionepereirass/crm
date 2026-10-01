<?php

namespace Tests\Feature;

use App\Enums\DataSubjectRequestStatus;
use App\Enums\DataSubjectRequestType;
use App\Models\AuditLog;
use App\Models\Consent;
use App\Models\DataSubjectRequest;
use App\Models\Event;
use App\Models\Platform;
use App\Models\Player;
use App\Models\User;
use App\Services\Privacy\DataExportService;
use App\Services\Privacy\DeletionPolicyService;
use App\Services\Privacy\PersonalDataAccessService;
use App\Services\Privacy\PlayerAnonymizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class PrivacyDataSubjectRequestTest extends TestCase
{
    use RefreshDatabase;

    protected Platform $platform;
    protected User $adminUser;
    protected string $adminToken;
    protected Player $player;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);

        $this->platform = Platform::where('slug', 'bet-brasil')->firstOrFail();

        $this->adminUser = User::where('email', 'admin@crm.example.com')->firstOrFail();
        $this->adminToken = $this->adminUser->createToken('admin')->plainTextToken;

        $this->player = Player::create([
            'platform_id' => $this->platform->id,
            'external_id' => 'PLY-DSR-001',
            'name' => 'Ana Clara Ribeiro',
            'email' => 'anaclara@exemplo.com.br',
            'phone' => '5531977776666',
            'cpf' => '98765432100',
            'city' => 'Belo Horizonte',
            'state' => 'MG',
            'status' => 'ACTIVE',
        ]);

        Consent::create([
            'platform_id' => $this->platform->id,
            'player_id' => $this->player->id,
            'channel' => 'EMAIL',
            'type' => 'MARKETING_EMAIL',
            'status' => 'GRANTED',
            'is_granted' => true,
        ]);
    }

    public function test_can_create_and_transition_data_subject_request(): void
    {
        // 1. Criação de solicitação
        $createRes = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->withHeader('X-Platform-Id', (string) $this->platform->id)
            ->postJson('/api/v1/privacy/requests', [
                'player_id' => $this->player->id,
                'type' => 'ACCESS',
                'reason' => 'Solicito acesso a todos os meus dados pessoais cadastrados na plataforma',
                'requested_by' => 'Ana Clara via Chat',
            ]);

        $createRes->assertStatus(201);
        $requestId = $createRes->json('data.id');
        $this->assertEquals('OPEN', $createRes->json('data.status'));
        $this->assertNotNull($createRes->json('data.due_at'));

        // 2. Atribuição a operador
        $assignRes = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->withHeader('X-Platform-Id', (string) $this->platform->id)
            ->postJson("/api/v1/privacy/requests/{$requestId}/assign", [
                'user_id' => $this->adminUser->id,
            ]);

        $assignRes->assertStatus(200);
        $this->assertEquals($this->adminUser->id, $assignRes->json('data.assigned_to'));

        // 3. Iniciar processamento
        $processRes = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->withHeader('X-Platform-Id', (string) $this->platform->id)
            ->postJson("/api/v1/privacy/requests/{$requestId}/process");

        $processRes->assertStatus(200);
        $this->assertEquals('IN_PROGRESS', $processRes->json('data.status'));

        // 4. Conclusão da solicitação
        $completeRes = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->withHeader('X-Platform-Id', (string) $this->platform->id)
            ->postJson("/api/v1/privacy/requests/{$requestId}/complete", [
                'resolution' => 'Relatório completo de dados enviado ao e-mail titular com sucesso.',
            ]);

        $completeRes->assertStatus(200);
        $this->assertEquals('COMPLETED', $completeRes->json('data.status'));
        $this->assertNotNull($completeRes->json('data.completed_at'));
    }

    public function test_can_export_player_data_and_omits_credentials_and_secrets(): void
    {
        $exportService = app(DataExportService::class);
        $bundle = $exportService->export($this->player, 'json', $this->adminUser->id);

        $this->assertIsArray($bundle);
        $this->assertArrayHasKey('player', $bundle);
        $this->assertArrayHasKey('consents', $bundle);
        $this->assertArrayHasKey('export_metadata', $bundle);

        // Verifica que dados cadastrais estão presentes
        $this->assertEquals('Ana Clara Ribeiro', $bundle['player']['name']);
        $this->assertEquals('anaclara@exemplo.com.br', $bundle['player']['email']);

        // Verifica que nenhuma credencial ou segredo interno vazou
        $jsonString = json_encode($bundle);
        $this->assertStringNotContainsString('password', $jsonString);
        $this->assertStringNotContainsString('two_factor_secret', $jsonString);
        $this->assertStringNotContainsString('remember_token', $jsonString);
        $this->assertStringNotContainsString('api_key', $jsonString);
    }

    public function test_can_anonymize_player_irreversibly_while_preserving_accounting_records(): void
    {
        $eventType = \App\Models\EventType::firstOrCreate([
            'platform_id' => $this->platform->id,
            'key' => 'DEPOSIT_SUCCESS',
        ], [
            'name' => 'Depósito Aprovado',
        ]);

        // Cria evento financeiro (depósito) para o jogador
        Event::create([
            'platform_id' => $this->platform->id,
            'player_id' => $this->player->id,
            'event_type_id' => $eventType->id,
            'external_event_id' => 'EXT-DEP-001',
            'occurred_at' => now(),
            'payload' => ['amount' => 500.00, 'currency' => 'BRL'],
        ]);

        $anonymizationService = app(PlayerAnonymizationService::class);

        // Sem confirmação deve falhar
        try {
            $anonymizationService->anonymize($this->player, false);
            $this->fail("Deveria ter lançado InvalidArgumentException sem confirmação.");
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('exige confirmação explícita', $e->getMessage());
        }

        // Com confirmação
        $result = $anonymizationService->anonymize($this->player, true, $this->adminUser->id, 'Exercício de direito LGPD');
        $this->assertTrue($result);

        $fresh = $this->player->fresh();
        $this->assertEquals("ANONYMIZED_USER_{$this->player->id}", $fresh->name);
        $this->assertStringStartsWith("anon_{$this->player->id}_", $fresh->email);
        $this->assertEquals('00000000000', $fresh->phone);
        $this->assertNull($fresh->cpf);
        $this->assertNull($fresh->city);
        $this->assertEquals('ANONYMIZED', $fresh->status);

        // Integridade relacional: evento financeiro continua vinculado ao id do jogador!
        $this->assertDatabaseHas('events', [
            'player_id' => $this->player->id,
            'event_type_id' => $eventType->id,
        ]);

        // Consentimentos foram revogados
        $consent = Consent::where('player_id', $this->player->id)->first();
        $this->assertEquals('REVOKED', $consent->status);
        $this->assertFalse($consent->is_granted);
    }

    public function test_deletion_policy_service_converts_deletion_to_anonymization_for_financial_players(): void
    {
        $betEventType = \App\Models\EventType::firstOrCreate([
            'platform_id' => $this->platform->id,
            'key' => 'BET_PLACED',
        ], [
            'name' => 'Aposta Realizada',
        ]);

        // Jogador com histórico de apostas
        Event::create([
            'platform_id' => $this->platform->id,
            'player_id' => $this->player->id,
            'event_type_id' => $betEventType->id,
            'external_event_id' => 'EXT-BET-001',
            'occurred_at' => now(),
            'payload' => ['amount' => 50.00],
        ]);

        $deletionService = app(DeletionPolicyService::class);
        $result = $deletionService->processPlayerDeletion($this->player, true, $this->adminUser->id);

        $this->assertEquals('ANONYMIZED', $result['action_taken']);
        $this->assertStringContainsString('obrigações legais', $result['reason']);
        $this->assertEquals('ANONYMIZED', $this->player->fresh()->status);
    }

    public function test_data_access_audit_logs_fields_without_exposing_sensitive_values(): void
    {
        $accessService = app(PersonalDataAccessService::class);

        $accessService->logAccess(
            $this->platform->id,
            $this->adminUser->id,
            $this->player->id,
            'Player360',
            ['name', 'email', 'cpf', 'financial_balance'],
            'Auditoria de atendimento ao cliente'
        );

        $log = AuditLog::where('action', 'SENSITIVE_DATA_ACCESSED')
            ->where('resource_id', (string) $this->player->id)
            ->firstOrFail();

        $this->assertNotNull($log);
        $this->assertIsArray($log->new_values);
        $this->assertContains('cpf', $log->new_values['accessed_fields']);
        $this->assertContains('email', $log->new_values['accessed_fields']);

        // Garante que o valor real do CPF ou e-mail NÃO está gravado no log
        $json = json_encode($log->new_values);
        $this->assertStringNotContainsString('98765432100', $json);
        $this->assertStringNotContainsString('anaclara@exemplo.com.br', $json);
    }
}
