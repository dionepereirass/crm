<?php

namespace Tests\Feature;

use App\Models\Platform;
use App\Models\Template;
use App\Models\TemplateVersion;
use App\Models\User;
use App\Services\Templates\TemplateRenderer;
use App\Services\Templates\TemplateSanitizer;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TemplateTest extends TestCase
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
     * 1. Criar Template de E-mail com Versão 1 inicial.
     */
    public function test_can_create_email_template(): void
    {
        $payload = [
            'name' => 'Boas-vindas VIP',
            'description' => 'E-mail enviado após primeiro depósito',
            'channel' => 'EMAIL',
            'category' => 'WELCOME',
            'status' => 'ACTIVE',
            'subject' => 'Olá {{player.first_name}}, bem-vindo à {{platform.name}}!',
            'preheader' => 'Seu bônus exclusivo já está disponível.',
            'html_content' => '<p>Olá <strong>{{player.name}}</strong>, seu saldo inicial é {{player.deposit.total|default:"R$ 0,00"}}.</p>',
            'text_content' => 'Olá {{player.name}}, seu saldo inicial é {{player.deposit.total}}.',
        ];

        $response = $this->withHeader('Authorization', "Bearer {$this->marketingToken}")
            ->withHeader('X-Platform-Id', (string) $this->betBrasil->id)
            ->postJson('/api/v1/templates', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Boas-vindas VIP')
            ->assertJsonPath('data.channel', 'EMAIL')
            ->assertJsonPath('data.status', 'ACTIVE')
            ->assertJsonPath('data.current_version.version', 1)
            ->assertJsonPath('data.current_version.status', 'PUBLISHED');

        $templateId = $response->json('data.id');
        $this->assertDatabaseHas('templates', ['id' => $templateId, 'name' => 'Boas-vindas VIP']);
        $this->assertDatabaseHas('template_versions', ['template_id' => $templateId, 'version' => 1]);
    }

    /**
     * 2. Criar Template de SMS.
     */
    public function test_can_create_sms_template(): void
    {
        $payload = [
            'name' => 'Alerta de Depósito SMS',
            'channel' => 'SMS',
            'category' => 'DEPOSIT',
            'status' => 'ACTIVE',
            'sms_content' => 'Ola {{player.first_name|default:"Apostador"}}, seu deposito de {{player.deposit.last_amount}} foi confirmado!',
        ];

        $response = $this->withHeader('Authorization', "Bearer {$this->marketingToken}")
            ->withHeader('X-Platform-Id', (string) $this->betBrasil->id)
            ->postJson('/api/v1/templates', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.channel', 'SMS');

        $templateId = $response->json('data.id');
        $this->assertDatabaseHas('template_versions', [
            'template_id' => $templateId,
            'sms_content' => 'Ola {{player.first_name|default:"Apostador"}}, seu deposito de {{player.deposit.last_amount}} foi confirmado!',
        ]);
    }

    /**
     * 3. Isolamento multi-plataforma: usuário de Bet Brasil não acessa template de Bet Global.
     */
    public function test_multi_platform_isolation_for_templates(): void
    {
        // Cria template na Bet Global
        $templateGlobal = Template::create([
            'platform_id' => $this->betGlobal->id,
            'name' => 'Global VIP Exclusive',
            'channel' => 'EMAIL',
            'status' => 'ACTIVE',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->marketingToken}")
            ->withHeader('X-Platform-Id', (string) $this->betBrasil->id)
            ->getJson('/api/v1/templates');

        $response->assertStatus(200)
            ->assertJsonCount(0, 'data');

        // Acesso direto por ID resulta em 404
        $detail = $this->withHeader('Authorization', "Bearer {$this->marketingToken}")
            ->withHeader('X-Platform-Id', (string) $this->betBrasil->id)
            ->getJson("/api/v1/templates/{$templateGlobal->id}");

        $detail->assertStatus(404);
    }

    /**
     * 4. RBAC: Usuário Support não pode criar ou publicar templates.
     */
    public function test_support_user_cannot_create_or_publish_template(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->supportToken}")
            ->withHeader('X-Platform-Id', (string) $this->betBrasil->id)
            ->postJson('/api/v1/templates', [
                'name' => 'Tentativa Invalida',
                'channel' => 'EMAIL',
                'subject' => 'Teste',
                'html_content' => '<p>Teste</p>',
            ]);

        $response->assertStatus(403);
    }

    /**
     * 5. Validação de Variáveis: rejeita variáveis inexistentes no catálogo oficial.
     */
    public function test_rejects_unknown_variables(): void
    {
        $payload = [
            'name' => 'Template com Variavel Hacker',
            'channel' => 'EMAIL',
            'subject' => 'Ola {{player.campo_inexistente_hacker}}',
            'html_content' => '<p>Conteudo</p>',
        ];

        $response = $this->withHeader('Authorization', "Bearer {$this->marketingToken}")
            ->withHeader('X-Platform-Id', (string) $this->betBrasil->id)
            ->postJson('/api/v1/templates', $payload);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    /**
     * 6. Validação de Variáveis: rejeita variável incompatível com SMS.
     */
    public function test_rejects_sms_incompatible_variables(): void
    {
        $payload = [
            'name' => 'SMS com Variavel Incompativel',
            'channel' => 'SMS',
            'sms_content' => 'Ola, seu email e {{player.email}}', // player.email só é permitido em EMAIL
        ];

        $response = $this->withHeader('Authorization', "Bearer {$this->marketingToken}")
            ->withHeader('X-Platform-Id', (string) $this->betBrasil->id)
            ->postJson('/api/v1/templates', $payload);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    /**
     * 7. TemplateRenderer: Substituição segura de variáveis e suporte a fallbacks.
     */
    public function test_template_renderer_replaces_variables_and_applies_fallback(): void
    {
        $renderer = app(TemplateRenderer::class);

        // Cenário com dados presentes
        $text = 'Ola {{player.first_name}}, seu deposito de {{player.deposit.last_amount}} foi recebido!';
        $context = [
            'player' => [
                'name' => 'Mariana Silva',
                'deposit' => [
                    'last_amount' => 250.50,
                ],
            ],
        ];

        $result = $renderer->renderString($text, $context);
        $this->assertEquals('Ola Mariana, seu deposito de R$ 250,50 foi recebido!', $result);

        // Cenário com dados ausentes e fallback
        $textWithFallback = 'Ola {{player.first_name|default:"Apostador"}}, bem-vindo a {{platform.name|default:"nossa casa"}}!';
        $emptyContext = [];

        $fallbackResult = $renderer->renderString($textWithFallback, $emptyContext);
        $this->assertEquals('Ola Apostador, bem-vindo a nossa casa!', $fallbackResult);
    }

    /**
     * 8. TemplateRenderer: Contador de caracteres e cálculo de segmentos GSM-7 vs Unicode.
     */
    public function test_sms_metrics_calculation_gsm7_vs_unicode(): void
    {
        $renderer = app(TemplateRenderer::class);

        // Texto GSM-7 básico com menos de 160 caracteres = 1 segmento
        $gsmShort = "Ola Carlos, seu bonus de 50 reais esta disponivel na Bet Brasil.";
        $m1 = $renderer->calculateSmsMetrics($gsmShort);
        $this->assertEquals('GSM-7', $m1['encoding']);
        $this->assertEquals(1, $m1['segments']);
        $this->assertFalse($m1['exceeded']);

        // Texto com emoji ou caractere especial fora do GSM-7 = UNICODE (limite 70 caracteres)
        $unicodeText = "Ola Carlos! Ganhe bônus 🎁 no cassino da Bet Brasil agora mesmo!";
        $m2 = $renderer->calculateSmsMetrics($unicodeText);
        $this->assertEquals('UNICODE', $m2['encoding']);
    }

    /**
     * 9. Imutabilidade e Versionamento: editar template publicado gera Versão N+1 em DRAFT.
     */
    public function test_editing_published_template_creates_new_draft_version(): void
    {
        // 1. Cria template publicado (v1)
        $createRes = $this->withHeader('Authorization', "Bearer {$this->marketingToken}")
            ->withHeader('X-Platform-Id', (string) $this->betBrasil->id)
            ->postJson('/api/v1/templates', [
                'name' => 'Newsletter Semanal',
                'channel' => 'EMAIL',
                'status' => 'ACTIVE',
                'subject' => 'Novidades da Semana v1',
                'html_content' => '<p>Conteudo v1</p>',
            ]);

        $templateId = $createRes->json('data.id');
        $this->assertEquals(1, $createRes->json('data.current_version.version'));

        // 2. Edita o conteúdo do template publicado
        $updateRes = $this->withHeader('Authorization', "Bearer {$this->marketingToken}")
            ->withHeader('X-Platform-Id', (string) $this->betBrasil->id)
            ->putJson("/api/v1/templates/{$templateId}", [
                'subject' => 'Novidades da Semana v2',
                'html_content' => '<p>Conteudo v2 melhorado</p>',
            ]);

        $updateRes->assertStatus(200);

        // A versão 1 permanece inalterada como PUBLISHED no banco
        $this->assertDatabaseHas('template_versions', [
            'template_id' => $templateId,
            'version' => 1,
            'status' => 'PUBLISHED',
            'subject' => 'Novidades da Semana v1',
        ]);

        // Uma nova versão 2 foi criada em DRAFT
        $this->assertDatabaseHas('template_versions', [
            'template_id' => $templateId,
            'version' => 2,
            'status' => 'DRAFT',
            'subject' => 'Novidades da Semana v2',
        ]);

        // O template continua apontando para a v1 até a v2 ser publicada
        $template = Template::withoutGlobalScopes()->find($templateId);
        $this->assertEquals(1, $template->currentVersion->version);

        // 3. Publica a versão 2
        $pubRes = $this->withHeader('Authorization', "Bearer {$this->marketingToken}")
            ->withHeader('X-Platform-Id', (string) $this->betBrasil->id)
            ->postJson("/api/v1/templates/{$templateId}/versions/2/publish");

        $pubRes->assertStatus(200)
            ->assertJsonPath('data.current_version.version', 2)
            ->assertJsonPath('data.current_version.status', 'PUBLISHED');
    }

    /**
     * 10. Restauração de versão histórica: cria nova versão N+1 preservando histórico.
     */
    public function test_restore_historical_version_creates_new_version(): void
    {
        $template = Template::create([
            'platform_id' => $this->betBrasil->id,
            'name' => 'Template de Recarga',
            'channel' => 'EMAIL',
            'status' => 'ACTIVE',
        ]);

        $v1 = TemplateVersion::create([
            'template_id' => $template->id,
            'version' => 1,
            'status' => 'PUBLISHED',
            'subject' => 'Recarga v1 original',
            'html_content' => '<p>Recarga 1</p>',
        ]);

        $v2 = TemplateVersion::create([
            'template_id' => $template->id,
            'version' => 2,
            'status' => 'PUBLISHED',
            'subject' => 'Recarga v2 modificada',
            'html_content' => '<p>Recarga 2</p>',
        ]);

        $template->update(['current_version_id' => $v2->id]);

        // Restaura a versão 1
        $response = $this->withHeader('Authorization', "Bearer {$this->marketingToken}")
            ->withHeader('X-Platform-Id', (string) $this->betBrasil->id)
            ->postJson("/api/v1/templates/{$template->id}/versions/1/restore");

        $response->assertStatus(200)
            ->assertJsonPath('data.current_version.version', 3)
            ->assertJsonPath('data.current_version.subject', 'Recarga v1 original');

        // Versão 3 foi criada com o conteúdo da v1
        $this->assertDatabaseHas('template_versions', [
            'template_id' => $template->id,
            'version' => 3,
            'subject' => 'Recarga v1 original',
        ]);
    }

    /**
     * 11. Duplicação de Template.
     */
    public function test_duplicate_template(): void
    {
        $template = Template::create([
            'platform_id' => $this->betBrasil->id,
            'name' => 'Original Welcome',
            'channel' => 'EMAIL',
            'status' => 'ACTIVE',
        ]);

        $v1 = TemplateVersion::create([
            'template_id' => $template->id,
            'version' => 1,
            'status' => 'PUBLISHED',
            'subject' => 'Welcome Subject',
            'html_content' => '<p>Welcome</p>',
        ]);
        $template->update(['current_version_id' => $v1->id]);

        $response = $this->withHeader('Authorization', "Bearer {$this->marketingToken}")
            ->withHeader('X-Platform-Id', (string) $this->betBrasil->id)
            ->postJson("/api/v1/templates/{$template->id}/duplicate");

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Original Welcome (Cópia)')
            ->assertJsonPath('data.status', 'DRAFT')
            ->assertJsonPath('data.current_version.version', 1);

        $cloneId = $response->json('data.id');
        $this->assertNotEquals($template->id, $cloneId);
    }

    /**
     * 12. Segurança e Sanitização: remove scripts maliciosos e iframes em HTML.
     */
    public function test_html_sanitizer_removes_dangerous_content(): void
    {
        $sanitizer = app(TemplateSanitizer::class);

        $malicious = '<div><p>Olá</p><script>alert("hacked")</script><iframe src="evil.com"></iframe><a href="javascript:stealCookie()">Clique</a><img src="pic.jpg" onload="badCode()" style="color: red;" /></div>';

        $clean = $sanitizer->sanitizeHtml($malicious);

        $this->assertStringNotContainsString('<script', $clean);
        $this->assertStringNotContainsString('alert(', $clean);
        $this->assertStringNotContainsString('<iframe', $clean);
        $this->assertStringNotContainsString('javascript:', $clean);
        $this->assertStringNotContainsString('onload=', $clean);
        $this->assertStringContainsString('<p>Olá</p>', $clean);
        $this->assertStringContainsString('style="color: red;"', $clean);
    }

    /**
     * 13. Preview de Template com dados simulados seguros.
     */
    public function test_template_preview_endpoint(): void
    {
        $template = Template::create([
            'platform_id' => $this->betBrasil->id,
            'name' => 'Preview Test Template',
            'channel' => 'EMAIL',
            'status' => 'ACTIVE',
        ]);

        $v1 = TemplateVersion::create([
            'template_id' => $template->id,
            'version' => 1,
            'status' => 'PUBLISHED',
            'subject' => 'Ola {{player.first_name}}!',
            'html_content' => '<p>Seu saldo: {{player.deposit.total}}</p>',
        ]);
        $template->update(['current_version_id' => $v1->id]);

        $response = $this->withHeader('Authorization', "Bearer {$this->marketingToken}")
            ->withHeader('X-Platform-Id', (string) $this->betBrasil->id)
            ->postJson("/api/v1/templates/{$template->id}/preview", [
                'context' => [
                    'player' => [
                        'first_name' => 'Roberto',
                        'deposit' => ['total' => 999.00],
                    ],
                ],
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.subject', 'Ola Roberto!')
            ->assertJsonPath('data.html', '<p>Seu saldo: R$ 999,00</p>');
    }
}
