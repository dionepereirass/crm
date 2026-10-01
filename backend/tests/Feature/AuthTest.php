<?php

namespace Tests\Feature;

use App\Models\Platform;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    /**
     * 1. Teste de Login Válido
     */
    public function test_user_can_login_with_valid_credentials(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@crm.example.com',
            'password' => 'Secret@123456',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'token',
                    'token_type',
                    'user' => [
                        'id',
                        'name',
                        'email',
                        'status',
                        'roles',
                        'permissions',
                        'platforms',
                    ],
                ],
            ]);
    }

    /**
     * 2. Teste de Login com Senha Inválida
     */
    public function test_user_cannot_login_with_invalid_password(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@crm.example.com',
            'password' => 'WrongPassword!99',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Credenciais de acesso inválidas.');
    }

    /**
     * 3. Teste de Login com Usuário Inexistente
     */
    public function test_user_cannot_login_with_nonexistent_email(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'naoexiste@crm.example.com',
            'password' => 'Secret@123456',
        ]);

        // Security check: response must be identical to invalid password to avoid email harvesting
        $response->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Credenciais de acesso inválidas.');
    }

    /**
     * 4. Teste de Usuário Desativado (INACTIVE/BLOCKED)
     */
    public function test_inactive_user_cannot_login(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'inactive@crm.example.com',
            'password' => 'Secret@123456',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Conta de usuário desativada ou bloqueada. Entre em contato com o administrador.');
    }

    /**
     * 5. Teste de Logout
     */
    public function test_authenticated_user_can_logout(): void
    {
        $user = User::where('email', 'admin@crm.example.com')->first();
        $token = $user->createToken('test_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/auth/logout');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Logout realizado com sucesso.');

        // Token should be revoked
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name' => 'test_token',
        ]);
    }

    /**
     * 6. Teste de /auth/me Autenticado
     */
    public function test_authenticated_user_can_fetch_profile(): void
    {
        $user = User::where('email', 'admin@crm.example.com')->first();
        $token = $user->createToken('test_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/auth/me');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.email', 'admin@crm.example.com')
            ->assertJsonPath('data.roles.0.slug', 'ADMIN');
    }

    /**
     * 7. Teste de /auth/me Sem Autenticação
     */
    public function test_unauthenticated_user_cannot_access_profile(): void
    {
        $response = $this->getJson('/api/v1/auth/me');

        $response->assertStatus(401);
    }

    /**
     * 8. Teste de Role SUPER_ADMIN
     */
    public function test_super_admin_role_privileges(): void
    {
        $superAdmin = User::where('email', 'superadmin@crm.example.com')->first();

        $this->assertTrue($superAdmin->isSuperAdmin());
        $this->assertTrue($superAdmin->hasRole('SUPER_ADMIN'));
        $this->assertTrue($superAdmin->hasPermission('platforms.delete'));
        $this->assertTrue($superAdmin->hasPermission('campaigns.send'));
    }

    /**
     * 9. Teste de Role ADMIN
     */
    public function test_admin_role_privileges(): void
    {
        $admin = User::where('email', 'admin@crm.example.com')->first();

        $this->assertFalse($admin->isSuperAdmin());
        $this->assertTrue($admin->hasRole('ADMIN'));
        $this->assertTrue($admin->hasPermission('users.create'));
        // Platform deletion is exclusive to SUPER_ADMIN
        $this->assertFalse($admin->hasPermission('platforms.delete'));
    }

    /**
     * 10. Teste de Permissão Negada
     */
    public function test_permission_denied_for_unauthorized_role(): void
    {
        $support = User::where('email', 'support@crm.example.com')->first();

        $this->assertFalse($support->hasPermission('campaigns.send'));
        $this->assertFalse($support->hasPermission('users.create'));
        $this->assertTrue($support->hasPermission('players.view'));
    }

    /**
     * 11. Teste de Usuário sem Vínculo com Plataforma
     */
    public function test_user_without_platform_association_fails_login(): void
    {
        $unlinkedUser = User::create([
            'name' => 'Sem Plataforma',
            'email' => 'unlinked@crm.example.com',
            'password' => Hash::make('Secret@123456'),
            'status' => 'ACTIVE',
        ]);
        $unlinkedUser->assignRole('SUPPORT');

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'unlinked@crm.example.com',
            'password' => 'Secret@123456',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Usuário sem nenhuma plataforma vinculada no sistema.');
    }

    /**
     * 12. Teste de Isolamento entre Plataformas
     */
    public function test_cross_platform_isolation(): void
    {
        $betBrasil = Platform::where('slug', 'bet-brasil')->first();
        $betGlobal = Platform::where('slug', 'bet-global')->first();

        // Admin has access only to Bet Brasil
        $admin = User::where('email', 'admin@crm.example.com')->first();
        $this->assertTrue($admin->hasAccessToPlatform($betBrasil));
        $this->assertFalse($admin->hasAccessToPlatform($betGlobal));

        // When making requests with X-Platform-Id of an unauthorized platform, it must return 403
        $token = $admin->createToken('test_token')->plainTextToken;
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('X-Platform-Id', (string) $betGlobal->id)
            ->getJson('/api/v1/auth/me');

        $response->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Acesso negado: você não tem permissão para operar nesta plataforma.');
    }

    /**
     * 13. Teste de Usuário Pertencente à Plataforma Correta
     */
    public function test_user_can_access_authorized_platform(): void
    {
        $betBrasil = Platform::where('slug', 'bet-brasil')->first();
        $admin = User::where('email', 'admin@crm.example.com')->first();

        $token = $admin->createToken('test_token')->plainTextToken;
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('X-Platform-Id', (string) $betBrasil->id)
            ->getJson('/api/v1/auth/me');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    /**
     * 14. Teste de Revogação de Todos os Tokens
     */
    public function test_user_can_revoke_all_tokens(): void
    {
        $user = User::where('email', 'admin@crm.example.com')->first();
        $token1 = $user->createToken('token_1')->plainTextToken;
        $token2 = $user->createToken('token_2')->plainTextToken;

        $this->assertCount(2, $user->tokens);

        $response = $this->withHeader('Authorization', "Bearer {$token1}")
            ->postJson('/api/v1/auth/revoke');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Todos os tokens de acesso foram revogados.');

        $this->assertCount(0, $user->fresh()->tokens);
    }

    /**
     * 15. Teste de Rate Limiting do Login (Proteção contra Força Bruta)
     */
    public function test_login_rate_limiting_blocks_brute_force(): void
    {
        $email = 'admin@crm.example.com';
        RateLimiter::clear(strtolower($email).'|127.0.0.1');

        // 5 consecutive failed attempts
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => $email,
                'password' => 'WrongPassword',
            ])->assertStatus(401);
        }

        // 6th attempt should be blocked by Rate Limiting (429 Too Many Requests)
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $email,
            'password' => 'Secret@123456',
        ]);

        $response->assertStatus(429);
    }
}
