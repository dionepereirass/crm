<?php

namespace Tests\Feature;

use App\Enums\AutomationStatus;
use App\Enums\AutomationTriggerType;
use App\Models\Automation;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutomationAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected Platform $platformA;
    protected Platform $platformB;
    protected User $superAdmin;
    protected User $adminUser;
    protected User $marketingUser;
    protected User $supportUser;
    protected User $analystUser;

    protected string $superAdminToken;
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

        $this->superAdmin = User::where('email', 'superadmin@crm.example.com')->firstOrFail();
        $this->superAdminToken = $this->superAdmin->createToken('sa_test')->plainTextToken;

        $this->adminUser = User::where('email', 'admin@crm.example.com')->firstOrFail();
        $this->adminToken = $this->adminUser->createToken('admin_test')->plainTextToken;

        $this->marketingUser = User::where('email', 'marketing@crm.example.com')->firstOrFail();
        $this->marketingToken = $this->marketingUser->createToken('mkt_test')->plainTextToken;

        $this->supportUser = User::where('email', 'support@crm.example.com')->firstOrFail();
        $this->supportToken = $this->supportUser->createToken('sup_test')->plainTextToken;

        $this->analystUser = User::where('email', 'analyst@crm.example.com')->firstOrFail();
        $this->analystToken = $this->analystUser->createToken('ana_test')->plainTextToken;
    }

    public function test_super_admin_and_admin_have_full_management(): void
    {
        // SUPER_ADMIN cria automação
        $responseSA = $this->withHeaders([
            'Authorization' => "Bearer {$this->superAdminToken}",
            'X-Platform-Id' => (string) $this->platformA->id,
        ])->postJson('/api/v1/automations', [
            'name' => 'Automação SA',
            'trigger_type' => 'LOGIN',
        ]);
        $responseSA->assertStatus(201);

        // ADMIN cria automação
        $responseAdmin = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
            'X-Platform-Id' => (string) $this->platformA->id,
        ])->postJson('/api/v1/automations', [
            'name' => 'Automação Admin',
            'trigger_type' => 'LOGIN',
        ]);
        $responseAdmin->assertStatus(201);
    }

    public function test_marketing_user_can_create_update_and_manage_automations(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->marketingToken}",
            'X-Platform-Id' => (string) $this->platformA->id,
        ])->postJson('/api/v1/automations', [
            'name' => 'Automação Marketing',
            'trigger_type' => 'PLAYER_CREATED',
        ]);

        $response->assertStatus(201);
        $automationId = $response->json('data.id');

        // Marketing pode atualizar
        $updateResponse = $this->withHeaders([
            'Authorization' => "Bearer {$this->marketingToken}",
            'X-Platform-Id' => (string) $this->platformA->id,
        ])->putJson("/api/v1/automations/{$automationId}", [
            'name' => 'Automação Marketing Modificada',
        ]);
        $updateResponse->assertStatus(200);

        // Marketing pode salvar grafo
        $graphResponse = $this->withHeaders([
            'Authorization' => "Bearer {$this->marketingToken}",
            'X-Platform-Id' => (string) $this->platformA->id,
        ])->putJson("/api/v1/automations/{$automationId}/graph", [
            'nodes' => [
                [
                    'node_key' => 'trigger-1',
                    'node_type' => 'TRIGGER',
                    'name' => 'Gatilho',
                ],
            ],
            'edges' => [],
        ]);
        $graphResponse->assertStatus(200);
    }

    public function test_support_user_has_read_only_access_and_cannot_create_or_activate(): void
    {
        // Support pode listar
        $listResponse = $this->withHeaders([
            'Authorization' => "Bearer {$this->supportToken}",
            'X-Platform-Id' => (string) $this->platformA->id,
        ])->getJson('/api/v1/automations');
        $listResponse->assertStatus(200);

        // Support NÃO pode criar
        $createResponse = $this->withHeaders([
            'Authorization' => "Bearer {$this->supportToken}",
            'X-Platform-Id' => (string) $this->platformA->id,
        ])->postJson('/api/v1/automations', [
            'name' => 'Tentativa Suporte',
            'trigger_type' => 'LOGIN',
        ]);
        $createResponse->assertStatus(403);

        $automation = Automation::create([
            'platform_id' => $this->platformA->id,
            'name' => 'Automação Existente',
            'status' => AutomationStatus::DRAFT,
            'trigger_type' => 'LOGIN',
        ]);

        // Support NÃO pode ativar
        $activateResponse = $this->withHeaders([
            'Authorization' => "Bearer {$this->supportToken}",
            'X-Platform-Id' => (string) $this->platformA->id,
        ])->postJson("/api/v1/automations/{$automation->id}/activate");
        $activateResponse->assertStatus(403);
    }

    public function test_analyst_user_can_view_automations_and_runs_cannot_create(): void
    {
        $automation = Automation::create([
            'platform_id' => $this->platformA->id,
            'name' => 'Automação para Analista',
            'status' => AutomationStatus::ACTIVE,
            'trigger_type' => 'DEPOSIT_SUCCESS',
        ]);

        // Analyst pode visualizar
        $showResponse = $this->withHeaders([
            'Authorization' => "Bearer {$this->analystToken}",
            'X-Platform-Id' => (string) $this->platformA->id,
        ])->getJson("/api/v1/automations/{$automation->id}");
        $showResponse->assertStatus(200);

        // Analyst pode listar execuções
        $runsResponse = $this->withHeaders([
            'Authorization' => "Bearer {$this->analystToken}",
            'X-Platform-Id' => (string) $this->platformA->id,
        ])->getJson("/api/v1/automations/{$automation->id}/runs");
        $runsResponse->assertStatus(200);

        // Analyst NÃO pode criar
        $createResponse = $this->withHeaders([
            'Authorization' => "Bearer {$this->analystToken}",
            'X-Platform-Id' => (string) $this->platformA->id,
        ])->postJson('/api/v1/automations', [
            'name' => 'Tentativa Analista',
            'trigger_type' => 'LOGIN',
        ]);
        $createResponse->assertStatus(403);
    }

    public function test_cross_platform_isolation_blocks_access(): void
    {
        // Automação na plataforma B
        $automationB = Automation::create([
            'platform_id' => $this->platformB->id,
            'name' => 'Automação Segredo Plataforma B',
            'status' => AutomationStatus::ACTIVE,
            'trigger_type' => 'LOGIN',
        ]);

        // Usuário da Plataforma A tenta visualizar automação da Plataforma B
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
            'X-Platform-Id' => (string) $this->platformA->id,
        ])->getJson("/api/v1/automations/{$automationB->id}");

        $response->assertStatus(403);
    }
}
