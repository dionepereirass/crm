<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\CampaignMetric;
use App\Models\CampaignRecipient;
use App\Models\Consent;
use App\Models\Message;
use App\Models\MessageEvent;
use App\Models\MessageLink;
use App\Models\Platform;
use App\Models\Player;
use App\Models\Provider;
use App\Models\Role;
use App\Models\Segment;
use App\Models\Template;
use App\Models\TemplateVersion;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Tests\TestCase;

class TrackingAndAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected Platform $platformA;
    protected Platform $platformB;
    protected User $marketingUser;
    protected User $analystUser;
    protected User $supportUser;
    protected Provider $emailProvider;
    protected Provider $smsProvider;
    protected Segment $segment;
    protected Template $emailTemplate;
    protected TemplateVersion $emailVersion;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        $this->platformA = Platform::create([
            'name' => 'Bet Alpha',
            'slug' => 'bet-alpha',
            'status' => 'ACTIVE',
        ]);

        $this->platformB = Platform::create([
            'name' => 'Bet Beta',
            'slug' => 'bet-beta',
            'status' => 'ACTIVE',
        ]);

        $marketingRole = Role::where('slug', 'MARKETING')->first();
        $analystRole = Role::where('slug', 'ANALYST')->first();
        $supportRole = Role::where('slug', 'SUPPORT')->first();

        $this->marketingUser = User::create([
            'name' => 'Marketing Specialist',
            'email' => 'marketing@betalpha.com',
            'password' => bcrypt('password123'),
            'role_id' => $marketingRole->id,
            'platform_id' => $this->platformA->id,
            'is_active' => true,
        ]);

        $this->analystUser = User::create([
            'name' => 'Data Analyst',
            'email' => 'analyst@betalpha.com',
            'password' => bcrypt('password123'),
            'role_id' => $analystRole->id,
            'platform_id' => $this->platformA->id,
            'is_active' => true,
        ]);

        $this->supportUser = User::create([
            'name' => 'Support Agent',
            'email' => 'support@betalpha.com',
            'password' => bcrypt('password123'),
            'role_id' => $supportRole->id,
            'platform_id' => $this->platformA->id,
            'is_active' => true,
        ]);

        $this->marketingUser->platforms()->attach($this->platformA->id);
        $this->analystUser->platforms()->attach($this->platformA->id);
        $this->supportUser->platforms()->attach($this->platformA->id);

        $this->marketingUser->roles()->attach($marketingRole->id);
        $this->analystUser->roles()->attach($analystRole->id);
        $this->supportUser->roles()->attach($supportRole->id);

        $this->emailProvider = Provider::create([
            'platform_id' => $this->platformA->id,
            'name' => 'Fake Email Service',
            'channel' => 'EMAIL',
            'driver' => 'fake_email',
            'status' => 'ACTIVE',
            'is_default' => true,
        ]);

        $this->smsProvider = Provider::create([
            'platform_id' => $this->platformA->id,
            'name' => 'Fake SMS Service',
            'channel' => 'SMS',
            'driver' => 'fake_sms',
            'status' => 'ACTIVE',
            'is_default' => true,
        ]);

        $this->segment = Segment::create([
            'platform_id' => $this->platformA->id,
            'name' => 'Segmento Teste',
            'status' => 'ACTIVE',
            'type' => 'DYNAMIC',
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
            'cached_count' => 10,
        ]);

        $this->emailTemplate = Template::create([
            'platform_id' => $this->platformA->id,
            'name' => 'Template E-mail Rastreavel',
            'channel' => 'EMAIL',
            'status' => 'ACTIVE',
        ]);

        $this->emailVersion = TemplateVersion::create([
            'template_id' => $this->emailTemplate->id,
            'version' => 1,
            'status' => 'PUBLISHED',
            'subject' => 'Oferta Especial {{player.name}}',
            'html_content' => '<p>Clique aqui: <a href="https://betalpha.com/promocao">Aproveitar Bonus</a></p>',
            'text_content' => 'Acesse https://betalpha.com/promocao',
        ]);
    }

    protected function headersFor(User $user, Platform $platform): array
    {
        $token = $user->createToken('test')->plainTextToken;
        return [
            'Authorization' => "Bearer {$token}",
            'X-Platform-ID' => (string) $platform->id,
            'Accept' => 'application/json',
        ];
    }

    /**
     * Teste 1: Webhook do provedor normaliza eventos de entrega e respeita idempotência.
     */
    public function test_provider_webhook_normalizes_events_and_enforces_idempotency(): void
    {
        $message = Message::create([
            'platform_id' => $this->platformA->id,
            'provider_id' => $this->emailProvider->id,
            'channel' => 'EMAIL',
            'recipient' => 'jogador@exemplo.com',
            'status' => 'SENT',
            'idempotency_key' => 'idemp_' . Str::uuid()->toString(),
            'provider_message_id' => 'brevo_msg_12345',
        ]);

        // Primeiro envio do webhook: DELIVERED
        $payload = [
            'event' => 'delivered',
            'message-id' => 'brevo_msg_12345',
            'event_id' => 'evt_brevo_99999',
            'date' => now()->toIso8601String(),
        ];

        $response1 = $this->postJson('/api/v1/providers/webhooks/brevo', $payload);
        $response1->assertStatus(200);

        $message->refresh();
        $this->assertEquals('DELIVERED', $message->status);

        $event = MessageEvent::where('provider_event_id', 'evt_brevo_99999')->first();
        $this->assertNotNull($event);
        $this->assertEquals('DELIVERED', $event->event_type);

        // Segundo envio do mesmo webhook (evento idêntico duplicado)
        $response2 = $this->postJson('/api/v1/providers/webhooks/brevo', $payload);
        $response2->assertStatus(200);

        // Não pode haver duplicação de eventos
        $eventCount = MessageEvent::where('provider_event_id', 'evt_brevo_99999')->count();
        $this->assertEquals(1, $eventCount);
    }

    /**
     * Teste 2: Precedência de status impede que evento atrasado de menor valor regrida status avançado.
     */
    public function test_status_precedence_prevents_downgrade(): void
    {
        $message = Message::create([
            'platform_id' => $this->platformA->id,
            'provider_id' => $this->emailProvider->id,
            'channel' => 'EMAIL',
            'recipient' => 'jogador2@exemplo.com',
            'status' => 'DELIVERED',
            'idempotency_key' => 'idemp_' . Str::uuid()->toString(),
            'provider_message_id' => 'msg_zenvia_888',
        ]);

        // Envia evento atrasado 'SENT'
        $payload = [
            'type' => 'MESSAGE_SENT',
            'messageId' => 'msg_zenvia_888',
            'eventId' => 'evt_late_sent_1',
            'timestamp' => now()->subMinute()->toIso8601String(),
        ];

        $this->postJson('/api/v1/providers/webhooks/zenvia', $payload)->assertStatus(200);

        $message->refresh();
        // Permanece DELIVERED pois SENT tem menor precedência
        $this->assertEquals('DELIVERED', $message->status);
    }

    /**
     * Teste 3: Rastreamento de abertura (Email Open Pixel 1x1 GIF).
     */
    public function test_open_tracking_returns_transparent_gif_and_records_opened_event(): void
    {
        $token = Str::random(48);
        $message = Message::create([
            'platform_id' => $this->platformA->id,
            'provider_id' => $this->emailProvider->id,
            'channel' => 'EMAIL',
            'recipient' => 'abertura@exemplo.com',
            'status' => 'SENT',
            'idempotency_key' => 'idemp_' . Str::uuid()->toString(),
            'open_tracking_token' => $token,
        ]);

        $response = $this->get("/api/v1/tracking/open/{$token}");

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'image/gif');
        $this->assertNotEmpty($response->getContent());

        $message->refresh();
        $this->assertEquals('DELIVERED', $message->status);
        $this->assertNotNull($message->opened_at);

        $event = MessageEvent::where('message_id', $message->id)
            ->where('event_type', 'OPENED')
            ->first();
        $this->assertNotNull($event);
    }

    /**
     * Teste 4: Múltiplas aberturas incrementam total de opens mas mantêm 1 unique_opener.
     */
    public function test_multiple_opens_keep_unique_openers_at_one(): void
    {
        $campaign = Campaign::create([
            'platform_id' => $this->platformA->id,
            'name' => 'Campanha Open Test',
            'channel' => 'EMAIL',
            'status' => 'PROCESSING',
            'template_id' => $this->emailTemplate->id,
            'template_version_id' => $this->emailVersion->id,
            'segment_id' => $this->segment->id,
        ]);

        $token = Str::random(48);
        $message = Message::create([
            'platform_id' => $this->platformA->id,
            'campaign_id' => $campaign->id,
            'provider_id' => $this->emailProvider->id,
            'channel' => 'EMAIL',
            'recipient' => 'multi_open@exemplo.com',
            'status' => 'SENT',
            'idempotency_key' => 'idemp_' . Str::uuid()->toString(),
            'open_tracking_token' => $token,
        ]);

        // Simula 3 aberturas pelo mesmo usuário
        $this->get("/api/v1/tracking/open/{$token}");
        $this->get("/api/v1/tracking/open/{$token}");
        $this->get("/api/v1/tracking/open/{$token}");

        $totalOpenEvents = MessageEvent::where('message_id', $message->id)
            ->where('event_type', 'OPENED')
            ->count();
        $this->assertEquals(3, $totalOpenEvents);

        // Recalcula e verifica agregação
        Artisan::call('campaigns:rebuild-analytics', ['--campaign' => $campaign->id]);

        $metric = CampaignMetric::where('campaign_id', $campaign->id)->first();
        $this->assertEquals(3, $metric->opened_count);
        $this->assertEquals(1, $metric->unique_openers_count);
    }

    /**
     * Teste 5: Click tracking redireciona para a URL original e bloqueia Open Redirect malicioso.
     */
    public function test_click_tracking_redirects_and_prevents_open_redirect(): void
    {
        $token = Str::random(48);
        $message = Message::create([
            'platform_id' => $this->platformA->id,
            'provider_id' => $this->emailProvider->id,
            'channel' => 'EMAIL',
            'recipient' => 'clique@exemplo.com',
            'status' => 'SENT',
            'idempotency_key' => 'idemp_' . Str::uuid()->toString(),
        ]);

        $link = MessageLink::create([
            'message_id' => $message->id,
            'tracking_token' => $token,
            'destination_url' => 'https://betalpha.com/promocao-vip',
        ]);

        $response = $this->get("/api/v1/tracking/click/{$token}");
        $response->assertStatus(302);
        $response->assertRedirect('https://betalpha.com/promocao-vip');

        $link->refresh();
        $this->assertEquals(1, $link->clicks_count);

        $event = MessageEvent::where('message_id', $message->id)
            ->where('event_type', 'CLICKED')
            ->first();
        $this->assertNotNull($event);

        // Teste de Open Redirect bloqueado: URL insegura
        $badToken = Str::random(48);
        MessageLink::create([
            'message_id' => $message->id,
            'tracking_token' => $badToken,
            'destination_url' => 'javascript:alert(1)',
        ]);

        $badResponse = $this->get("/api/v1/tracking/click/{$badToken}");
        $this->assertTrue(in_array($badResponse->status(), [400, 404]));
    }

    /**
     * Teste 6: Descadastramento (Unsubscribe) revoga consentimento de marketing do jogador (LGPD).
     */
    public function test_unsubscribe_revokes_marketing_consent(): void
    {
        $player = Player::create([
            'platform_id' => $this->platformA->id,
            'external_id' => 'ext_player_unsub_1',
            'name' => 'Jogador Consentido',
            'email' => 'unsub@exemplo.com',
            'status' => 'ACTIVE',
        ]);

        Consent::create([
            'player_id' => $player->id,
            'channel' => 'EMAIL',
            'is_granted' => true,
            'consent_date' => now(),
            'consent_source' => 'REGISTRATION',
        ]);

        $unsubToken = Str::random(48);
        $message = Message::create([
            'platform_id' => $this->platformA->id,
            'player_id' => $player->id,
            'provider_id' => $this->emailProvider->id,
            'channel' => 'EMAIL',
            'recipient' => 'unsub@exemplo.com',
            'status' => 'DELIVERED',
            'idempotency_key' => 'idemp_' . Str::uuid()->toString(),
            'unsubscribe_token' => $unsubToken,
        ]);

        $response = $this->get("/api/v1/tracking/unsubscribe/{$unsubToken}");
        $response->assertStatus(200);
        $response->assertSee('Descadastrado com Sucesso');

        // Verifica se o consentimento foi devidamente revogado no banco
        $consent = Consent::where('player_id', $player->id)
            ->where('channel', 'EMAIL')
            ->first();
        $this->assertFalse($consent->is_granted);
        $this->assertNotNull($consent->revoked_at);

        // Verifica que evento foi registrado
        $event = MessageEvent::where('message_id', $message->id)
            ->where('event_type', 'UNSUBSCRIBED')
            ->first();
        $this->assertNotNull($event);
    }

    /**
     * Teste 7: Endpoint de analytics da campanha calcula funil e taxas com segurança.
     */
    public function test_campaign_analytics_endpoint_returns_metrics_and_funnel(): void
    {
        $campaign = Campaign::create([
            'platform_id' => $this->platformA->id,
            'name' => 'Black Friday Analytics',
            'channel' => 'EMAIL',
            'status' => 'COMPLETED',
            'template_id' => $this->emailTemplate->id,
            'template_version_id' => $this->emailVersion->id,
            'segment_id' => $this->segment->id,
        ]);

        CampaignMetric::create([
            'campaign_id' => $campaign->id,
            'platform_id' => $this->platformA->id,
            'audience_count' => 100,
            'eligible_count' => 90,
            'queued_count' => 90,
            'sent_count' => 90,
            'delivered_count' => 80,
            'failed_count' => 5,
            'bounced_count' => 5,
            'opened_count' => 40,
            'unique_openers_count' => 35,
            'clicked_count' => 15,
            'unique_clickers_count' => 10,
            'unsubscribed_count' => 2,
            'delivery_rate' => 88.89,
            'open_rate' => 43.75,
            'click_rate' => 12.50,
            'bounce_rate' => 5.56,
            'failure_rate' => 5.56,
            'unsubscribe_rate' => 2.50,
        ]);

        $headers = $this->headersFor($this->analystUser, $this->platformA);
        $response = $this->getJson("/api/v1/campaigns/{$campaign->id}/analytics", $headers);

        $response->assertStatus(200);
        $response->assertJsonPath('data.campaign_id', $campaign->id);
        $response->assertJsonPath('data.metrics.delivered', 80);
        $response->assertJsonPath('data.metrics.unique_openers', 35);
        $response->assertJsonPath('data.metrics.delivery_rate', 88.89);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'campaign_id',
                'campaign_name',
                'channel',
                'status',
                'metrics',
                'funnel',
                'providers',
                'timeline',
            ],
        ]);
    }

    /**
     * Teste 8: Isolamento multi-plataforma impede acesso cruzado a analytics.
     */
    public function test_multi_platform_isolation_blocks_cross_platform_analytics(): void
    {
        $campaignB = Campaign::create([
            'platform_id' => $this->platformB->id,
            'name' => 'Campanha Plataforma B',
            'channel' => 'EMAIL',
            'status' => 'COMPLETED',
            'template_id' => $this->emailTemplate->id,
            'template_version_id' => $this->emailVersion->id,
            'segment_id' => $this->segment->id,
        ]);

        $headers = $this->headersFor($this->analystUser, $this->platformA);
        $response = $this->getJson("/api/v1/campaigns/{$campaignB->id}/analytics", $headers);

        // Deve retornar 404 (campanha não existe no contexto da plataforma A)
        $response->assertStatus(404);
    }

    /**
     * Teste 9: RBAC bloqueia usuário sem permissão (SUPPORT) de acessar analytics gerais.
     */
    public function test_rbac_restricts_support_user_from_analytics_overview(): void
    {
        $headers = $this->headersFor($this->supportUser, $this->platformA);
        $response = $this->getJson('/api/v1/analytics/overview', $headers);

        $response->assertStatus(403);
    }

    /**
     * Teste 10: Exportação de CSV higienizado sem exposição de dados sensíveis ou secrets.
     */
    public function test_analytics_csv_export(): void
    {
        $campaign = Campaign::create([
            'platform_id' => $this->platformA->id,
            'name' => 'Campanha Export CSV',
            'channel' => 'EMAIL',
            'status' => 'COMPLETED',
            'template_id' => $this->emailTemplate->id,
            'template_version_id' => $this->emailVersion->id,
            'segment_id' => $this->segment->id,
        ]);

        CampaignMetric::create([
            'campaign_id' => $campaign->id,
            'platform_id' => $this->platformA->id,
            'audience_count' => 50,
            'sent_count' => 50,
            'delivered_count' => 48,
        ]);

        $headers = $this->headersFor($this->marketingUser, $this->platformA);
        $response = $this->get('/api/v1/analytics/campaigns/export', $headers);

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $content = $response->getContent();
        $this->assertStringContainsString('Campanha Export CSV', $content);
        $this->assertStringContainsString('Taxa Entrega (%)', $content);
        $this->assertStringNotContainsString('password', $content);
        $this->assertStringNotContainsString('token', $content);
    }

    /**
     * Teste 11: Reconstrução de métricas via comando CLI funciona perfeitamente (Event Sourcing).
     */
    public function test_artisan_rebuild_analytics_command(): void
    {
        $campaign = Campaign::create([
            'platform_id' => $this->platformA->id,
            'name' => 'Campanha Rebuild CLI',
            'channel' => 'EMAIL',
            'status' => 'COMPLETED',
            'template_id' => $this->emailTemplate->id,
            'template_version_id' => $this->emailVersion->id,
            'segment_id' => $this->segment->id,
        ]);

        $message = Message::create([
            'platform_id' => $this->platformA->id,
            'campaign_id' => $campaign->id,
            'provider_id' => $this->emailProvider->id,
            'channel' => 'EMAIL',
            'recipient' => 'rebuild@exemplo.com',
            'status' => 'DELIVERED',
            'idempotency_key' => 'idemp_' . Str::uuid()->toString(),
        ]);

        MessageEvent::create([
            'message_id' => $message->id,
            'event_type' => 'DELIVERED',
            'occurred_at' => now(),
            'created_at' => now(),
        ]);

        MessageEvent::create([
            'message_id' => $message->id,
            'event_type' => 'OPENED',
            'occurred_at' => now(),
            'created_at' => now(),
        ]);

        $exitCode = Artisan::call('campaigns:rebuild-analytics', ['--campaign' => $campaign->id]);
        $this->assertEquals(0, $exitCode);

        $metric = CampaignMetric::where('campaign_id', $campaign->id)->first();
        $this->assertNotNull($metric);
        $this->assertEquals(1, $metric->delivered_count);
        $this->assertEquals(1, $metric->unique_openers_count);
    }
}
