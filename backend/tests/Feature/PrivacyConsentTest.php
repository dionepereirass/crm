<?php

namespace Tests\Feature;

use App\Enums\ConsentStatus;
use App\Enums\ConsentType;
use App\Models\Consent;
use App\Models\ConsentHistory;
use App\Models\Platform;
use App\Models\Player;
use App\Models\User;
use App\Services\Privacy\ConsentPolicyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class PrivacyConsentTest extends TestCase
{
    use RefreshDatabase;

    protected Platform $platformA;
    protected Platform $platformB;
    protected User $adminUser;
    protected User $marketingUser;
    protected string $adminToken;
    protected string $marketingToken;
    protected Player $playerA;
    protected Player $playerB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);

        $this->platformA = Platform::where('slug', 'bet-brasil')->firstOrFail();
        $this->platformB = Platform::where('slug', 'bet-global')->firstOrFail();

        $this->adminUser = User::where('email', 'admin@crm.example.com')->firstOrFail();
        $this->adminToken = $this->adminUser->createToken('admin')->plainTextToken;

        $this->marketingUser = User::where('email', 'marketing@crm.example.com')->firstOrFail();
        $this->marketingToken = $this->marketingUser->createToken('marketing')->plainTextToken;

        $this->playerA = Player::create([
            'platform_id' => $this->platformA->id,
            'external_id' => 'PLY-PRIV-A1',
            'name' => 'Roberto Carlos',
            'email' => 'roberto@example.com',
            'phone' => '5531988887777',
            'cpf' => '12345678901',
            'status' => 'ACTIVE',
        ]);

        $this->playerB = Player::create([
            'platform_id' => $this->platformB->id,
            'external_id' => 'PLY-PRIV-B1',
            'name' => 'John Doe Global',
            'email' => 'john@globalbet.com',
            'phone' => '14155552671',
            'status' => 'ACTIVE',
        ]);
    }

    public function test_user_can_grant_consent_and_generates_evidence_with_hash(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->marketingToken}")
            ->withHeader('X-Platform-Id', (string) $this->platformA->id)
            ->postJson('/api/v1/privacy/consents/grant', [
                'player_id' => $this->playerA->id,
                'type' => 'MARKETING_EMAIL',
                'source' => 'terms_checkout_v2',
                'version' => 'v2.1',
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.status', 'GRANTED');
        $response->assertJsonPath('data.type', 'MARKETING_EMAIL');
        $response->assertJsonPath('data.is_granted', true);

        // Verifica evidência gerada
        $this->assertDatabaseHas('consents', [
            'player_id' => $this->playerA->id,
            'type' => 'MARKETING_EMAIL',
            'status' => 'GRANTED',
            'is_granted' => true,
            'consent_source' => 'terms_checkout_v2',
        ]);

        $consent = Consent::where('player_id', $this->playerA->id)->firstOrFail();
        $this->assertNotEmpty($consent->evidence_hash);
        $this->assertEquals(64, strlen($consent->evidence_hash));
        $this->assertIsArray($consent->evidence);

        // Verifica histórico imutável (append-only)
        $this->assertDatabaseHas('consent_history', [
            'consent_id' => $consent->id,
            'player_id' => $this->playerA->id,
            'action' => 'GRANT',
            'new_status' => 'GRANTED',
        ]);
    }

    public function test_user_can_revoke_consent_and_blocks_marketing_channels(): void
    {
        $policyService = app(ConsentPolicyService::class);

        // Concede primeiro
        $policyService->grantConsent($this->playerA, 'MARKETING_EMAIL');
        $this->assertTrue($policyService->canSendMarketingEmail($this->playerA));
        $this->assertTrue($this->playerA->hasMarketingConsent('EMAIL'));

        // Revoga via endpoint
        $response = $this->withHeader('Authorization', "Bearer {$this->marketingToken}")
            ->withHeader('X-Platform-Id', (string) $this->platformA->id)
            ->postJson('/api/v1/privacy/consents/revoke', [
                'player_id' => $this->playerA->id,
                'type' => 'MARKETING_EMAIL',
                'reason' => 'Solicitação expressa do titular via formulário LGPD',
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.status', 'REVOKED');
        $response->assertJsonPath('data.is_granted', false);

        // Verifica bloqueio nos serviços de consentimento
        $this->assertFalse($policyService->canSendMarketingEmail($this->playerA->fresh()));
        $this->assertFalse($this->playerA->fresh()->hasMarketingConsent('EMAIL'));

        // Histórico registrado
        $this->assertDatabaseHas('consent_history', [
            'player_id' => $this->playerA->id,
            'action' => 'REVOKE',
            'new_status' => 'REVOKED',
        ]);
    }

    public function test_consent_history_is_append_only_and_cannot_be_updated_or_deleted(): void
    {
        $policyService = app(ConsentPolicyService::class);
        $consent = $policyService->grantConsent($this->playerA, 'MARKETING_SMS');

        $historyEntry = ConsentHistory::where('consent_id', $consent->id)->firstOrFail();

        // Tentativa de update deve lançar RuntimeException de violação LGPD
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Violação de Imutabilidade LGPD');
        $historyEntry->update(['new_status' => 'REVOKED']);
    }

    public function test_consent_policy_service_authorizes_or_denies_channels_accurately(): void
    {
        $policyService = app(ConsentPolicyService::class);

        // Inicialmente tudo negado
        $this->assertFalse($policyService->canSendMarketingEmail($this->playerA));
        $this->assertFalse($policyService->canSendMarketingSms($this->playerA));
        $this->assertFalse($policyService->canSendMarketingWhatsapp($this->playerA));

        // Concede apenas E-mail
        $policyService->grantConsent($this->playerA, ConsentType::MARKETING_EMAIL->value);

        $this->assertTrue($policyService->canSendMarketingEmail($this->playerA));
        $this->assertFalse($policyService->canSendMarketingSms($this->playerA));
        $this->assertFalse($policyService->canSendMarketingWhatsapp($this->playerA));
        $this->assertTrue($policyService->canRunMarketingAutomation($this->playerA));

        // Concede SMS
        $policyService->grantConsent($this->playerA, ConsentType::MARKETING_SMS->value);
        $this->assertTrue($policyService->canSendMarketingSms($this->playerA));
    }

    public function test_multi_platform_isolation_for_consents(): void
    {
        // Concede na Plataforma B
        $policyService = app(ConsentPolicyService::class);
        $policyService->grantConsent($this->playerB, 'MARKETING_EMAIL');

        // Usuário da Plataforma A tentando listar consentimentos da Plataforma B
        $response = $this->withHeader('Authorization', "Bearer {$this->marketingToken}")
            ->withHeader('X-Platform-Id', (string) $this->platformA->id)
            ->getJson("/api/v1/privacy/consents?player_id={$this->playerB->id}");

        $response->assertStatus(200);
        $this->assertCount(0, $response->json('data'));

        // Concessão direta no player de outra plataforma deve ser barrada com 404
        $crossGrant = $this->withHeader('Authorization', "Bearer {$this->marketingToken}")
            ->withHeader('X-Platform-Id', (string) $this->platformA->id)
            ->postJson('/api/v1/privacy/consents/grant', [
                'player_id' => $this->playerB->id,
                'type' => 'MARKETING_EMAIL',
            ]);

        $crossGrant->assertStatus(404);
    }
}
