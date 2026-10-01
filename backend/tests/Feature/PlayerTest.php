<?php

namespace Tests\Feature;

use App\Models\Platform;
use App\Models\Player;
use App\Models\Tag;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlayerTest extends TestCase
{
    use RefreshDatabase;

    protected Platform $betBrasil;
    protected Platform $betGlobal;
    protected User $adminUser;
    protected User $supportUser;
    protected string $adminToken;
    protected string $supportToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->betBrasil = Platform::where('slug', 'bet-brasil')->first();
        $this->betGlobal = Platform::where('slug', 'bet-global')->first();

        $this->adminUser = User::where('email', 'admin@crm.example.com')->first();
        $this->adminToken = $this->adminUser->createToken('test_admin')->plainTextToken;

        $this->supportUser = User::where('email', 'support@crm.example.com')->first();
        $this->supportToken = $this->supportUser->createToken('test_support')->plainTextToken;
    }

    /**
     * 1. Criar Jogador
     */
    public function test_can_create_player(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->withHeader('X-Platform-Id', (string) $this->betBrasil->id)
            ->postJson('/api/v1/players', [
                'external_id' => 'PLY-NEW-99',
                'name' => 'Roberto Carlos',
                'email' => 'roberto@email.com',
                'phone' => '5511999990000',
                'cpf' => '11122233344',
                'city' => 'Campinas',
                'state' => 'SP',
                'status' => 'ACTIVE',
                'custom_fields' => ['tier' => 'Platinum'],
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.external_id', 'PLY-NEW-99')
            ->assertJsonPath('data.name', 'Roberto Carlos')
            ->assertJsonPath('data.platform_id', $this->betBrasil->id);

        $this->assertDatabaseHas('players', [
            'external_id' => 'PLY-NEW-99',
            'platform_id' => $this->betBrasil->id,
        ]);
    }

    /**
     * 2. Listar Jogadores
     */
    public function test_can_list_players(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->withHeader('X-Platform-Id', (string) $this->betBrasil->id)
            ->getJson('/api/v1/players');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['id', 'external_id', 'name', 'status', 'platform_id']
                ],
                'pagination' => ['per_page', 'total', 'current_page'],
            ]);
    }

    /**
     * 3. Visualizar Jogador
     */
    public function test_can_view_player(): void
    {
        $player = Player::where('platform_id', $this->betBrasil->id)->first();

        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->withHeader('X-Platform-Id', (string) $this->betBrasil->id)
            ->getJson("/api/v1/players/{$player->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $player->id)
            ->assertJsonPath('data.external_id', $player->external_id);
    }

    /**
     * 4. Editar Jogador
     */
    public function test_can_update_player(): void
    {
        $player = Player::where('platform_id', $this->betBrasil->id)->first();

        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->withHeader('X-Platform-Id', (string) $this->betBrasil->id)
            ->putJson("/api/v1/players/{$player->id}", [
                'name' => 'Nome Atualizado do Jogador',
                'status' => 'INACTIVE',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Nome Atualizado do Jogador')
            ->assertJsonPath('data.status', 'INACTIVE');

        $this->assertEquals('Nome Atualizado do Jogador', $player->fresh()->name);
    }

    /**
     * 5. Excluir Jogador (Soft Delete)
     */
    public function test_can_soft_delete_player(): void
    {
        $player = Player::where('platform_id', $this->betBrasil->id)->first();

        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->withHeader('X-Platform-Id', (string) $this->betBrasil->id)
            ->deleteJson("/api/v1/players/{$player->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertSoftDeleted('players', ['id' => $player->id]);
    }

    /**
     * 6. Buscar Jogador
     */
    public function test_search_player_by_name_and_external_id(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->withHeader('X-Platform-Id', (string) $this->betBrasil->id)
            ->getJson('/api/v1/players?search=Carlos');

        $response->assertStatus(200)
            ->assertJsonPath('data.0.name', 'Carlos Eduardo Santos');

        $responseExternal = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->withHeader('X-Platform-Id', (string) $this->betBrasil->id)
            ->getJson('/api/v1/players?search=PLY-1002');

        $responseExternal->assertStatus(200)
            ->assertJsonPath('data.0.external_id', 'PLY-1002');
    }

    /**
     * 7. Filtrar por Status
     */
    public function test_filter_players_by_status(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->withHeader('X-Platform-Id', (string) $this->betBrasil->id)
            ->getJson('/api/v1/players?status=BLOCKED');

        $response->assertStatus(200);
        foreach ($response->json('data') as $p) {
            $this->assertEquals('BLOCKED', $p['status']);
        }
    }

    /**
     * 8. Filtrar por Estado
     */
    public function test_filter_players_by_state(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->withHeader('X-Platform-Id', (string) $this->betBrasil->id)
            ->getJson('/api/v1/players?state=MG');

        $response->assertStatus(200)
            ->assertJsonPath('data.0.state', 'MG');
    }

    /**
     * 9. Filtrar por Cidade
     */
    public function test_filter_players_by_city(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->withHeader('X-Platform-Id', (string) $this->betBrasil->id)
            ->getJson('/api/v1/players?city=Curitiba');

        $response->assertStatus(200)
            ->assertJsonPath('data.0.city', 'Curitiba');
    }

    /**
     * 10. Filtrar por Afiliado
     */
    public function test_filter_players_by_affiliate(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->withHeader('X-Platform-Id', (string) $this->betBrasil->id)
            ->getJson('/api/v1/players?affiliate=canal_apostas');

        $response->assertStatus(200)
            ->assertJsonPath('data.0.affiliate', 'canal_apostas');
    }

    /**
     * 11. Filtrar por Tag
     */
    public function test_filter_players_by_tag(): void
    {
        $vipTag = Tag::where('platform_id', $this->betBrasil->id)->where('name', 'VIP')->first();

        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->withHeader('X-Platform-Id', (string) $this->betBrasil->id)
            ->getJson("/api/v1/players?tag_id={$vipTag->id}");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertNotEmpty($data);
        $this->assertEquals('PLY-1001', $data[0]['external_id']);
    }

    /**
     * 12. Paginação Offset
     */
    public function test_players_offset_pagination(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->withHeader('X-Platform-Id', (string) $this->betBrasil->id)
            ->getJson('/api/v1/players?per_page=2&page=1');

        $response->assertStatus(200)
            ->assertJsonPath('pagination.per_page', 2)
            ->assertJsonPath('pagination.current_page', 1);
        $this->assertCount(2, $response->json('data'));
    }

    /**
     * 13. Cursor Pagination
     */
    public function test_players_cursor_pagination(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->withHeader('X-Platform-Id', (string) $this->betBrasil->id)
            ->getJson('/api/v1/players?cursor_mode=1&per_page=2');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'pagination' => ['next_cursor'],
            ]);
    }

    /**
     * 14. Rejeição de Duplicate External ID na Mesma Plataforma
     */
    public function test_cannot_create_duplicate_external_id_in_same_platform(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->withHeader('X-Platform-Id', (string) $this->betBrasil->id)
            ->postJson('/api/v1/players', [
                'external_id' => 'PLY-1001', // Já existe na Bet Brasil
                'name' => 'Jogador Duplicado',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['external_id']);
    }

    /**
     * 15. Permitir Mesmo External ID em Plataformas Diferentes
     */
    public function test_allows_same_external_id_in_different_platforms(): void
    {
        // PLY-1001 já foi criado na Bet Brasil e na Bet Global pelo seeder
        $playerBrasil = Player::withoutGlobalScopes()
            ->where('platform_id', $this->betBrasil->id)
            ->where('external_id', 'PLY-1001')
            ->first();

        $playerGlobal = Player::withoutGlobalScopes()
            ->where('platform_id', $this->betGlobal->id)
            ->where('external_id', 'PLY-1001')
            ->first();

        $this->assertNotNull($playerBrasil);
        $this->assertNotNull($playerGlobal);
        $this->assertNotEquals($playerBrasil->platform_id, $playerGlobal->platform_id);
    }

    /**
     * 16. Isolamento entre Plataformas na Listagem
     */
    public function test_platform_isolation_in_listing(): void
    {
        // Admin da Bet Brasil só deve ver jogadores da Bet Brasil
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->withHeader('X-Platform-Id', (string) $this->betBrasil->id)
            ->getJson('/api/v1/players');

        $response->assertStatus(200);
        foreach ($response->json('data') as $p) {
            $this->assertEquals($this->betBrasil->id, $p['platform_id']);
            $this->assertNotEquals('John Doe (Global)', $p['name']);
        }
    }

    /**
     * 17. Usuário sem Permissão
     */
    public function test_user_without_permission_cannot_delete_player(): void
    {
        // Support não tem permissão 'players.delete'
        $player = Player::where('platform_id', $this->betBrasil->id)->first();

        $response = $this->withHeader('Authorization', "Bearer {$this->supportToken}")
            ->withHeader('X-Platform-Id', (string) $this->betBrasil->id)
            ->deleteJson("/api/v1/players/{$player->id}");

        $response->assertStatus(403);
    }

    /**
     * 18. Usuário com Permissão
     */
    public function test_user_with_permission_can_view_players(): void
    {
        // Support tem permissão 'players.view'
        $response = $this->withHeader('Authorization', "Bearer {$this->supportToken}")
            ->withHeader('X-Platform-Id', (string) $this->betBrasil->id)
            ->getJson('/api/v1/players');

        $response->assertStatus(200);
    }

    /**
     * 19. Criar Tag
     */
    public function test_can_create_tag(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->withHeader('X-Platform-Id', (string) $this->betBrasil->id)
            ->postJson('/api/v1/tags', [
                'name' => 'BLACK_FRIDAY',
                'color' => '#10b981',
                'description' => 'Apostadores da Black Friday',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'BLACK_FRIDAY');
    }

    /**
     * 20. Associar Tag ao Jogador
     */
    public function test_can_attach_tag_to_player(): void
    {
        $player = Player::where('platform_id', $this->betBrasil->id)->first();
        $tag = Tag::where('platform_id', $this->betBrasil->id)->first();

        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->withHeader('X-Platform-Id', (string) $this->betBrasil->id)
            ->postJson("/api/v1/players/{$player->id}/tags", [
                'tag_id' => $tag->id,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertTrue($player->fresh()->tags->contains('id', $tag->id));
    }

    /**
     * 21. Remover Tag do Jogador
     */
    public function test_can_detach_tag_from_player(): void
    {
        $player = Player::where('platform_id', $this->betBrasil->id)->first();
        $tag = Tag::where('platform_id', $this->betBrasil->id)->first();
        $player->tags()->syncWithoutDetaching([$tag->id]);

        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->withHeader('X-Platform-Id', (string) $this->betBrasil->id)
            ->deleteJson("/api/v1/players/{$player->id}/tags/{$tag->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertFalse($player->fresh()->tags->contains('id', $tag->id));
    }

    /**
     * 22. Impedir Tag de Outra Plataforma
     */
    public function test_cannot_attach_tag_from_another_platform(): void
    {
        $playerBrasil = Player::where('platform_id', $this->betBrasil->id)->first();
        $tagGlobal = Tag::withoutGlobalScopes()->where('platform_id', $this->betGlobal->id)->first();

        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->withHeader('X-Platform-Id', (string) $this->betBrasil->id)
            ->postJson("/api/v1/players/{$playerBrasil->id}/tags", [
                'tag_id' => $tagGlobal->id,
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Não é permitido associar tags pertencentes a outra plataforma.');
    }

    /**
     * 23. Custom Fields (Armazenamento e Leitura)
     */
    public function test_custom_fields_storage_and_retrieval(): void
    {
        $player = Player::where('platform_id', $this->betBrasil->id)->first();

        $this->assertIsArray($player->custom_fields);
        $this->assertEquals('Ouro', $player->custom_fields['nivel_vip']);

        // Update custom fields
        $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->withHeader('X-Platform-Id', (string) $this->betBrasil->id)
            ->putJson("/api/v1/players/{$player->id}", [
                'custom_fields' => ['novo_campo' => 'Valor Customizado'],
            ])->assertStatus(200);

        $fresh = $player->fresh();
        $this->assertEquals('Valor Customizado', $fresh->custom_fields['novo_campo']);
        $this->assertEquals('Ouro', $fresh->custom_fields['nivel_vip']);
    }

    /**
     * 24. Ficha 360° do Jogador
     */
    public function test_player_360_view_endpoint(): void
    {
        $player = Player::where('platform_id', $this->betBrasil->id)->first();

        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->withHeader('X-Platform-Id', (string) $this->betBrasil->id)
            ->getJson("/api/v1/players/{$player->id}/360");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'external_id',
                    'status',
                    'personal_data' => ['name', 'cpf', 'birth_date', 'gender'],
                    'contact_data' => ['email', 'phone', 'whatsapp', 'city', 'state'],
                    'platform' => ['id', 'name', 'slug'],
                    'acquisition' => ['source', 'affiliate', 'promo_code', 'registered_at', 'created_at'],
                    'activity' => ['last_login_at', 'days_since_creation'],
                    'tags',
                    'custom_fields',
                    'consents',
                    'timeline',
                ],
            ]);
    }

    /**
     * 25. Proteção contra Acesso Direto a Jogador de Outra Plataforma
     */
    public function test_cannot_access_player_from_unauthorized_platform_directly(): void
    {
        // Jogador da Bet Global
        $playerGlobal = Player::withoutGlobalScopes()->where('platform_id', $this->betGlobal->id)->first();

        // Admin da Bet Brasil tentando acessar jogador da Bet Global
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->withHeader('X-Platform-Id', (string) $this->betBrasil->id)
            ->getJson("/api/v1/players/{$playerGlobal->id}");

        // Due to PlatformScope, the record is not found in the tenant's scope (404)
        $response->assertStatus(404);
    }
}
