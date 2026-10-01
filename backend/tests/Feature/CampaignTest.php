<?php

namespace Tests\Feature;

use App\Jobs\CreateCampaignMessagesJob;
use App\Jobs\DispatchCampaignJob;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Consent;
use App\Models\Message;
use App\Models\Platform;
use App\Models\Player;
use App\Models\Provider;
use App\Models\Segment;
use App\Models\Template;
use App\Models\TemplateVersion;
use App\Models\User;
use App\Services\Campaigns\CampaignService;
use App\Services\Messaging\MessageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Tests\TestCase;

class CampaignTest extends TestCase
{
    use RefreshDatabase;

    protected Platform $betBrasil;
    protected Platform $betGlobal;

    protected User $adminUser;
    protected User $marketingUser;
    protected User $supportUser;
    protected User $analystUser;

    protected string $adminToken;
    protected string $marketingToken;
    protected string $supportToken;
    protected string $analystToken;

    protected Segment $vipSegment;
    protected Template $welcomeTemplate;
    protected TemplateVersion $welcomeVersion;
    protected Provider $fakeEmailProvider;
    protected Provider $fakeSmsProvider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);

        $this->betBrasil = Platform::where('slug', 'bet-brasil')->firstOrFail();
        $this->betGlobal = Platform::where('slug', 'bet-global')->firstOrFail();

        $this->adminUser = User::where('email', 'admin@crm.example.com')->firstOrFail();
        $this->adminToken = $this->adminUser->createToken('admin_test')->plainTextToken;

        $this->marketingUser = User::where('email', 'marketing@crm.example.com')->firstOrFail();
        $this->marketingToken = $this->marketingUser->createToken('marketing_test')->plainTextToken;

        $this->supportUser = User::where('email', 'support@crm.example.com')->firstOrFail();
        $this->supportToken = $this->supportUser->createToken('support_test')->plainTextToken;

        $this->analystUser = User::where('email', 'analyst@crm.example.com')->firstOrFail();
        $this->analystToken = $this->analystUser->createToken('analyst_test')->plainTextToken;

        // 1. Provedores
        $this->fakeEmailProvider = Provider::create([
            'uuid' => (string) Str::uuid(),
            'platform_id' => $this->betBrasil->id,
            'name' => 'Fake Email Dev',
            'channel' => 'EMAIL',
            'driver' => 'fake_email',
            'status' => 'ACTIVE',
            'is_default' => true,
            'priority' => 1,
            'rate_limit_per_minute' => 1000,
        ]);

        $this->fakeSmsProvider = Provider::create([
            'uuid' => (string) Str::uuid(),
            'platform_id' => $this->betBrasil->id,
            'name' => 'Fake SMS Dev',
            'channel' => 'SMS',
            'driver' => 'fake_sms',
            'status' => 'ACTIVE',
            'is_default' => true,
            'priority' => 1,
            'rate_limit_per_minute' => 1000,
        ]);

        // 2. Segmento
        $this->vipSegment = Segment::create([
            'uuid' => (string) Str::uuid(),
            'platform_id' => $this->betBrasil->id,
            'name' => 'Jogadores Ativos',
            'slug' => 'jogadores-ativos-' . Str::random(5),
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
            'cached_count' => 5,
        ]);

        // 3. Template
        $this->welcomeTemplate = Template::create([
            'uuid' => (string) Str::uuid(),
            'platform_id' => $this->betBrasil->id,
            'name' => 'Boas-vindas VIP',
            'slug' => 'boas-vindas-vip-' . Str::random(5),
            'channel' => 'EMAIL',
            'category' => 'ONBOARDING',
            'status' => 'ACTIVE',
        ]);

        $this->welcomeVersion = TemplateVersion::create([
            'uuid' => (string) Str::uuid(),
            'template_id' => $this->welcomeTemplate->id,
            'version' => 1,
            'status' => 'PUBLISHED',
            'subject' => 'Olá {{player.first_name}}, bem-vindo à {{platform.name}}!',
            'preheader' => 'Aproveite sua bonificação exclusiva',
            'html_content' => '<p>Olá {{player.first_name}}, sua conta {{player.external_id}} está ativa.</p>',
            'text_content' => 'Olá {{player.first_name}}, sua conta está ativa.',
            'variables_schema' => [
                ['key' => 'player.first_name'],
                ['key' => 'player.external_id'],
                ['key' => 'platform.name'],
            ],
        ]);

        $this->welcomeTemplate->update(['current_version_id' => $this->welcomeVersion->id]);
    }

    /**
     * 1. CRUD de Campanhas
     */
    public function test_user_can_create_and_view_campaign(): void
    {
        $payload = [
            'name' => 'Campanha Black Friday',
            'description' => 'Disparo especial para jogadores ativos',
            'channel' => 'EMAIL',
            'segment_id' => $this->vipSegment->id,
            'template_id' => $this->welcomeTemplate->id,
            'template_version_id' => $this->welcomeVersion->id,
            'provider_id' => $this->fakeEmailProvider->id,
            'from_name' => 'Bet Brasil Notificações',
            'from_email' => 'promocoes@betbrasil.com',
        ];

        $response = $this->withHeader('Authorization', "Bearer {$this->marketingToken}")
            ->withHeader('X-Platform-Id', (string) $this->betBrasil->id)
            ->postJson('/api/v1/campaigns', $payload);

        $response->assertStatus(201);
        $campaignId = $response->json('data.id');

        $this->assertDatabaseHas('campaigns', [
            'id' => $campaignId,
            'name' => 'Campanha Black Friday',
            'status' => 'DRAFT',
            'channel' => 'EMAIL',
        ]);

        // Visualização
        $viewResponse = $this->withHeader('Authorization', "Bearer {$this->marketingToken}")
            ->withHeader('X-Platform-Id', (string) $this->betBrasil->id)
            ->getJson("/api/v1/campaigns/{$campaignId}");

        $viewResponse->assertStatus(200);
        $this->assertEquals('Campanha Black Friday', $viewResponse->json('data.name'));
        $this->assertNotNull($viewResponse->json('data.segment'));
        $this->assertNotNull($viewResponse->json('data.template'));
    }

    public function test_user_can_update_and_delete_campaign(): void
    {
        $campaign = Campaign::create([
            'uuid' => (string) Str::uuid(),
            'platform_id' => $this->betBrasil->id,
            'name' => 'Campanha Rascunho',
            'channel' => 'EMAIL',
            'status' => 'DRAFT',
            'segment_id' => $this->vipSegment->id,
            'template_id' => $this->welcomeTemplate->id,
            'template_version_id' => $this->welcomeVersion->id,
        ]);

        $updateResponse = $this->withHeader('Authorization', "Bearer {$this->marketingToken}")
            ->withHeader('X-Platform-Id', (string) $this->betBrasil->id)
            ->putJson("/api/v1/campaigns/{$campaign->id}", [
                'name' => 'Campanha Rascunho Atualizada',
            ]);

        $updateResponse->assertStatus(200);
        $this->assertEquals('Campanha Rascunho Atualizada', $updateResponse->json('data.name'));

        // Delete
        $delResponse = $this->withHeader('Authorization', "Bearer {$this->marketingToken}")
            ->withHeader('X-Platform-Id', (string) $this->betBrasil->id)
            ->deleteJson("/api/v1/campaigns/{$campaign->id}");

        $delResponse->assertStatus(200);
        $this->assertSoftDeleted('campaigns', ['id' => $campaign->id]);
    }

    /**
     * 2. Isolamento Multi-Plataforma
     */
    public function test_platform_isolation_prevents_access_to_other_platform_campaign(): void
    {
        $globalSegment = Segment::create([
            'uuid' => (string) Str::uuid(),
            'platform_id' => $this->betGlobal->id,
            'name' => 'Global Segment',
            'slug' => 'global-segment',
            'status' => 'ACTIVE',
            'rules_tree' => ['operator' => 'AND', 'children' => []],
        ]);

        $globalTemplate = Template::create([
            'uuid' => (string) Str::uuid(),
            'platform_id' => $this->betGlobal->id,
            'name' => 'Global Template',
            'slug' => 'global-template',
            'channel' => 'EMAIL',
            'status' => 'ACTIVE',
        ]);

        $globalVersion = TemplateVersion::create([
            'uuid' => (string) Str::uuid(),
            'template_id' => $globalTemplate->id,
            'version' => 1,
            'status' => 'PUBLISHED',
            'subject' => 'Global Welcome',
        ]);

        $globalCampaign = Campaign::create([
            'uuid' => (string) Str::uuid(),
            'platform_id' => $this->betGlobal->id,
            'name' => 'Campanha Global',
            'channel' => 'EMAIL',
            'status' => 'DRAFT',
            'segment_id' => $globalSegment->id,
            'template_id' => $globalTemplate->id,
            'template_version_id' => $globalVersion->id,
        ]);

        // Tentativa de acesso com token e header da Bet Brasil
        $response = $this->withHeader('Authorization', "Bearer {$this->marketingToken}")
            ->withHeader('X-Platform-Id', (string) $this->betBrasil->id)
            ->getJson("/api/v1/campaigns/{$globalCampaign->id}");

        $response->assertStatus(404);

        // Tentativa de criar campanha na Bet Brasil usando segmento da Bet Global deve falhar
        $badCreate = $this->withHeader('Authorization', "Bearer {$this->marketingToken}")
            ->withHeader('X-Platform-Id', (string) $this->betBrasil->id)
            ->postJson('/api/v1/campaigns', [
                'name' => 'Invasão',
                'channel' => 'EMAIL',
                'segment_id' => $globalSegment->id,
                'template_id' => $this->welcomeTemplate->id,
                'template_version_id' => $this->welcomeVersion->id,
            ]);

        $this->assertContains($badCreate->status(), [404, 422]);
    }

    /**
     * 3. Validação pré-disparo da campanha
     */
    public function test_campaign_validation_and_readiness(): void
    {
        // Cria campanha em DRAFT
        $campaign = Campaign::create([
            'uuid' => (string) Str::uuid(),
            'platform_id' => $this->betBrasil->id,
            'name' => 'Campanha Pronta',
            'channel' => 'EMAIL',
            'status' => 'DRAFT',
            'segment_id' => $this->vipSegment->id,
            'template_id' => $this->welcomeTemplate->id,
            'template_version_id' => $this->welcomeVersion->id,
            'provider_id' => $this->fakeEmailProvider->id,
        ]);

        $valResponse = $this->withHeader('Authorization', "Bearer {$this->marketingToken}")
            ->withHeader('X-Platform-Id', (string) $this->betBrasil->id)
            ->postJson("/api/v1/campaigns/{$campaign->id}/validate");

        $valResponse->assertStatus(200);
        $this->assertTrue($valResponse->json('data.is_valid'));

        // Status deve ter mudado para READY
        $campaign->refresh();
        $this->assertEquals('READY', $campaign->status);
    }

    public function test_validation_fails_on_channel_mismatch(): void
    {
        // Campanha SMS usando template EMAIL
        $campaign = Campaign::create([
            'uuid' => (string) Str::uuid(),
            'platform_id' => $this->betBrasil->id,
            'name' => 'Incompatível',
            'channel' => 'SMS',
            'status' => 'DRAFT',
            'segment_id' => $this->vipSegment->id,
            'template_id' => $this->welcomeTemplate->id, // template é EMAIL!
            'template_version_id' => $this->welcomeVersion->id,
            'provider_id' => $this->fakeSmsProvider->id,
        ]);

        $valResponse = $this->withHeader('Authorization', "Bearer {$this->marketingToken}")
            ->withHeader('X-Platform-Id', (string) $this->betBrasil->id)
            ->postJson("/api/v1/campaigns/{$campaign->id}/validate");

        $valResponse->assertStatus(200);
        $this->assertFalse($valResponse->json('data.is_valid'));
        $this->assertNotEmpty($valResponse->json('data.errors'));
    }

    /**
     * 4. Respeito obrigatório a Consentimento LGPD (Marketing Consent)
     */
    public function test_launch_skips_players_without_marketing_consent(): void
    {
        // Cria dois jogadores no segmento: um com consentimento e outro sem
        $playerWithConsent = Player::create([
            'platform_id' => $this->betBrasil->id,
            'external_id' => 'PLY-CONSENT-1',
            'name' => 'Consentido Silva',
            'email' => 'consentido@betcrm.com',
            'status' => 'ACTIVE',
        ]);
        Consent::create([
            'player_id' => $playerWithConsent->id,
            'channel' => 'EMAIL',
            'is_granted' => true,
            'consent_date' => now(),
        ]);

        $playerWithoutConsent = Player::create([
            'platform_id' => $this->betBrasil->id,
            'external_id' => 'PLY-NOCONSENT-2',
            'name' => 'Sem Consentimento Souza',
            'email' => 'semconsentimento@betcrm.com',
            'status' => 'ACTIVE',
        ]);
        Consent::create([
            'player_id' => $playerWithoutConsent->id,
            'channel' => 'EMAIL',
            'is_granted' => false, // REVOGADO / NÃO AUTORIZADO
            'consent_date' => now(),
        ]);

        $campaign = Campaign::create([
            'uuid' => (string) Str::uuid(),
            'platform_id' => $this->betBrasil->id,
            'name' => 'Campanha Consentimento LGPD',
            'channel' => 'EMAIL',
            'status' => 'READY',
            'segment_id' => $this->vipSegment->id,
            'template_id' => $this->welcomeTemplate->id,
            'template_version_id' => $this->welcomeVersion->id,
            'provider_id' => $this->fakeEmailProvider->id,
        ]);

        // Executa o job de dispatch manualmente para inspecionar os recipients
        $job = new DispatchCampaignJob($campaign->id);
        $campaign->update(['status' => 'PROCESSING']);
        app()->call([$job, 'handle']);

        // Verifica recipient do jogador COM consentimento
        $recWith = CampaignRecipient::where('campaign_id', $campaign->id)
            ->where('player_id', $playerWithConsent->id)
            ->first();
        $this->assertNotNull($recWith);
        $this->assertContains($recWith->status, ['PENDING', 'QUEUED']);

        // Verifica recipient do jogador SEM consentimento
        $recWithout = CampaignRecipient::where('campaign_id', $campaign->id)
            ->where('player_id', $playerWithoutConsent->id)
            ->first();
        $this->assertNotNull($recWithout);
        $this->assertEquals('SKIPPED', $recWithout->status);
        $this->assertEquals('MARKETING_CONSENT_REQUIRED', $recWithout->reason);
    }

    /**
     * 5. Imutabilidade do Snapshot de Audiência
     */
    public function test_audience_snapshot_remains_immutable_after_launch(): void
    {
        $campaign = Campaign::create([
            'uuid' => (string) Str::uuid(),
            'platform_id' => $this->betBrasil->id,
            'name' => 'Campanha Snapshot',
            'channel' => 'EMAIL',
            'status' => 'PROCESSING',
            'segment_id' => $this->vipSegment->id,
            'template_id' => $this->welcomeTemplate->id,
            'template_version_id' => $this->welcomeVersion->id,
            'provider_id' => $this->fakeEmailProvider->id,
        ]);

        // Executa preparação do snapshot
        $dispatchJob = new DispatchCampaignJob($campaign->id);
        app()->call([$dispatchJob, 'handle']);

        $recipientsCountBefore = CampaignRecipient::where('campaign_id', $campaign->id)->count();
        $this->assertGreaterThan(0, $recipientsCountBefore);

        // Altera regras do segmento ou adiciona novo jogador ao banco
        $newPlayer = Player::create([
            'platform_id' => $this->betBrasil->id,
            'external_id' => 'PLY-NEW-LATE',
            'name' => 'Atrasado da Silva',
            'email' => 'atrasado@betcrm.com',
            'status' => 'ACTIVE',
        ]);
        Consent::create([
            'player_id' => $newPlayer->id,
            'channel' => 'EMAIL',
            'is_granted' => true,
        ]);

        // Recalcular ou reconsultar recipients da campanha não deve alterar a contagem da execução iniciada
        $recipientsCountAfter = CampaignRecipient::where('campaign_id', $campaign->id)->count();
        $this->assertEquals($recipientsCountBefore, $recipientsCountAfter);
    }

    /**
     * 6. Deduplicação Estrita de Destinatários
     */
    public function test_strict_deduplication_prevents_duplicate_recipients(): void
    {
        $player = Player::where('platform_id', $this->betBrasil->id)->firstOrFail();

        $campaign = Campaign::create([
            'uuid' => (string) Str::uuid(),
            'platform_id' => $this->betBrasil->id,
            'name' => 'Campanha Deduplicação',
            'channel' => 'EMAIL',
            'status' => 'DRAFT',
            'segment_id' => $this->vipSegment->id,
            'template_id' => $this->welcomeTemplate->id,
            'template_version_id' => $this->welcomeVersion->id,
            'provider_id' => $this->fakeEmailProvider->id,
        ]);

        CampaignRecipient::create([
            'uuid' => (string) Str::uuid(),
            'campaign_id' => $campaign->id,
            'platform_id' => $this->betBrasil->id,
            'player_id' => $player->id,
            'channel' => 'EMAIL',
            'recipient' => $player->email,
            'status' => 'PENDING',
        ]);

        // Tentativa de duplicar deve ser barrada pela constraint do banco
        $this->expectException(\Illuminate\Database\QueryException::class);
        CampaignRecipient::create([
            'uuid' => (string) Str::uuid(),
            'campaign_id' => $campaign->id,
            'platform_id' => $this->betBrasil->id,
            'player_id' => $player->id,
            'channel' => 'EMAIL',
            'recipient' => $player->email,
            'status' => 'PENDING',
        ]);
    }

    /**
     * 7. Fluxo de Lançamento Ponta a Ponta com Fake Provider
     */
    public function test_full_launch_flow_creates_recipients_and_dispatches_messages(): void
    {
        $campaign = Campaign::create([
            'uuid' => (string) Str::uuid(),
            'platform_id' => $this->betBrasil->id,
            'name' => 'Campanha Completa',
            'channel' => 'EMAIL',
            'status' => 'READY',
            'segment_id' => $this->vipSegment->id,
            'template_id' => $this->welcomeTemplate->id,
            'template_version_id' => $this->welcomeVersion->id,
            'provider_id' => $this->fakeEmailProvider->id,
        ]);

        // Chamada da API para lançamento com confirmação
        $launchResponse = $this->withHeader('Authorization', "Bearer {$this->marketingToken}")
            ->withHeader('X-Platform-Id', (string) $this->betBrasil->id)
            ->postJson("/api/v1/campaigns/{$campaign->id}/launch", [
                'confirmation' => true,
            ]);

        $launchResponse->assertStatus(200);
        $campaign->refresh();
        $this->assertContains($campaign->status, ['PROCESSING', 'COMPLETED']);

        // Se a fila for assíncrona e ainda estiver PROCESSING, executa os jobs manualmente
        if ($campaign->status === 'PROCESSING') {
            $dispatchJob = new DispatchCampaignJob($campaign->id);
            app()->call([$dispatchJob, 'handle']);

            $messagesJob = new CreateCampaignMessagesJob($campaign->id);
            app()->call([$messagesJob, 'handle']);
            $campaign->refresh();
        }

        $this->assertEquals('COMPLETED', $campaign->status);
        $this->assertNotNull($campaign->completed_at);

        // Verifica que foram geradas mensagens associadas à campanha
        $messagesCount = Message::where('campaign_id', $campaign->id)->count();
        $this->assertGreaterThan(0, $messagesCount);

        // Verifica que as mensagens possuem o idempotency key correto
        $firstMessage = Message::where('campaign_id', $campaign->id)->first();
        $this->assertStringStartsWith("campaign:{$campaign->id}:player:", $firstMessage->idempotency_key);
    }

    /**
     * 8. Exigência de Confirmação Explícita para Lançamento
     */
    public function test_launch_requires_explicit_confirmation(): void
    {
        $campaign = Campaign::create([
            'uuid' => (string) Str::uuid(),
            'platform_id' => $this->betBrasil->id,
            'name' => 'Tentativa Sem Confirmação',
            'channel' => 'EMAIL',
            'status' => 'READY',
            'segment_id' => $this->vipSegment->id,
            'template_id' => $this->welcomeTemplate->id,
            'template_version_id' => $this->welcomeVersion->id,
            'provider_id' => $this->fakeEmailProvider->id,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->marketingToken}")
            ->withHeader('X-Platform-Id', (string) $this->betBrasil->id)
            ->postJson("/api/v1/campaigns/{$campaign->id}/launch", [
                'confirmation' => false,
            ]);

        $response->assertStatus(422);
        $campaign->refresh();
        $this->assertEquals('READY', $campaign->status);
    }

    /**
     * 9. Pausar, Retomar e Cancelar Campanha
     */
    public function test_pause_resume_and_cancel_lifecycle(): void
    {
        $campaign = Campaign::create([
            'uuid' => (string) Str::uuid(),
            'platform_id' => $this->betBrasil->id,
            'name' => 'Campanha Controlada',
            'channel' => 'EMAIL',
            'status' => 'PROCESSING',
            'segment_id' => $this->vipSegment->id,
            'template_id' => $this->welcomeTemplate->id,
            'template_version_id' => $this->welcomeVersion->id,
            'provider_id' => $this->fakeEmailProvider->id,
        ]);

        // Pausar
        $pauseRes = $this->withHeader('Authorization', "Bearer {$this->marketingToken}")
            ->withHeader('X-Platform-Id', (string) $this->betBrasil->id)
            ->postJson("/api/v1/campaigns/{$campaign->id}/pause");

        $pauseRes->assertStatus(200);
        $campaign->refresh();
        $this->assertEquals('PAUSED', $campaign->status);
        $this->assertNotNull($campaign->paused_at);

        // Retomar
        $resumeRes = $this->withHeader('Authorization', "Bearer {$this->marketingToken}")
            ->withHeader('X-Platform-Id', (string) $this->betBrasil->id)
            ->postJson("/api/v1/campaigns/{$campaign->id}/resume");

        $resumeRes->assertStatus(200);
        $campaign->refresh();
        $this->assertContains($campaign->status, ['PROCESSING', 'COMPLETED']);

        // Cancelar
        $campaignToCancel = Campaign::create([
            'uuid' => (string) Str::uuid(),
            'platform_id' => $this->betBrasil->id,
            'name' => 'Campanha para Cancelar',
            'channel' => 'EMAIL',
            'status' => 'READY',
            'segment_id' => $this->vipSegment->id,
            'template_id' => $this->welcomeTemplate->id,
            'template_version_id' => $this->welcomeVersion->id,
            'provider_id' => $this->fakeEmailProvider->id,
        ]);

        $cancelRes = $this->withHeader('Authorization', "Bearer {$this->marketingToken}")
            ->withHeader('X-Platform-Id', (string) $this->betBrasil->id)
            ->postJson("/api/v1/campaigns/{$campaignToCancel->id}/cancel");

        $cancelRes->assertStatus(200);
        $campaignToCancel->refresh();
        $this->assertEquals('CANCELLED', $campaignToCancel->status);
        $this->assertNotNull($campaignToCancel->cancelled_at);
    }

    /**
     * 10. Agendamento de Campanha (Scheduled At)
     */
    public function test_scheduled_campaign_sets_scheduled_status(): void
    {
        $futureDate = now()->addDays(2)->toIso8601String();

        $campaign = Campaign::create([
            'uuid' => (string) Str::uuid(),
            'platform_id' => $this->betBrasil->id,
            'name' => 'Campanha Futura',
            'channel' => 'EMAIL',
            'status' => 'READY',
            'segment_id' => $this->vipSegment->id,
            'template_id' => $this->welcomeTemplate->id,
            'template_version_id' => $this->welcomeVersion->id,
            'provider_id' => $this->fakeEmailProvider->id,
            'scheduled_at' => $futureDate,
        ]);

        $res = $this->withHeader('Authorization', "Bearer {$this->marketingToken}")
            ->withHeader('X-Platform-Id', (string) $this->betBrasil->id)
            ->postJson("/api/v1/campaigns/{$campaign->id}/launch", [
                'confirmation' => true,
            ]);

        $res->assertStatus(200);
        $campaign->refresh();
        $this->assertEquals('SCHEDULED', $campaign->status);
    }

    /**
     * 11. RBAC: Permissões por Papel
     */
    public function test_rbac_controls_campaign_operations(): void
    {
        $campaign = Campaign::create([
            'uuid' => (string) Str::uuid(),
            'platform_id' => $this->betBrasil->id,
            'name' => 'Campanha RBAC',
            'channel' => 'EMAIL',
            'status' => 'READY',
            'segment_id' => $this->vipSegment->id,
            'template_id' => $this->welcomeTemplate->id,
            'template_version_id' => $this->welcomeVersion->id,
            'provider_id' => $this->fakeEmailProvider->id,
        ]);

        // Support não pode disparar campanha
        $supportLaunch = $this->withHeader('Authorization', "Bearer {$this->supportToken}")
            ->withHeader('X-Platform-Id', (string) $this->betBrasil->id)
            ->postJson("/api/v1/campaigns/{$campaign->id}/launch", ['confirmation' => true]);
        $supportLaunch->assertStatus(403);

        // Support não pode excluir
        $supportDel = $this->withHeader('Authorization', "Bearer {$this->supportToken}")
            ->withHeader('X-Platform-Id', (string) $this->betBrasil->id)
            ->deleteJson("/api/v1/campaigns/{$campaign->id}");
        $supportDel->assertStatus(403);

        // Reset auth state para alternar usuário no Sanctum
        $this->app['auth']->forgetGuards();

        // Analyst pode visualizar estatísticas
        $analystStats = $this->withHeader('Authorization', "Bearer {$this->analystToken}")
            ->withHeader('X-Platform-Id', (string) $this->betBrasil->id)
            ->getJson("/api/v1/campaigns/{$campaign->id}/stats");
        $analystStats->assertStatus(200);

        // Analyst não pode lançar
        $analystLaunch = $this->withHeader('Authorization', "Bearer {$this->analystToken}")
            ->withHeader('X-Platform-Id', (string) $this->betBrasil->id)
            ->postJson("/api/v1/campaigns/{$campaign->id}/launch", ['confirmation' => true]);
        $analystLaunch->assertStatus(403);
    }

    /**
     * 12. Pré-visualização e Envio de Teste Administrativo
     */
    public function test_preview_and_test_send_endpoints(): void
    {
        $campaign = Campaign::create([
            'uuid' => (string) Str::uuid(),
            'platform_id' => $this->betBrasil->id,
            'name' => 'Campanha Preview Test',
            'channel' => 'EMAIL',
            'status' => 'DRAFT',
            'segment_id' => $this->vipSegment->id,
            'template_id' => $this->welcomeTemplate->id,
            'template_version_id' => $this->welcomeVersion->id,
            'provider_id' => $this->fakeEmailProvider->id,
        ]);

        // Preview
        $prevRes = $this->withHeader('Authorization', "Bearer {$this->marketingToken}")
            ->withHeader('X-Platform-Id', (string) $this->betBrasil->id)
            ->postJson("/api/v1/campaigns/{$campaign->id}/preview");

        $prevRes->assertStatus(200);
        $this->assertNotEmpty($prevRes->json('data.subject'));
        $this->assertNotEmpty($prevRes->json('data.html'));

        // Test Send
        $testRes = $this->withHeader('Authorization', "Bearer {$this->marketingToken}")
            ->withHeader('X-Platform-Id', (string) $this->betBrasil->id)
            ->postJson("/api/v1/campaigns/{$campaign->id}/test", [
                'recipient' => 'test_admin@crm.example.com',
            ]);

        $testRes->assertStatus(200);
        $this->assertTrue($testRes->json('success'));

        // O envio de teste não gera campaign_recipient real
        $this->assertEquals(0, CampaignRecipient::where('campaign_id', $campaign->id)->count());
    }

    /**
     * 13. Mascaramento LGPD de Destinatários na API de Destinatários
     */
    public function test_recipients_endpoint_masks_contacts_under_lgpd(): void
    {
        $campaign = Campaign::create([
            'uuid' => (string) Str::uuid(),
            'platform_id' => $this->betBrasil->id,
            'name' => 'Campanha LGPD',
            'channel' => 'EMAIL',
            'status' => 'COMPLETED',
            'segment_id' => $this->vipSegment->id,
            'template_id' => $this->welcomeTemplate->id,
            'template_version_id' => $this->welcomeVersion->id,
            'provider_id' => $this->fakeEmailProvider->id,
        ]);

        $player = Player::where('platform_id', $this->betBrasil->id)->firstOrFail();

        CampaignRecipient::create([
            'uuid' => (string) Str::uuid(),
            'campaign_id' => $campaign->id,
            'platform_id' => $this->betBrasil->id,
            'player_id' => $player->id,
            'channel' => 'EMAIL',
            'recipient' => 'jogador.secreto@gmail.com',
            'status' => 'SENT',
        ]);

        $res = $this->withHeader('Authorization', "Bearer {$this->marketingToken}")
            ->withHeader('X-Platform-Id', (string) $this->betBrasil->id)
            ->getJson("/api/v1/campaigns/{$campaign->id}/recipients");

        $res->assertStatus(200);
        $recipientData = $res->json('data.0.recipient');

        // Confirma mascaramento (não pode conter o e-mail completo)
        $this->assertNotEquals('jogador.secreto@gmail.com', $recipientData);
        $this->assertStringContainsString('***@gmail.com', $recipientData);
    }
}
