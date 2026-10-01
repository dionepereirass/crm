<?php

namespace Tests\Feature;

use App\Models\EventType;
use App\Models\Event;
use App\Models\Platform;
use App\Models\Player;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyticsDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected Platform $platformA;
    protected Platform $platformB;
    protected User $adminUser;
    protected User $marketingUser;
    protected User $unauthorizedUser;
    protected string $adminToken;
    protected string $marketingToken;
    protected string $unauthorizedToken;
    protected Player $playerA1;
    protected Player $playerA2;
    protected Player $playerB1;

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

        // Anexa adminUser à plataforma B também para testar isolamento de dados
        $this->adminUser->platforms()->syncWithoutDetaching([$this->platformA->id, $this->platformB->id]);

        // Cria usuário sem permissão de analytics
        $this->unauthorizedUser = User::create([
            'name' => 'Sem Permissao',
            'email' => 'noanalytics@crm.example.com',
            'password' => \Illuminate\Support\Facades\Hash::make('Secret@123456'),
            'status' => 'ACTIVE',
        ]);
        $this->unauthorizedUser->platforms()->syncWithoutDetaching([$this->platformA->id]);
        $this->unauthorizedToken = $this->unauthorizedUser->createToken('unauth')->plainTextToken;

        // Players Platform A
        $this->playerA1 = Player::create([
            'platform_id' => $this->platformA->id,
            'external_id' => 'PLY-A1',
            'name' => 'Carlos Silva',
            'email' => 'carlos@example.com',
            'phone' => '5511999990001',
            'status' => 'ACTIVE',
            'created_at' => Carbon::now()->subDays(5),
            'last_activity_at' => Carbon::now()->subDays(2),
        ]);

        $this->playerA2 = Player::create([
            'platform_id' => $this->platformA->id,
            'external_id' => 'PLY-A2',
            'name' => 'Fernanda Lima',
            'email' => 'fernanda@example.com',
            'phone' => '5511999990002',
            'status' => 'ACTIVE',
            'created_at' => Carbon::now()->subDays(20),
            'last_activity_at' => Carbon::now()->subDays(18),
        ]);

        // Player Platform B (Tenant Isolation)
        $this->playerB1 = Player::create([
            'platform_id' => $this->platformB->id,
            'external_id' => 'PLY-B1',
            'name' => 'John Miller',
            'email' => 'john@global.com',
            'phone' => '14155550001',
            'status' => 'ACTIVE',
            'created_at' => Carbon::now()->subDays(2),
            'last_activity_at' => Carbon::now()->subHours(5),
        ]);

        // Cria eventos de depósito para teste financeiro
        $depositType = EventType::firstOrCreate(
            ['key' => 'DEPOSIT_SUCCESS'],
            ['name' => 'Depósito Concluído', 'category' => 'FINANCIAL', 'active' => true]
        );

        Event::create([
            'platform_id' => $this->platformA->id,
            'player_id' => $this->playerA1->id,
            'event_type_id' => $depositType->id,
            'external_event_id' => 'EXT-EVT-A1',
            'event_name' => 'DEPOSIT_SUCCESS',
            'occurred_at' => Carbon::now()->subDays(3),
            'payload' => ['amount' => 150.00],
            'normalized_payload' => [
                'data' => [
                    'amount' => 150.00,
                    'currency' => 'BRL',
                    'transaction_id' => 'TXN-A1-1',
                ],
            ],
        ]);

        Event::create([
            'platform_id' => $this->platformA->id,
            'player_id' => $this->playerA2->id,
            'event_type_id' => $depositType->id,
            'external_event_id' => 'EXT-EVT-A2',
            'event_name' => 'DEPOSIT_SUCCESS',
            'occurred_at' => Carbon::now()->subDays(1),
            'payload' => ['amount' => 250.00],
            'normalized_payload' => [
                'data' => [
                    'amount' => 250.00,
                    'currency' => 'BRL',
                    'transaction_id' => 'TXN-A2-1',
                ],
            ],
        ]);
    }

    public function test_dashboard_endpoint_returns_consolidated_kpis_and_funnel(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->withHeader('X-Platform-Id', (string) $this->platformA->id)
            ->getJson('/api/v1/analytics/dashboard?period=30d&grouping=day');

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'period' => ['key', 'current', 'prior', 'grouping'],
                'kpis' => [
                    'players',
                    'financial',
                    'betting',
                    'marketing',
                    'automations',
                ],
                'charts' => ['combined_evolution'],
                'marketing_funnel',
                'risk_distribution',
                'churn_metrics',
                'active_alerts',
            ],
        ]);

        // Verifica valores reais de depósitos da plataforma A
        $this->assertEquals(400.0, (float) $response->json('data.kpis.financial.total_deposit_amount.value'));
        $this->assertEquals(2, $response->json('data.kpis.financial.deposit_count.value'));
    }

    public function test_players_analytics_endpoint_returns_evolution_and_risk_distribution(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->marketingToken}")
            ->withHeader('X-Platform-Id', (string) $this->platformA->id)
            ->getJson('/api/v1/analytics/players?period=30d');

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonStructure([
            'data' => [
                'kpis' => ['total_players', 'new_players', 'active_players', 'inactive_players'],
                'risk_distribution' => ['ativo', 'atencao', 'risco', 'inativo'],
                'timeseries',
            ],
        ]);

        $this->assertGreaterThanOrEqual(2, $response->json('data.kpis.total_players.value'));
    }

    public function test_retention_and_cohort_matrix_endpoint(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->marketingToken}")
            ->withHeader('X-Platform-Id', (string) $this->platformA->id)
            ->getJson('/api/v1/analytics/retention?weeks=8');

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonStructure([
            'data' => [
                'cohort',
                'curve',
            ],
        ]);
    }

    public function test_churn_endpoint_with_configurable_threshold(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->marketingToken}")
            ->withHeader('X-Platform-Id', (string) $this->platformA->id)
            ->getJson('/api/v1/analytics/churn?inactivity_days=14');

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.inactivity_threshold_days', 14);
        $response->assertJsonStructure([
            'data' => [
                'inactivity_threshold_days',
                'total_players',
                'active_players',
                'at_risk_players',
                'churned_players',
                'churn_rate',
            ],
        ]);
    }

    public function test_finance_analytics_endpoint(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->withHeader('X-Platform-Id', (string) $this->platformA->id)
            ->getJson('/api/v1/analytics/finance?period=30d');

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.kpis.deposit_count.value', 2);
        $this->assertEquals(400.0, (float) $response->json('data.kpis.total_deposit_amount.value'));
    }

    public function test_betting_analytics_handles_empty_state_cleanly(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->withHeader('X-Platform-Id', (string) $this->platformA->id)
            ->getJson('/api/v1/analytics/betting?period=30d');

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.has_data', false);
        $response->assertJsonPath('data.message', 'Sem dados suficientes de apostas para o período selecionado.');
    }

    public function test_marketing_analytics_and_funnel_endpoint(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->marketingToken}")
            ->withHeader('X-Platform-Id', (string) $this->platformA->id)
            ->getJson('/api/v1/analytics/marketing?period=30d');

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonStructure([
            'data' => [
                'period',
                'kpis',
                'funnel',
                'rankings',
                'channels',
            ],
        ]);
    }

    public function test_templates_analytics_endpoint(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->marketingToken}")
            ->withHeader('X-Platform-Id', (string) $this->platformA->id)
            ->getJson('/api/v1/analytics/templates?period=30d');

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $this->assertIsArray($response->json('data'));
    }

    public function test_providers_analytics_endpoint(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->withHeader('X-Platform-Id', (string) $this->platformA->id)
            ->getJson('/api/v1/analytics/providers?period=30d');

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $this->assertIsArray($response->json('data'));
    }

    public function test_automations_analytics_endpoint(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->marketingToken}")
            ->withHeader('X-Platform-Id', (string) $this->platformA->id)
            ->getJson('/api/v1/analytics/automations?period=30d');

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonStructure([
            'data' => [
                'period',
                'kpis' => [
                    'active_automations',
                    'total_runs',
                    'completed_runs',
                    'failed_runs',
                    'success_rate',
                ],
                'funnel',
            ],
        ]);
    }

    public function test_privacy_analytics_endpoint(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->withHeader('X-Platform-Id', (string) $this->platformA->id)
            ->getJson('/api/v1/analytics/privacy?period=30d');

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonStructure([
            'data' => [
                'period',
                'kpis' => [
                    'active_consents',
                    'revoked_consents',
                    'open_dsr_requests',
                    'completed_dsr_requests',
                    'near_sla_dsr_requests',
                    'anonymized_players',
                    'audit_events_count',
                ],
            ],
        ]);
    }

    public function test_cache_invalidation_endpoint(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->withHeader('X-Platform-Id', (string) $this->platformA->id)
            ->postJson('/api/v1/analytics/cache/invalidate', [
                'metric' => 'dashboard',
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('message', 'Cache de analytics invalidado com sucesso.');
    }

    public function test_tenant_isolation_between_platforms(): void
    {
        // Consulta na plataforma A
        $resA = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->withHeader('X-Platform-Id', (string) $this->platformA->id)
            ->getJson('/api/v1/analytics/finance?period=30d');

        // Consulta na plataforma B (onde não houve depósitos criados)
        $resB = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->withHeader('X-Platform-Id', (string) $this->platformB->id)
            ->getJson('/api/v1/analytics/finance?period=30d');

        $this->assertEquals(400.0, (float) $resA->json('data.kpis.total_deposit_amount.value'));
        $this->assertEquals(0.0, (float) $resB->json('data.kpis.total_deposit_amount.value'));
    }

    public function test_unauthorized_user_is_forbidden_from_viewing_analytics(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->unauthorizedToken}")
            ->withHeader('X-Platform-Id', (string) $this->platformA->id)
            ->getJson('/api/v1/analytics/dashboard');

        $response->assertStatus(403);
    }
}
