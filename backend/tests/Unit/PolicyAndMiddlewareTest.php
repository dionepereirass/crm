<?php

namespace Tests\Unit;

use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\RequirePermission;
use App\Models\Platform;
use App\Models\User;
use App\Policies\PlatformPolicy;
use App\Policies\UserPolicy;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class PolicyAndMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_platform_policy_grants_super_admin_full_access(): void
    {
        $superAdmin = User::where('email', 'superadmin@crm.example.com')->first();
        $platform = Platform::first();

        $policy = new PlatformPolicy();

        $this->assertTrue($policy->viewAny($superAdmin));
        $this->assertTrue($policy->view($superAdmin, $platform));
        $this->assertTrue($policy->create($superAdmin));
        $this->assertTrue($policy->update($superAdmin, $platform));
        $this->assertTrue($policy->delete($superAdmin, $platform));
    }

    public function test_platform_policy_restricts_regular_admin(): void
    {
        $admin = User::where('email', 'admin@crm.example.com')->first();
        $betBrasil = Platform::where('slug', 'bet-brasil')->first();
        $betGlobal = Platform::where('slug', 'bet-global')->first();

        $policy = new PlatformPolicy();

        $this->assertTrue($policy->view($admin, $betBrasil));
        // Admin cannot view or delete Bet Global
        $this->assertFalse($policy->view($admin, $betGlobal));
        $this->assertFalse($policy->delete($admin, $betBrasil));
    }

    public function test_user_policy_prevents_user_from_deleting_self(): void
    {
        $superAdmin = User::where('email', 'superadmin@crm.example.com')->first();
        $policy = new UserPolicy();

        $this->assertFalse($policy->delete($superAdmin, $superAdmin));
    }

    public function test_ensure_user_is_active_middleware_blocks_inactive(): void
    {
        $inactive = User::where('email', 'inactive@crm.example.com')->first();

        $middleware = new EnsureUserIsActive();
        $request = Request::create('/api/v1/auth/me', 'GET');
        $request->setUserResolver(fn() => $inactive);

        $response = $middleware->handle($request, fn() => response('OK'));

        $this->assertEquals(403, $response->getStatusCode());
    }

    public function test_require_permission_middleware(): void
    {
        $support = User::where('email', 'support@crm.example.com')->first();

        $middleware = new RequirePermission();
        $request = Request::create('/test', 'GET');
        $request->setUserResolver(fn() => $support);

        // Has permission
        $resAllowed = $middleware->handle($request, fn() => response('OK'), 'players.view');
        $this->assertEquals(200, $resAllowed->getStatusCode());

        // Does not have permission
        $resDenied = $middleware->handle($request, fn() => response('OK'), 'campaigns.send');
        $this->assertEquals(403, $resDenied->getStatusCode());
    }
}
