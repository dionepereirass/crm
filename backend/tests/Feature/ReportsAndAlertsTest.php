<?php

namespace Tests\Feature;

use App\Models\AlertRule;
use App\Models\OperationalAlert;
use App\Models\Platform;
use App\Models\Player;
use App\Models\ScheduledReport;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ReportsAndAlertsTest extends TestCase
{
    use RefreshDatabase;

    protected Platform $platformA;
    protected Platform $platformB;
    protected User $adminUser;
    protected User $supportUser;
    protected string $adminToken;
    protected string $supportToken;
    protected Player $playerA;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);

        $this->platformA = Platform::where('slug', 'bet-brasil')->firstOrFail();
        $this->platformB = Platform::where('slug', 'bet-global')->firstOrFail();

        $this->adminUser = User::where('email', 'admin@crm.example.com')->firstOrFail();
        $this->adminUser->platforms()->syncWithoutDetaching([$this->platformA->id, $this->platformB->id]);
        $this->adminToken = $this->adminUser->createToken('admin')->plainTextToken;

        $this->supportUser = User::where('email', 'support@crm.example.com')->firstOrFail();
        $this->supportToken = $this->supportUser->createToken('support')->plainTextToken;

        $this->playerA = Player::create([
            'platform_id' => $this->platformA->id,
            'external_id' => 'PLY-REP-A1',
            'name' => 'Marcos Souza',
            'email' => 'marcos@example.com',
            'phone' => '5511988881234',
            'status' => 'ACTIVE',
        ]);
    }

    public function test_reports_preview_endpoint_returns_tabular_data(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->withHeader('X-Platform-Id', (string) $this->platformA->id)
            ->getJson('/api/v1/reports?report_type=PLAYERS&period=30d');

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('report_type', 'PLAYERS');
        $this->assertIsArray($response->json('data'));
    }

    public function test_reports_export_csv_endpoint(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->withHeader('X-Platform-Id', (string) $this->platformA->id)
            ->postJson('/api/v1/reports/export', [
                'report_type' => 'PLAYERS',
                'format' => 'CSV',
                'period' => '30d',
            ]);

        $response->assertStatus(200);
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment; filename=', $response->headers->get('Content-Disposition'));
    }

    public function test_reports_export_json_endpoint(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->withHeader('X-Platform-Id', (string) $this->platformA->id)
            ->postJson('/api/v1/reports/export', [
                'report_type' => 'FINANCIAL',
                'format' => 'JSON',
                'period' => '30d',
            ]);

        $response->assertStatus(200);
        $this->assertStringContainsString('application/json', $response->headers->get('Content-Type'));
    }

    public function test_reports_export_async_dispatches_background_job(): void
    {
        Queue::fake();

        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->withHeader('X-Platform-Id', (string) $this->platformA->id)
            ->postJson('/api/v1/reports/export', [
                'report_type' => 'PLAYERS',
                'format' => 'CSV',
                'async' => true,
            ]);

        $response->assertStatus(202);
        $response->assertJsonPath('success', true);
        $this->assertNotEmpty($response->json('export_id'));

        Queue::assertPushed(\App\Jobs\GenerateAnalyticsExportJob::class);
    }

    public function test_scheduled_reports_crud(): void
    {
        // 1. Create schedule
        $resCreate = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->withHeader('X-Platform-Id', (string) $this->platformA->id)
            ->postJson('/api/v1/reports/scheduled', [
                'name' => 'Relatório Semanal de Jogadores',
                'report_type' => 'PLAYERS',
                'frequency' => 'WEEKLY',
                'recipients' => ['gerencia@betcrm.com', 'analista@betcrm.com'],
                'format' => 'CSV',
            ]);

        $resCreate->assertStatus(201);
        $resCreate->assertJsonPath('success', true);
        $id = $resCreate->json('data.id');

        // 2. List schedules
        $resList = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->withHeader('X-Platform-Id', (string) $this->platformA->id)
            ->getJson('/api/v1/reports/scheduled');

        $resList->assertStatus(200);
        $this->assertCount(1, $resList->json('data'));

        // 3. Delete schedule
        $resDel = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->withHeader('X-Platform-Id', (string) $this->platformA->id)
            ->deleteJson("/api/v1/reports/scheduled/{$id}");

        $resDel->assertStatus(200);
        $this->assertDatabaseMissing('scheduled_reports', ['id' => $id]);
    }

    public function test_alerts_lifecycle_acknowledge_and_resolve(): void
    {
        // Cria alerta inicial
        $alert = OperationalAlert::create([
            'platform_id' => $this->platformA->id,
            'metric' => 'PROVIDER_FAILURES',
            'severity' => 'WARNING',
            'status' => 'TRIGGERED',
            'title' => 'Falhas no Provedor Twilio',
            'message' => 'Taxa de erro superior a 5%',
            'current_value' => 7.5,
            'threshold_value' => 5.0,
            'triggered_at' => Carbon::now(),
        ]);

        // 1. List Alerts
        $resList = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->withHeader('X-Platform-Id', (string) $this->platformA->id)
            ->getJson('/api/v1/alerts?status=TRIGGERED');

        $resList->assertStatus(200);
        $this->assertCount(1, $resList->json('data'));

        // 2. Acknowledge Alert
        $resAck = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->withHeader('X-Platform-Id', (string) $this->platformA->id)
            ->postJson("/api/v1/alerts/{$alert->id}/acknowledge");

        $resAck->assertStatus(200);
        $resAck->assertJsonPath('data.status', 'ACKNOWLEDGED');

        // 3. Resolve Alert
        $resRes = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->withHeader('X-Platform-Id', (string) $this->platformA->id)
            ->postJson("/api/v1/alerts/{$alert->id}/resolve", [
                'notes' => 'Roteamento comutado para fallback SendGrid.',
            ]);

        $resRes->assertStatus(200);
        $resRes->assertJsonPath('data.status', 'RESOLVED');
        $this->assertDatabaseHas('operational_alerts', [
            'id' => $alert->id,
            'status' => 'RESOLVED',
            'resolution_notes' => 'Roteamento comutado para fallback SendGrid.',
        ]);
    }

    public function test_alert_rules_crud_and_evaluation(): void
    {
        // 1. Create rule
        $resRule = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->withHeader('X-Platform-Id', (string) $this->platformA->id)
            ->postJson('/api/v1/alerts/rules', [
                'name' => 'Alerta de Falhas de Provedor',
                'metric' => 'PROVIDER_FAILURES',
                'operator' => 'GT',
                'threshold' => 0.0, // Gatilho imediato para teste
                'severity' => 'CRITICAL',
                'cooldown_minutes' => 30,
            ]);

        $resRule->assertStatus(201);
        $ruleId = $resRule->json('data.id');

        // 2. List rules
        $resList = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->withHeader('X-Platform-Id', (string) $this->platformA->id)
            ->getJson('/api/v1/alerts/rules');

        $resList->assertStatus(200);
        $this->assertCount(1, $resList->json('data'));

        // 3. Immediate Evaluation
        $resEval = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->withHeader('X-Platform-Id', (string) $this->platformA->id)
            ->postJson('/api/v1/alerts/evaluate');

        $resEval->assertStatus(200);
        $resEval->assertJsonPath('success', true);
        $this->assertGreaterThanOrEqual(1, $resEval->json('data.evaluated_rules'));

        // 4. Delete rule
        $resDel = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->withHeader('X-Platform-Id', (string) $this->platformA->id)
            ->deleteJson("/api/v1/alerts/rules/{$ruleId}");

        $resDel->assertStatus(200);
        $this->assertDatabaseMissing('alert_rules', ['id' => $ruleId]);
    }

    public function test_tenant_isolation_in_alerts(): void
    {
        // Alerta na plataforma A
        OperationalAlert::create([
            'platform_id' => $this->platformA->id,
            'metric' => 'CIRCUIT_BREAKER_OPEN',
            'severity' => 'CRITICAL',
            'status' => 'TRIGGERED',
            'title' => 'Circuito Aberto',
            'message' => 'Provedor indisponível',
            'current_value' => 1,
            'threshold_value' => 0,
            'triggered_at' => Carbon::now(),
        ]);

        // Consulta na plataforma B não deve listar alertas da plataforma A
        $resB = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->withHeader('X-Platform-Id', (string) $this->platformB->id)
            ->getJson('/api/v1/alerts');

        $resB->assertStatus(200);
        $this->assertCount(0, $resB->json('data'));
    }
}
