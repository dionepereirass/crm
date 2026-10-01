<?php

namespace Tests\Feature;

use App\Models\Platform;
use App\Models\Player;
use App\Models\TrackingToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected Platform $platform;
    protected User $adminUser;
    protected string $adminToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);

        $this->platform = Platform::where('slug', 'bet-brasil')->firstOrFail();
        $this->adminUser = User::where('email', 'admin@crm.example.com')->firstOrFail();
        $this->adminUser->platforms()->syncWithoutDetaching([$this->platform->id]);
        $this->adminToken = $this->adminUser->createToken('smoke_admin')->plainTextToken;
    }

    /**
     * 1. Smoke Test: Login e Autenticação
     */
    public function test_smoke_01_authentication_login(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@crm.example.com',
            'password' => 'Secret@123456',
            'platform_id' => $this->platform->id,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $this->assertNotEmpty($response->json('data.token'));
    }

    /**
     * 2. Smoke Test: Seleção de Plataforma e Contexto Multi-Tenant
     */
    public function test_smoke_02_platform_selection_and_me(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->withHeader('X-Platform-Id', (string) $this->platform->id)
            ->getJson('/api/v1/auth/me');

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.email', 'admin@crm.example.com');
    }

    /**
     * 3. Smoke Test: Dashboard Executivo
     */
    public function test_smoke_03_executive_dashboard(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->withHeader('X-Platform-Id', (string) $this->platform->id)
            ->getJson('/api/v1/analytics/dashboard?period=30d');

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $this->assertArrayHasKey('kpis', $response->json('data'));
    }

    /**
     * 4. Smoke Test: Módulo de Jogadores (Players)
     */
    public function test_smoke_04_players_module(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->withHeader('X-Platform-Id', (string) $this->platform->id)
            ->getJson('/api/v1/players');

        $response->assertStatus(200);
        $this->assertArrayHasKey('data', $response->json());
    }

    /**
     * 5. Smoke Test: Módulo de Eventos Transacionais
     */
    public function test_smoke_05_events_module(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->withHeader('X-Platform-Id', (string) $this->platform->id)
            ->getJson('/api/v1/events');

        $response->assertStatus(200);
        $this->assertArrayHasKey('data', $response->json());
    }

    /**
     * 6. Smoke Test: Módulo de Segmentação
     */
    public function test_smoke_06_segments_module(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->withHeader('X-Platform-Id', (string) $this->platform->id)
            ->getJson('/api/v1/segments');

        $response->assertStatus(200);
        $this->assertArrayHasKey('data', $response->json());
    }

    /**
     * 7. Smoke Test: Módulo de Templates
     */
    public function test_smoke_07_templates_module(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->withHeader('X-Platform-Id', (string) $this->platform->id)
            ->getJson('/api/v1/templates');

        $response->assertStatus(200);
        $this->assertArrayHasKey('data', $response->json());
    }

    /**
     * 8. Smoke Test: Módulo de Provedores de Mensageria
     */
    public function test_smoke_08_providers_module(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->withHeader('X-Platform-Id', (string) $this->platform->id)
            ->getJson('/api/v1/providers');

        $response->assertStatus(200);
        $this->assertArrayHasKey('data', $response->json());
    }

    /**
     * 9. Smoke Test: Módulo de Mensagens
     */
    public function test_smoke_09_messages_module(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->withHeader('X-Platform-Id', (string) $this->platform->id)
            ->getJson('/api/v1/messages');

        $response->assertStatus(200);
        $this->assertArrayHasKey('data', $response->json());
    }

    /**
     * 10. Smoke Test: Módulo de Campanhas de Marketing
     */
    public function test_smoke_10_campaigns_module(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->withHeader('X-Platform-Id', (string) $this->platform->id)
            ->getJson('/api/v1/campaigns');

        $response->assertStatus(200);
        $this->assertArrayHasKey('data', $response->json());
    }

    /**
     * 11. Smoke Test: Módulo de Tracking (Pixel / Open Endpoint)
     */
    public function test_smoke_11_tracking_pixel_endpoint(): void
    {
        $token = \Illuminate\Support\Str::random(48);
        \App\Models\Message::create([
            'platform_id' => $this->platform->id,
            'channel' => 'EMAIL',
            'recipient' => 'smoke_test@example.com',
            'status' => 'SENT',
            'idempotency_key' => 'idemp_' . \Illuminate\Support\Str::uuid()->toString(),
            'open_tracking_token' => $token,
        ]);

        $response = $this->get("/api/v1/tracking/open/{$token}");
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'image/gif');
    }

    /**
     * 12. Smoke Test: Módulo de Automações e Jornadas
     */
    public function test_smoke_12_automations_module(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->withHeader('X-Platform-Id', (string) $this->platform->id)
            ->getJson('/api/v1/automations');

        $response->assertStatus(200);
        $this->assertArrayHasKey('data', $response->json());
    }

    /**
     * 13. Smoke Test: Módulo de Privacidade e LGPD
     */
    public function test_smoke_13_privacy_and_lgpd_module(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->withHeader('X-Platform-Id', (string) $this->platform->id)
            ->getJson('/api/v1/privacy/dashboard');

        $response->assertStatus(200);
        $this->assertArrayHasKey('active_consents', $response->json('metrics'));
    }

    /**
     * 14. Smoke Test: Módulo de Analytics Geral
     */
    public function test_smoke_14_analytics_module(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->withHeader('X-Platform-Id', (string) $this->platform->id)
            ->getJson('/api/v1/analytics/overview');

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
    }

    /**
     * 15. Smoke Test: Módulo de Relatórios e Exportação
     */
    public function test_smoke_15_reports_module(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->withHeader('X-Platform-Id', (string) $this->platform->id)
            ->getJson('/api/v1/reports?report_type=PLAYERS&period=7d');

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
    }

    /**
     * 16. Smoke Test: Módulo de Alertas Operacionais
     */
    public function test_smoke_16_alerts_module(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->withHeader('X-Platform-Id', (string) $this->platform->id)
            ->getJson('/api/v1/alerts');

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
    }
}
