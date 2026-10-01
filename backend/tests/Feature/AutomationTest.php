<?php

namespace Tests\Feature;

use App\Enums\AutomationNodeType;
use App\Enums\AutomationStatus;
use App\Enums\AutomationTriggerType;
use App\Models\Automation;
use App\Models\Platform;
use App\Models\Role;
use App\Models\Template;
use App\Models\TemplateVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutomationTest extends TestCase
{
    use RefreshDatabase;

    protected Platform $platformA;
    protected Platform $platformB;
    protected User $adminUser;
    protected string $adminToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);

        $this->platformA = Platform::where('slug', 'bet-brasil')->firstOrFail();
        $this->platformB = Platform::where('slug', 'bet-global')->firstOrFail();

        $this->adminUser = User::where('email', 'admin@crm.example.com')->firstOrFail();
        $this->adminToken = $this->adminUser->createToken('test_token')->plainTextToken;
    }

    public function test_user_can_create_and_view_automation(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
            'X-Platform-Id' => (string) $this->platformA->id,
        ])->postJson('/api/v1/automations', [
            'name' => 'Boas-vindas Novos Apostadores',
            'description' => 'Jornada inicial de ativação após cadastro',
            'trigger_type' => 'PLAYER_CREATED',
            'settings' => [
                'reentry_policy' => 'BLOCK_REENTRY',
                'cooldown_days' => 0,
                'max_steps_per_run' => 20,
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Boas-vindas Novos Apostadores')
            ->assertJsonPath('data.status', 'DRAFT')
            ->assertJsonPath('data.trigger_type', 'PLAYER_CREATED');

        $automationId = $response->json('data.id');

        // Nó de gatilho padrão deve ser gerado automaticamente
        $this->assertDatabaseHas('automation_nodes', [
            'automation_id' => $automationId,
            'node_type' => 'TRIGGER',
            'node_key' => 'trigger-1',
        ]);

        // Consulta de detalhes
        $showResponse = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
            'X-Platform-Id' => (string) $this->platformA->id,
        ])->getJson("/api/v1/automations/{$automationId}");

        $showResponse->assertStatus(200)
            ->assertJsonPath('data.id', $automationId)
            ->assertJsonStructure(['data', 'metrics']);
    }

    public function test_user_can_update_and_delete_automation(): void
    {
        $automation = Automation::create([
            'platform_id' => $this->platformA->id,
            'name' => 'Automação Antiga',
            'status' => 'DRAFT',
            'trigger_type' => 'LOGIN',
        ]);

        $updateResponse = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
            'X-Platform-Id' => (string) $this->platformA->id,
        ])->putJson("/api/v1/automations/{$automation->id}", [
            'name' => 'Automação Atualizada',
            'description' => 'Descrição nova',
        ]);

        $updateResponse->assertStatus(200)
            ->assertJsonPath('data.name', 'Automação Atualizada');

        // Exclusão
        $deleteResponse = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
            'X-Platform-Id' => (string) $this->platformA->id,
        ])->deleteJson("/api/v1/automations/{$automation->id}");

        $deleteResponse->assertStatus(200);
        $this->assertSoftDeleted('automations', ['id' => $automation->id]);
    }

    public function test_platform_isolation_prevents_access_to_other_platform_automations(): void
    {
        $otherAutomation = Automation::create([
            'platform_id' => $this->platformB->id,
            'name' => 'Automação da Plataforma B',
            'status' => 'DRAFT',
            'trigger_type' => 'DEPOSIT_SUCCESS',
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
            'X-Platform-Id' => (string) $this->platformA->id,
        ])->getJson("/api/v1/automations/{$otherAutomation->id}");

        $response->assertStatus(403);
    }

    public function test_save_graph_and_retrieve_graph(): void
    {
        $automation = Automation::create([
            'platform_id' => $this->platformA->id,
            'name' => 'Jornada com Grafo',
            'status' => 'DRAFT',
            'trigger_type' => 'DEPOSIT_SUCCESS',
        ]);

        $graphPayload = [
            'nodes' => [
                [
                    'node_key' => 'trigger-1',
                    'node_type' => 'TRIGGER',
                    'name' => 'Gatilho Depósito',
                    'configuration' => ['trigger_type' => 'DEPOSIT_SUCCESS'],
                    'position_x' => 100,
                    'position_y' => 100,
                ],
                [
                    'node_key' => 'wait-1',
                    'node_type' => 'WAIT',
                    'name' => 'Aguardar 2 Horas',
                    'configuration' => ['amount' => 2, 'unit' => 'hours'],
                    'position_x' => 100,
                    'position_y' => 250,
                ],
                [
                    'node_key' => 'tag-1',
                    'node_type' => 'ACTION',
                    'name' => 'Adicionar Tag VIP',
                    'configuration' => ['action_type' => 'ADD_TAG', 'tag_name' => 'VIP'],
                    'position_x' => 100,
                    'position_y' => 400,
                ],
            ],
            'edges' => [
                [
                    'source_node_key' => 'trigger-1',
                    'target_node_key' => 'wait-1',
                ],
                [
                    'source_node_key' => 'wait-1',
                    'target_node_key' => 'tag-1',
                ],
            ],
        ];

        $saveResponse = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
            'X-Platform-Id' => (string) $this->platformA->id,
        ])->putJson("/api/v1/automations/{$automation->id}/graph", $graphPayload);

        $saveResponse->assertStatus(200);

        // Consulta o grafo
        $getGraphResponse = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
            'X-Platform-Id' => (string) $this->platformA->id,
        ])->getJson("/api/v1/automations/{$automation->id}/graph");

        $getGraphResponse->assertStatus(200)
            ->assertJsonCount(3, 'nodes')
            ->assertJsonCount(2, 'edges');
    }

    public function test_cycle_detection_blocks_infinite_loops(): void
    {
        $automation = Automation::create([
            'platform_id' => $this->platformA->id,
            'name' => 'Automação com Ciclo Proibido',
            'status' => 'DRAFT',
            'trigger_type' => 'DEPOSIT_SUCCESS',
        ]);

        // Grafo com ciclo: Node A -> Node B -> Node C -> Node A
        $cyclePayload = [
            'nodes' => [
                [
                    'node_key' => 'trigger-1',
                    'node_type' => 'TRIGGER',
                    'name' => 'Gatilho',
                    'configuration' => ['trigger_type' => 'DEPOSIT_SUCCESS'],
                ],
                [
                    'node_key' => 'action-a',
                    'node_type' => 'ACTION',
                    'name' => 'Ação A',
                    'configuration' => ['action_type' => 'ADD_TAG', 'tag_name' => 'A'],
                ],
                [
                    'node_key' => 'action-b',
                    'node_type' => 'ACTION',
                    'name' => 'Ação B',
                    'configuration' => ['action_type' => 'ADD_TAG', 'tag_name' => 'B'],
                ],
            ],
            'edges' => [
                ['source_node_key' => 'trigger-1', 'target_node_key' => 'action-a'],
                ['source_node_key' => 'action-a', 'target_node_key' => 'action-b'],
                ['source_node_key' => 'action-b', 'target_node_key' => 'action-a'], // Ciclo!
            ],
        ];

        $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
            'X-Platform-Id' => (string) $this->platformA->id,
        ])->putJson("/api/v1/automations/{$automation->id}/graph", $cyclePayload);

        // Validação deve acusar AUTOMATION_GRAPH_INVALID
        $validateResponse = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
            'X-Platform-Id' => (string) $this->platformA->id,
        ])->postJson("/api/v1/automations/{$automation->id}/validate");

        $validateResponse->assertStatus(200)
            ->assertJsonPath('valid', false)
            ->assertJsonFragment(['code' => 'AUTOMATION_GRAPH_INVALID']);

        // Tentativa de ativação deve falhar
        $activateResponse = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
            'X-Platform-Id' => (string) $this->platformA->id,
        ])->postJson("/api/v1/automations/{$automation->id}/activate");

        $activateResponse->assertStatus(422);
    }

    public function test_activate_pause_and_preview_automation(): void
    {
        $automation = Automation::create([
            'platform_id' => $this->platformA->id,
            'name' => 'Jornada Válida para Ativação',
            'status' => 'DRAFT',
            'trigger_type' => 'PLAYER_CREATED',
        ]);

        $validGraph = [
            'nodes' => [
                [
                    'node_key' => 'trigger-1',
                    'node_type' => 'TRIGGER',
                    'name' => 'Início Cadastro',
                    'configuration' => ['trigger_type' => 'PLAYER_CREATED'],
                ],
                [
                    'node_key' => 'tag-1',
                    'node_type' => 'ACTION',
                    'name' => 'Tag Boas-vindas',
                    'configuration' => ['action_type' => 'ADD_TAG', 'tag_name' => 'Novo Cadastro'],
                ],
            ],
            'edges' => [
                ['source_node_key' => 'trigger-1', 'target_node_key' => 'tag-1'],
            ],
        ];

        $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
            'X-Platform-Id' => (string) $this->platformA->id,
        ])->putJson("/api/v1/automations/{$automation->id}/graph", $validGraph);

        // Preview da jornada
        $previewResponse = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
            'X-Platform-Id' => (string) $this->platformA->id,
        ])->postJson("/api/v1/automations/{$automation->id}/preview");

        $previewResponse->assertStatus(200)
            ->assertJsonPath('nodes_count', 2)
            ->assertJsonPath('validation.valid', true);

        // Ativação bem-sucedida
        $activateResponse = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
            'X-Platform-Id' => (string) $this->platformA->id,
        ])->postJson("/api/v1/automations/{$automation->id}/activate");

        $activateResponse->assertStatus(200)
            ->assertJsonPath('data.status', 'ACTIVE');

        // Pausa
        $pauseResponse = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
            'X-Platform-Id' => (string) $this->platformA->id,
        ])->postJson("/api/v1/automations/{$automation->id}/pause");

        $pauseResponse->assertStatus(200)
            ->assertJsonPath('data.status', 'PAUSED');
    }
}
