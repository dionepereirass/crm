<?php

namespace Tests\Feature;

use App\Enums\AutomationNodeType;
use App\Enums\AutomationRunStatus;
use App\Enums\AutomationStatus;
use App\Enums\AutomationTriggerType;
use App\Jobs\CreateCampaignMessagesJob;
use App\Jobs\DispatchCampaignJob;
use App\Models\Automation;
use App\Models\AutomationEdge;
use App\Models\AutomationNode;
use App\Models\AutomationRun;
use App\Models\Campaign;
use App\Models\Consent;
use App\Models\Message;
use App\Models\MessageEvent;
use App\Models\MessageLink;
use App\Models\Platform;
use App\Models\Player;
use App\Models\Provider;
use App\Models\Segment;
use App\Models\Template;
use App\Models\TemplateVersion;
use App\Models\User;
use App\Services\Automations\AutomationTriggerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FinalHomologationE2ETest extends TestCase
{
    use RefreshDatabase;

    protected Platform $betBrasil;
    protected Platform $betGlobal;

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

        $this->betBrasil = Platform::where('slug', 'bet-brasil')->firstOrFail();
        $this->betGlobal = Platform::where('slug', 'bet-global')->firstOrFail();

        $this->superAdmin = User::where('email', 'superadmin@crm.example.com')->firstOrFail();
        $this->superAdminToken = $this->superAdmin->createToken('super_e2e')->plainTextToken;

        $this->adminUser = User::where('email', 'admin@crm.example.com')->firstOrFail();
        $this->adminToken = $this->adminUser->createToken('admin_e2e')->plainTextToken;

        $this->marketingUser = User::where('email', 'marketing@crm.example.com')->firstOrFail();
        $this->marketingToken = $this->marketingUser->createToken('marketing_e2e')->plainTextToken;

        $this->supportUser = User::where('email', 'support@crm.example.com')->firstOrFail();
        $this->supportToken = $this->supportUser->createToken('support_e2e')->plainTextToken;

        $this->analystUser = User::where('email', 'analyst@crm.example.com')->firstOrFail();
        $this->analystToken = $this->analystUser->createToken('analyst_e2e')->plainTextToken;
    }

    /**
     * E2E Flow 1: Complete Core Business Flow
     * Platform -> Player -> Event -> Segment -> Template -> Provider -> Campaign -> Message -> Tracking -> Analytics
     */
    public function test_e2e_full_business_flow_from_platform_to_analytics(): void
    {
        // 1. Criar Jogador na Bet Brasil
        $player = Player::create([
            'platform_id' => $this->betBrasil->id,
            'external_id' => 'e2e_player_777',
            'name' => 'Roberto Carlos',
            'email' => 'roberto.carlos@homologacao.test',
            'phone' => '5511988887777',
            'status' => 'ACTIVE',
            'balance' => 350.00,
            'total_deposits' => 500.00,
        ]);

        // Registrar Consentimento LGPD
        Consent::create([
            'platform_id' => $this->betBrasil->id,
            'player_id' => $player->id,
            'channel' => 'EMAIL',
            'is_granted' => true,
            'consent_date' => now(),
        ]);

        // 2. Segmentação Dinâmica (Jogadores Ativos)
        $segment = Segment::create([
            'uuid' => (string) Str::uuid(),
            'platform_id' => $this->betBrasil->id,
            'name' => 'VIPs Ativos Homologados',
            'slug' => 'vips-ativos-homologados',
            'status' => 'ACTIVE',
            'rules_tree' => [
                'operator' => 'AND',
                'children' => [
                    [
                        'field' => 'player.status',
                        'operator' => 'equals',
                        'value' => 'ACTIVE',
                    ]
                ],
            ],
            'cached_count' => 1,
        ]);

        // 3. Template com variáveis personalizadas
        $template = Template::create([
            'uuid' => (string) Str::uuid(),
            'platform_id' => $this->betBrasil->id,
            'name' => 'Template VIP Promocional',
            'slug' => 'template-vip-promocional',
            'channel' => 'EMAIL',
            'status' => 'ACTIVE',
        ]);

        $templateVersion = TemplateVersion::create([
            'uuid' => (string) Str::uuid(),
            'template_id' => $template->id,
            'version' => 1,
            'status' => 'PUBLISHED',
            'subject' => 'Oferta Exclusiva para {{name}}',
            'html_content' => '<p>Olá {{name}}, seu saldo atual é {{balance}}!</p><a href="https://betbrasil.com/promo">Resgatar</a>',
            'text_content' => 'Olá {{name}}, acesse suas vantagens.',
            'published_at' => now(),
        ]);

        // 4. Provider de Envio de Email
        $provider = Provider::where('platform_id', $this->betBrasil->id)
            ->where('channel', 'EMAIL')
            ->first() ?? Provider::create([
                'uuid' => (string) Str::uuid(),
                'platform_id' => $this->betBrasil->id,
                'name' => 'Provider Email Homologacao',
                'channel' => 'EMAIL',
                'driver' => 'fake_email',
                'status' => 'ACTIVE',
                'is_default' => true,
                'priority' => 1,
                'rate_limit_per_minute' => 600,
            ]);

        // 5. Criação da Campanha
        $campaignPayload = [
            'name' => 'Campanha Homologação E2E',
            'description' => 'Disparo validando ciclo completo de mensageria',
            'channel' => 'EMAIL',
            'segment_id' => $segment->id,
            'template_id' => $template->id,
            'template_version_id' => $templateVersion->id,
            'provider_id' => $provider->id,
            'from_name' => 'Equipe VIP',
            'from_email' => 'vip@betbrasil.com',
        ];

        $createCampaignResponse = $this->withHeader('Authorization', "Bearer {$this->marketingToken}")
            ->withHeader('X-Platform-Id', (string) $this->betBrasil->id)
            ->postJson('/api/v1/campaigns', $campaignPayload);

        $createCampaignResponse->assertStatus(201);
        $campaignId = $createCampaignResponse->json('data.id');
        $campaign = Campaign::findOrFail($campaignId);
        $campaign->update(['status' => 'READY']);

        // 6. Lançamento da Campanha e Execução de Jobs
        $launchResponse = $this->withHeader('Authorization', "Bearer {$this->marketingToken}")
            ->withHeader('X-Platform-Id', (string) $this->betBrasil->id)
            ->postJson("/api/v1/campaigns/{$campaign->id}/launch", [
                'confirmation' => true,
            ]);
        $launchResponse->assertStatus(200);

        $dispatchJob = new DispatchCampaignJob($campaign->id);
        app()->call([$dispatchJob, 'handle']);

        $messagesJob = new CreateCampaignMessagesJob($campaign->id);
        app()->call([$messagesJob, 'handle']);

        $campaign->refresh();
        $this->assertEquals('COMPLETED', $campaign->status);

        $message = Message::where('campaign_id', $campaign->id)
            ->where('player_id', $player->id)
            ->firstOrFail();

        $this->assertNotNull($message);
        $this->assertContains($message->status, ['SENT', 'DELIVERED', 'QUEUED']);

        // 7. Simular Tracking: Abertura via Pixel 1x1
        $openToken = $message->open_tracking_token ?? Str::random(48);
        if (!$message->open_tracking_token) {
            $message->update(['open_tracking_token' => $openToken]);
        }

        $trackingResponse = $this->get("/api/v1/tracking/open/{$openToken}");
        $trackingResponse->assertStatus(200);
        $trackingResponse->assertHeader('Content-Type', 'image/gif');

        $message->refresh();
        $this->assertNotNull($message->opened_at);

        // 8. Simular Tracking: Clique em Link via MessageLink
        $clickToken = Str::random(48);
        $destinationUrl = 'https://betbrasil.com/promo';
        $link = MessageLink::create([
            'message_id' => $message->id,
            'tracking_token' => $clickToken,
            'destination_url' => $destinationUrl,
        ]);

        $clickResponse = $this->get("/api/v1/tracking/click/{$clickToken}");
        $clickResponse->assertStatus(302);
        $clickResponse->assertRedirect($destinationUrl);

        $link->refresh();
        $this->assertEquals(1, $link->clicks_count);

        // 9. Validar Atualização do Analytics da Campanha
        $analyticsResponse = $this->withHeader('Authorization', "Bearer {$this->analystToken}")
            ->withHeader('X-Platform-Id', (string) $this->betBrasil->id)
            ->getJson("/api/v1/campaigns/{$campaign->id}/analytics");

        $analyticsResponse->assertStatus(200);
        $analyticsResponse->assertJsonPath('success', true);
        $this->assertGreaterThanOrEqual(1, $analyticsResponse->json('data.metrics.opened'));
    }

    /**
     * E2E Flow 2: Complete Automation Engine Journey
     * Player -> Trigger -> Condition -> Action -> Tag & Execution
     */
    public function test_e2e_automation_journey_flow_with_trigger_condition_and_dispatch(): void
    {
        // 1. Criar Jogador
        $player = Player::create([
            'platform_id' => $this->betBrasil->id,
            'external_id' => 'e2e_auto_player_1',
            'name' => 'Juliana Paes',
            'email' => 'juliana.paes@homologacao.test',
            'status' => 'ACTIVE',
            'balance' => 200.00,
        ]);

        // 2. Criar Automação Ativa
        $automation = Automation::create([
            'platform_id' => $this->betBrasil->id,
            'name' => 'Jornada Boas Vindas Primeiro Deposito',
            'status' => AutomationStatus::ACTIVE,
            'trigger_type' => AutomationTriggerType::PLAYER_CREATED,
        ]);

        // Nó Trigger
        $nodeTrigger = AutomationNode::create([
            'automation_id' => $automation->id,
            'node_key' => 'trigger-1',
            'node_type' => AutomationNodeType::TRIGGER,
            'name' => 'Gatilho Cadastro',
            'configuration' => ['trigger_type' => 'PLAYER_CREATED'],
        ]);

        // Nó Condição (Status Ativo)
        $nodeCondition = AutomationNode::create([
            'automation_id' => $automation->id,
            'node_key' => 'cond-1',
            'node_type' => AutomationNodeType::CONDITION,
            'name' => 'Verifica Status Ativo',
            'configuration' => [
                'field' => 'player.status',
                'operator' => '=',
                'value' => 'ACTIVE',
            ],
        ]);

        // Nó Ação (Adicionar Tag VIP)
        $nodeAction = AutomationNode::create([
            'automation_id' => $automation->id,
            'node_key' => 'act-tag-1',
            'node_type' => AutomationNodeType::ACTION,
            'name' => 'Atribuir Tag VIP Automatica',
            'configuration' => [
                'action_type' => 'ADD_TAG',
                'tag_name' => 'VIP Homologado',
            ],
        ]);

        // Conectar Arestas (Trigger -> Condition -> Action)
        AutomationEdge::create([
            'automation_id' => $automation->id,
            'source_node_id' => $nodeTrigger->id,
            'target_node_id' => $nodeCondition->id,
        ]);

        AutomationEdge::create([
            'automation_id' => $automation->id,
            'source_node_id' => $nodeCondition->id,
            'target_node_id' => $nodeAction->id,
            'condition_key' => 'true',
        ]);

        // 3. Iniciar Execução da Jornada via Trigger Service (executa sincronicamente no ambiente de teste)
        $triggerService = app(AutomationTriggerService::class);
        $dispatched = $triggerService->dispatchForTrigger(
            $this->betBrasil->id,
            $player->id,
            AutomationTriggerType::PLAYER_CREATED->value,
            'event-e2e-homologation-999'
        );

        $this->assertEquals(1, $dispatched);

        $run = AutomationRun::where('automation_id', $automation->id)->firstOrFail();
        $this->assertEquals(AutomationRunStatus::COMPLETED, $run->status);
        $this->assertNotNull($run->completed_at);

        // Valida que a tag foi atribuída ao jogador pela ação da jornada
        $this->assertTrue($player->tags()->where('name', 'VIP Homologado')->exists());
    }

    /**
     * E2E Flow 3: Multi-Tenant Strict Cross-Isolation
     * Tenant A (Bet Brasil) vs Tenant B (Bet Global)
     */
    public function test_e2e_multi_tenant_strict_cross_isolation(): void
    {
        // 1. Recursos na Bet Brasil (Tenant A)
        $playerBrasil = Player::create([
            'platform_id' => $this->betBrasil->id,
            'external_id' => 'p_brasil_01',
            'name' => 'Jogador Brasil',
            'email' => 'brasil@tenant-a.com',
            'status' => 'ACTIVE',
        ]);

        $segmentBrasil = Segment::create([
            'uuid' => (string) Str::uuid(),
            'platform_id' => $this->betBrasil->id,
            'name' => 'Segmento Brasil Privado',
            'slug' => 'segmento-brasil-privado',
            'status' => 'ACTIVE',
            'rules_tree' => ['operator' => 'AND', 'children' => []],
        ]);

        // 2. Recursos na Bet Global (Tenant B)
        $playerGlobal = Player::create([
            'platform_id' => $this->betGlobal->id,
            'external_id' => 'p_global_01',
            'name' => 'Player Global International',
            'email' => 'global@tenant-b.com',
            'status' => 'ACTIVE',
        ]);

        $segmentGlobal = Segment::create([
            'uuid' => (string) Str::uuid(),
            'platform_id' => $this->betGlobal->id,
            'name' => 'Global Segment International',
            'slug' => 'global-segment-international',
            'status' => 'ACTIVE',
            'rules_tree' => ['operator' => 'AND', 'children' => []],
        ]);

        // 3. Tenant A (Bet Brasil) tentando acessar recursos do Tenant B (Bet Global)
        $leakPlayerResponse = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->withHeader('X-Platform-Id', (string) $this->betBrasil->id)
            ->getJson("/api/v1/players/{$playerGlobal->id}");

        $this->assertContains($leakPlayerResponse->status(), [404, 403]);

        $leakSegmentResponse = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->withHeader('X-Platform-Id', (string) $this->betBrasil->id)
            ->getJson("/api/v1/segments/{$segmentGlobal->id}");

        $this->assertContains($leakSegmentResponse->status(), [404, 403]);

        // 4. Criação de Usuário e Token isolado para o Tenant B (Bet Global)
        $adminGlobal = User::create([
            'name' => 'Global Admin',
            'email' => 'admin.global@crm.example.com',
            'password' => bcrypt('Secret@123456'),
            'status' => 'ACTIVE',
        ]);
        $adminGlobal->assignRole('ADMIN');
        $adminGlobal->platforms()->syncWithoutDetaching([$this->betGlobal->id]);
        $adminGlobalToken = $adminGlobal->createToken('global_token')->plainTextToken;

        // Tenant B tentando acessar recursos do Tenant A
        $leakFromGlobalResponse = $this->withHeader('Authorization', "Bearer {$adminGlobalToken}")
            ->withHeader('X-Platform-Id', (string) $this->betGlobal->id)
            ->getJson("/api/v1/players/{$playerBrasil->id}");

        $this->assertContains($leakFromGlobalResponse->status(), [404, 403]);

        $leakSegmentFromGlobal = $this->withHeader('Authorization', "Bearer {$adminGlobalToken}")
            ->withHeader('X-Platform-Id', (string) $this->betGlobal->id)
            ->getJson("/api/v1/segments/{$segmentBrasil->id}");

        $this->assertContains($leakSegmentFromGlobal->status(), [404, 403]);
    }

    /**
     * E2E Flow 4: RBAC Matrix Verification across All 5 Roles
     * SUPER_ADMIN, ADMIN, MARKETING, SUPPORT, ANALYST
     */
    public function test_e2e_rbac_matrix_permissions_across_all_roles(): void
    {
        $testPlayer = Player::create([
            'platform_id' => $this->betBrasil->id,
            'external_id' => 'p_rbac_test_01',
            'name' => 'Player RBAC Guard',
            'email' => 'rbac.guard@test.com',
            'status' => 'ACTIVE',
        ]);

        // 1. SUPER_ADMIN: Pode visualizar dados de auditoria e governança
        $superAuditResponse = $this->withHeader('Authorization', "Bearer {$this->superAdminToken}")
            ->withHeader('X-Platform-Id', (string) $this->betBrasil->id)
            ->getJson('/api/v1/privacy/audit');
        $superAuditResponse->assertStatus(200);

        app('auth')->forgetGuards();

        // 2. ADMIN: Tem acesso administrativo aos painéis da sua plataforma
        $adminOverviewResponse = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->withHeader('X-Platform-Id', (string) $this->betBrasil->id)
            ->getJson('/api/v1/analytics/overview');
        $adminOverviewResponse->assertStatus(200);

        app('auth')->forgetGuards();

        // 3. MARKETING: Pode visualizar/criar campanhas, mas NÃO pode anonimizar jogadores (exclusivo ADMIN/DPO)
        $marketingCampaigns = $this->withHeader('Authorization', "Bearer {$this->marketingToken}")
            ->withHeader('X-Platform-Id', (string) $this->betBrasil->id)
            ->getJson('/api/v1/campaigns');
        $marketingCampaigns->assertStatus(200);

        $marketingForbiddenAnonymize = $this->withHeader('Authorization', "Bearer {$this->marketingToken}")
            ->withHeader('X-Platform-Id', (string) $this->betBrasil->id)
            ->postJson("/api/v1/privacy/players/{$testPlayer->id}/anonymize", [
                'confirmed' => true,
            ]);
        $marketingForbiddenAnonymize->assertStatus(403);

        app('auth')->forgetGuards();

        // 4. SUPPORT: Pode visualizar jogadores, mas NÃO pode criar campanhas
        $supportPlayers = $this->withHeader('Authorization', "Bearer {$this->supportToken}")
            ->withHeader('X-Platform-Id', (string) $this->betBrasil->id)
            ->getJson('/api/v1/players');
        $supportPlayers->assertStatus(200);

        $supportForbiddenCampaign = $this->withHeader('Authorization', "Bearer {$this->supportToken}")
            ->withHeader('X-Platform-Id', (string) $this->betBrasil->id)
            ->postJson('/api/v1/campaigns', [
                'name' => 'Campanha Ilegal Suporte',
                'channel' => 'EMAIL',
            ]);
        $this->assertEquals(403, $supportForbiddenCampaign->status());

        app('auth')->forgetGuards();

        // 5. ANALYST: Pode consultar Relatórios e Analytics, mas NÃO pode criar campanhas
        $analystReports = $this->withHeader('Authorization', "Bearer {$this->analystToken}")
            ->withHeader('X-Platform-Id', (string) $this->betBrasil->id)
            ->getJson('/api/v1/analytics/overview');
        $analystReports->assertStatus(200);

        $analystForbiddenCampaign = $this->withHeader('Authorization', "Bearer {$this->analystToken}")
            ->withHeader('X-Platform-Id', (string) $this->betBrasil->id)
            ->postJson('/api/v1/campaigns', [
                'name' => 'Campanha Ilegal Analista',
                'channel' => 'EMAIL',
            ]);
        $this->assertEquals(403, $analystForbiddenCampaign->status());
    }
}
