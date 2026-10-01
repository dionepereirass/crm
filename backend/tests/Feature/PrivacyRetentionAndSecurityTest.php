<?php

namespace Tests\Feature;

use App\Enums\RetentionAction;
use App\Models\Platform;
use App\Models\Player;
use App\Models\RetentionPolicy;
use App\Models\User;
use App\Models\WebhookLog;
use App\Services\Privacy\RetentionService;
use App\Services\Privacy\SensitiveDataSanitizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrivacyRetentionAndSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected Platform $platformA;
    protected Platform $platformB;
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

        $this->platformA = Platform::where('slug', 'bet-brasil')->firstOrFail();
        $this->platformB = Platform::where('slug', 'bet-global')->firstOrFail();

        $this->adminUser = User::where('email', 'admin@crm.example.com')->firstOrFail();
        $this->adminToken = $this->adminUser->createToken('admin')->plainTextToken;

        $this->marketingUser = User::where('email', 'marketing@crm.example.com')->firstOrFail();
        $this->marketingToken = $this->marketingUser->createToken('marketing')->plainTextToken;

        $this->supportUser = User::where('email', 'support@crm.example.com')->firstOrFail();
        $this->supportToken = $this->supportUser->createToken('support')->plainTextToken;

        $this->analystUser = User::where('email', 'analyst@crm.example.com')->firstOrFail();
        $this->analystToken = $this->analystUser->createToken('analyst')->plainTextToken;
    }

    public function test_retention_service_deletes_expired_logs_and_anonymizes_inactive_players(): void
    {
        // 1. Política de exclusão de logs com mais de 30 dias
        RetentionPolicy::create([
            'platform_id' => $this->platformA->id,
            'data_category' => 'LOGS',
            'retention_days' => 30,
            'action' => RetentionAction::DELETE,
            'active' => true,
        ]);

        $oldLog = WebhookLog::create([
            'platform_id' => $this->platformA->id,
            'endpoint' => '/api/v1/webhooks/bet-brasil',
            'headers' => ['content-type' => 'application/json'],
            'payload' => ['test' => 'old_event'],
            'status' => 'PROCESSED',
            'received_at' => now()->subDays(45),
            'created_at' => now()->subDays(45),
        ]);
        \Illuminate\Support\Facades\DB::table('webhook_logs')->where('id', $oldLog->id)->update([
            'created_at' => now()->subDays(45),
            'received_at' => now()->subDays(45),
        ]);

        $recentLog = WebhookLog::create([
            'platform_id' => $this->platformA->id,
            'endpoint' => '/api/v1/webhooks/bet-brasil',
            'headers' => ['content-type' => 'application/json'],
            'payload' => ['test' => 'recent_event'],
            'status' => 'PROCESSED',
            'received_at' => now()->subDays(5),
            'created_at' => now()->subDays(5),
        ]);

        // 2. Política de anonimização de jogadores inativos há mais de 90 dias
        RetentionPolicy::create([
            'platform_id' => $this->platformA->id,
            'data_category' => 'INACTIVE_PLAYERS',
            'retention_days' => 90,
            'action' => RetentionAction::ANONYMIZE,
            'active' => true,
        ]);

        $inactivePlayer = Player::create([
            'platform_id' => $this->platformA->id,
            'external_id' => 'PLY-INACTIVE-99',
            'name' => 'Marcos Inativo',
            'email' => 'marcos.inativo@exemplo.com',
            'phone' => '5531999990000',
            'last_activity_at' => now()->subDays(120),
            'status' => 'ACTIVE',
        ]);

        $activePlayer = Player::create([
            'platform_id' => $this->platformA->id,
            'external_id' => 'PLY-ACTIVE-01',
            'name' => 'Juliana Ativa',
            'email' => 'juliana.ativa@exemplo.com',
            'phone' => '5531999991111',
            'last_activity_at' => now()->subDays(10),
            'status' => 'ACTIVE',
        ]);

        // Executa retenção
        $retentionService = app(RetentionService::class);
        $summary = $retentionService->processPolicies($this->platformA->id);

        $this->assertGreaterThanOrEqual(1, $summary['records_deleted']);
        $this->assertGreaterThanOrEqual(1, $summary['records_anonymized']);

        // Log antigo foi deletado, recente preservado
        $this->assertDatabaseMissing('webhook_logs', ['id' => $oldLog->id]);
        $this->assertDatabaseHas('webhook_logs', ['id' => $recentLog->id]);

        // Jogador inativo foi anonimizado, ativo preservado
        $this->assertEquals('ANONYMIZED', $inactivePlayer->fresh()->status);
        $this->assertEquals('ACTIVE', $activePlayer->fresh()->status);
    }

    public function test_sensitive_data_sanitizer_masks_email_phone_cpf_and_redacts_tokens(): void
    {
        $sanitizer = app(SensitiveDataSanitizer::class);

        $payload = [
            'player_name' => 'Carlos Santana',
            'player_email' => 'carlos.santana@betcrm.com',
            'phone_number' => '5531988887777',
            'cpf_document' => '12345678909',
            'credit_card' => '4111111111111234',
            'api_token' => 'sk_live_secret_1234567890',
            'webhook_secret' => 'whsec_9988776655',
            'authorization_bearer' => 'Bearer eyJhbGciOi...',
            'password_hash' => '$2y$10$abcdefgh...',
            'metadata' => [
                'nested_email' => 'suporte@casa.com',
                'internal_key' => 'secret_internal',
            ],
        ];

        $sanitized = $sanitizer->sanitize($payload);

        // Verifica mascaramentos
        $this->assertEquals('ca***@betcrm.com', $sanitized['player_email']);
        $this->assertEquals('5531*****7777', $sanitized['phone_number']);
        $this->assertEquals('***.456.789-**', $sanitized['cpf_document']);
        $this->assertEquals('su***@casa.com', $sanitized['metadata']['nested_email']);

        // Verifica redação total de segredos
        $this->assertEquals('***REDACTED***', $sanitized['api_token']);
        $this->assertEquals('***REDACTED***', $sanitized['webhook_secret']);
        $this->assertEquals('***REDACTED***', $sanitized['authorization_bearer']);
        $this->assertEquals('***REDACTED***', $sanitized['password_hash']);
        $this->assertEquals('***REDACTED***', $sanitized['metadata']['internal_key']);
    }

    public function test_rbac_restricts_marketing_and_support_from_administrative_privacy_actions(): void
    {
        $player = Player::create([
            'platform_id' => $this->platformA->id,
            'external_id' => 'PLY-RBAC-01',
            'name' => 'Felipe Teste',
            'email' => 'felipe@test.com',
            'status' => 'ACTIVE',
        ]);

        // 1. Marketing tentando anonimizar diretamente -> 403 Forbidden
        $mktAnonymize = $this->withHeader('Authorization', "Bearer {$this->marketingToken}")
            ->withHeader('X-Platform-Id', (string) $this->platformA->id)
            ->postJson("/api/v1/privacy/players/{$player->id}/anonymize", [
                'confirmed' => true,
            ]);
        $mktAnonymize->assertStatus(403);

        // 2. Support tentando gerenciar políticas de retenção -> 403 Forbidden
        $supRetention = $this->withHeader('Authorization', "Bearer {$this->supportToken}")
            ->withHeader('X-Platform-Id', (string) $this->platformA->id)
            ->postJson('/api/v1/privacy/retention', [
                'data_category' => 'LOGS',
                'retention_days' => 15,
                'action' => 'DELETE',
            ]);
        $supRetention->assertStatus(403);

        // 3. Analyst tentando criar solicitação -> 403 Forbidden
        $analystCreateReq = $this->withHeader('Authorization', "Bearer {$this->analystToken}")
            ->withHeader('X-Platform-Id', (string) $this->platformA->id)
            ->postJson('/api/v1/privacy/requests', [
                'player_id' => $player->id,
                'type' => 'ACCESS',
            ]);
        $analystCreateReq->assertStatus(403);
    }

    public function test_platform_isolation_blocks_cross_tenant_access_to_requests_and_exports(): void
    {
        $playerB = Player::create([
            'platform_id' => $this->platformB->id,
            'external_id' => 'PLY-GLOBAL-88',
            'name' => 'Player Global',
            'email' => 'global@bet.com',
            'status' => 'ACTIVE',
        ]);

        // Operador da Plataforma A tentando exportar dados de player da Plataforma B
        $crossExport = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->withHeader('X-Platform-Id', (string) $this->platformA->id)
            ->getJson("/api/v1/privacy/players/{$playerB->id}/export");

        $crossExport->assertStatus(404);

        // Operador da Plataforma A tentando anonimizar player da Plataforma B
        $crossAnonymize = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->withHeader('X-Platform-Id', (string) $this->platformA->id)
            ->postJson("/api/v1/privacy/players/{$playerB->id}/anonymize", [
                'confirmed' => true,
            ]);

        $crossAnonymize->assertStatus(404);
    }
}
