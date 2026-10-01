<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventType;
use App\Models\Platform;
use App\Models\Player;
use App\Models\Segment;
use App\Models\SegmentCondition;
use App\Models\SegmentGroup;
use App\Models\Tag;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SegmentTest extends TestCase
{
    use RefreshDatabase;

    protected Platform $betBrasil;
    protected Platform $betGlobal;
    protected User $adminUser;
    protected User $marketingUser;
    protected User $supportUser;
    protected string $adminToken;
    protected string $marketingToken;
    protected string $supportToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->betBrasil = Platform::where('slug', 'bet-brasil')->first();
        $this->betGlobal = Platform::where('slug', 'bet-global')->first();

        $this->adminUser = User::where('email', 'admin@crm.example.com')->first();
        $this->adminToken = $this->adminUser->createToken('test_admin')->plainTextToken;

        $this->marketingUser = User::where('email', 'marketing@crm.example.com')->first();
        $this->marketingToken = $this->marketingUser->createToken('test_marketing')->plainTextToken;

        $this->supportUser = User::where('email', 'support@crm.example.com')->first();
        $this->supportToken = $this->supportUser->createToken('test_support')->plainTextToken;
    }

    /**
     * 1. Listagem de segmentos por usuário autorizado.
     */
    public function test_user_with_permission_can_list_segments(): void
    {
        Segment::create([
            'platform_id' => $this->betBrasil->id,
            'name' => 'Jogadores VIP Recentes',
            'status' => 'ACTIVE',
            'rules_tree' => [
                'operator' => 'AND',
                'children' => [
                    ['type' => 'condition', 'field' => 'player.status', 'operator' => 'equals', 'value' => 'ACTIVE'],
                ],
            ],
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->marketingToken}")
            ->withHeader('X-Platform-Id', (string) $this->betBrasil->id)
            ->getJson('/api/v1/segments');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Jogadores VIP Recentes');
    }

    /**
     * 2. Isolamento de plataforma: usuário não visualiza segmentos de outra plataforma.
     */
    public function test_platform_isolation_for_segments(): void
    {
        $segmentGlobal = Segment::create([
            'platform_id' => $this->betGlobal->id,
            'name' => 'High Rollers Global',
            'status' => 'ACTIVE',
            'rules_tree' => ['operator' => 'AND', 'children' => []],
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->marketingToken}")
            ->withHeader('X-Platform-Id', (string) $this->betBrasil->id)
            ->getJson('/api/v1/segments');

        $response->assertStatus(200)
            ->assertJsonCount(0, 'data');

        // Tentar acessar diretamente o segmento de outra plataforma resulta em 404
        $detailResponse = $this->withHeader('Authorization', "Bearer {$this->marketingToken}")
            ->withHeader('X-Platform-Id', (string) $this->betBrasil->id)
            ->getJson("/api/v1/segments/{$segmentGlobal->id}");

        $detailResponse->assertStatus(404);
    }

    /**
     * 3. RBAC: Usuário sem permissão não pode criar segmentos.
     */
    public function test_user_without_permission_cannot_create_segment(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->supportToken}")
            ->withHeader('X-Platform-Id', (string) $this->betBrasil->id)
            ->postJson('/api/v1/segments', [
                'name' => 'Tentativa Não Autorizada',
                'rules_tree' => ['operator' => 'AND', 'children' => []],
            ]);

        $response->assertStatus(403);
    }

    /**
     * 4. Criação de segmento com AST aninhada e sincronização relacional.
     */
    public function test_user_can_create_segment_with_nested_rules_tree(): void
    {
        $payload = [
            'name' => 'Baleias Ativas em SP',
            'description' => 'Jogadores do estado de SP com status ativo',
            'status' => 'ACTIVE',
            'rules_tree' => [
                'operator' => 'AND',
                'children' => [
                    [
                        'type' => 'condition',
                        'field' => 'player.status',
                        'operator' => 'equals',
                        'value' => 'ACTIVE',
                    ],
                    [
                        'type' => 'group',
                        'operator' => 'OR',
                        'children' => [
                            [
                                'type' => 'condition',
                                'field' => 'player.state',
                                'operator' => 'equals',
                                'value' => 'SP',
                            ],
                            [
                                'type' => 'condition',
                                'field' => 'player.city',
                                'operator' => 'equals',
                                'value' => 'São Paulo',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->withHeader('Authorization', "Bearer {$this->marketingToken}")
            ->withHeader('X-Platform-Id', (string) $this->betBrasil->id)
            ->postJson('/api/v1/segments', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Baleias Ativas em SP')
            ->assertJsonPath('data.status', 'ACTIVE');

        $segmentId = $response->json('data.id');

        // Valida se as tabelas relacionais foram sincronizadas a partir da árvore
        $this->assertDatabaseHas('segments', ['id' => $segmentId, 'name' => 'Baleias Ativas em SP']);
        $this->assertEquals(2, SegmentGroup::where('segment_id', $segmentId)->count());
        $this->assertEquals(3, SegmentCondition::where('segment_id', $segmentId)->count());
    }

    /**
     * 5. Validação de árvore de regras: rejeita campos ou operadores inválidos.
     */
    public function test_validation_rejects_invalid_field_or_operator(): void
    {
        $payload = [
            'name' => 'Segmento Inválido',
            'rules_tree' => [
                'operator' => 'AND',
                'children' => [
                    [
                        'type' => 'condition',
                        'field' => 'campo_inexistente_hacker',
                        'operator' => 'equals',
                        'value' => 'test',
                    ],
                ],
            ],
        ];

        $response = $this->withHeader('Authorization', "Bearer {$this->marketingToken}")
            ->withHeader('X-Platform-Id', (string) $this->betBrasil->id)
            ->postJson('/api/v1/segments', $payload);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    /**
     * 6. Live preview de regras dinâmicas sem salvar.
     */
    public function test_preview_endpoint_returns_count_and_sample(): void
    {
        $payload = [
            'rules_tree' => [
                'operator' => 'AND',
                'children' => [
                    [
                        'type' => 'condition',
                        'field' => 'player.status',
                        'operator' => 'equals',
                        'value' => 'ACTIVE',
                    ],
                ],
            ],
            'limit' => 5,
        ];

        $response = $this->withHeader('Authorization', "Bearer {$this->marketingToken}")
            ->withHeader('X-Platform-Id', (string) $this->betBrasil->id)
            ->postJson('/api/v1/segments/preview', $payload);

        // Jogadores ativos na Bet Brasil seedados = 2 (Carlos e Ana)
        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('count', 2)
            ->assertJsonCount(2, 'sample');
    }

    /**
     * 7. Filtros por Tags no compilador.
     */
    public function test_segment_compiler_filters_by_tags(): void
    {
        $payload = [
            'name' => 'Apenas VIPs',
            'rules_tree' => [
                'operator' => 'AND',
                'children' => [
                    [
                        'type' => 'condition',
                        'field' => 'tags.name',
                        'operator' => 'equals',
                        'value' => 'VIP',
                    ],
                ],
            ],
        ];

        $response = $this->withHeader('Authorization', "Bearer {$this->marketingToken}")
            ->withHeader('X-Platform-Id', (string) $this->betBrasil->id)
            ->postJson('/api/v1/segments', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.cached_count', 1);

        $segmentId = $response->json('data.id');

        // Verifica membros
        $membersResponse = $this->withHeader('Authorization', "Bearer {$this->marketingToken}")
            ->withHeader('X-Platform-Id', (string) $this->betBrasil->id)
            ->getJson("/api/v1/segments/{$segmentId}/members");

        $membersResponse->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.external_id', 'PLY-1001');
    }

    /**
     * 8. Filtros por Agregados Financeiros e Eventos (Depósitos > 100).
     */
    public function test_segment_compiler_filters_financial_aggregates(): void
    {
        $playerRich = Player::where('platform_id', $this->betBrasil->id)->where('external_id', 'PLY-1001')->first();
        $playerModest = Player::where('platform_id', $this->betBrasil->id)->where('external_id', 'PLY-1002')->first();

        $depType = EventType::where('key', 'DEPOSIT_SUCCESS')->first();

        // 2 depósitos de 150 = 300 para o playerRich
        Event::create([
            'platform_id' => $this->betBrasil->id,
            'player_id' => $playerRich->id,
            'event_type_id' => $depType->id,
            'external_event_id' => 'DEP-EVT-01',
            'payload' => ['amount' => 150.00],
            'normalized_payload' => ['data' => ['amount' => 150.00]],
            'processing_status' => 'PROCESSED',
            'occurred_at' => now(),
        ]);
        Event::create([
            'platform_id' => $this->betBrasil->id,
            'player_id' => $playerRich->id,
            'event_type_id' => $depType->id,
            'external_event_id' => 'DEP-EVT-02',
            'payload' => ['amount' => 150.00],
            'normalized_payload' => ['data' => ['amount' => 150.00]],
            'processing_status' => 'PROCESSED',
            'occurred_at' => now(),
        ]);

        // 1 depósito de 30 para o playerModest
        Event::create([
            'platform_id' => $this->betBrasil->id,
            'player_id' => $playerModest->id,
            'event_type_id' => $depType->id,
            'external_event_id' => 'DEP-EVT-03',
            'payload' => ['amount' => 30.00],
            'normalized_payload' => ['data' => ['amount' => 30.00]],
            'processing_status' => 'PROCESSED',
            'occurred_at' => now(),
        ]);

        $payload = [
            'name' => 'Depositantes Acima de 100',
            'rules_tree' => [
                'operator' => 'AND',
                'children' => [
                    [
                        'type' => 'condition',
                        'field' => 'deposit.total',
                        'operator' => 'greater_than',
                        'value' => 100,
                    ],
                ],
            ],
        ];

        $response = $this->withHeader('Authorization', "Bearer {$this->marketingToken}")
            ->withHeader('X-Platform-Id', (string) $this->betBrasil->id)
            ->postJson('/api/v1/segments', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.cached_count', 1);

        $segmentId = $response->json('data.id');

        $membersResponse = $this->withHeader('Authorization', "Bearer {$this->marketingToken}")
            ->withHeader('X-Platform-Id', (string) $this->betBrasil->id)
            ->getJson("/api/v1/segments/{$segmentId}/members");

        $membersResponse->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $playerRich->id);
    }

    /**
     * 9. Ativação e Desativação de segmento.
     */
    public function test_activate_and_deactivate_segment(): void
    {
        $segment = Segment::create([
            'platform_id' => $this->betBrasil->id,
            'name' => 'Segmento Teste Ciclo de Vida',
            'status' => 'DRAFT',
            'rules_tree' => ['operator' => 'AND', 'children' => []],
        ]);

        $actResponse = $this->withHeader('Authorization', "Bearer {$this->marketingToken}")
            ->withHeader('X-Platform-Id', (string) $this->betBrasil->id)
            ->postJson("/api/v1/segments/{$segment->id}/activate");

        $actResponse->assertStatus(200)
            ->assertJsonPath('data.status', 'ACTIVE');

        $deactResponse = $this->withHeader('Authorization', "Bearer {$this->marketingToken}")
            ->withHeader('X-Platform-Id', (string) $this->betBrasil->id)
            ->postJson("/api/v1/segments/{$segment->id}/deactivate");

        $deactResponse->assertStatus(200)
            ->assertJsonPath('data.status', 'INACTIVE');
    }

    /**
     * 10. Duplicação de segmento.
     */
    public function test_duplicate_segment(): void
    {
        $segment = Segment::create([
            'platform_id' => $this->betBrasil->id,
            'name' => 'Original Segment',
            'status' => 'ACTIVE',
            'rules_tree' => [
                'operator' => 'AND',
                'children' => [
                    ['type' => 'condition', 'field' => 'player.state', 'operator' => 'equals', 'value' => 'RJ'],
                ],
            ],
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->marketingToken}")
            ->withHeader('X-Platform-Id', (string) $this->betBrasil->id)
            ->postJson("/api/v1/segments/{$segment->id}/duplicate");

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Original Segment (Cópia)')
            ->assertJsonPath('data.status', 'DRAFT');

        $this->assertDatabaseHas('segments', [
            'name' => 'Original Segment (Cópia)',
            'platform_id' => $this->betBrasil->id,
        ]);
    }

    /**
     * 11. Refresh da contagem de membros e renovação de cache.
     */
    public function test_refresh_segment_count(): void
    {
        $segment = Segment::create([
            'platform_id' => $this->betBrasil->id,
            'name' => 'Segmento Contador',
            'status' => 'ACTIVE',
            'rules_tree' => ['operator' => 'AND', 'children' => []],
            'cached_count' => 0,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->marketingToken}")
            ->withHeader('X-Platform-Id', (string) $this->betBrasil->id)
            ->postJson("/api/v1/segments/{$segment->id}/refresh");

        // Existem 4 jogadores seedados na Bet Brasil
        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('cached_count', 4);

        $this->assertDatabaseHas('segments', [
            'id' => $segment->id,
            'cached_count' => 4,
        ]);
    }

    /**
     * 12. Catálogos de campos e operadores.
     */
    public function test_catalogs_endpoints(): void
    {
        $fieldsResponse = $this->withHeader('Authorization', "Bearer {$this->marketingToken}")
            ->withHeader('X-Platform-Id', (string) $this->betBrasil->id)
            ->getJson('/api/v1/segment-fields');

        $fieldsResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['player', 'tags', 'consents', 'deposits', 'bets', 'withdrawals', 'logins']]);

        $operatorsResponse = $this->withHeader('Authorization', "Bearer {$this->marketingToken}")
            ->withHeader('X-Platform-Id', (string) $this->betBrasil->id)
            ->getJson('/api/v1/segment-operators');

        $operatorsResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['comparison', 'temporal', 'existence']]);
    }
}
