<?php

declare(strict_types=1);

namespace Tests\Acceptance\Treasury;

use App\Models\User;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TreasuryAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);
    }

    /**
     * SATU test untuk semua grup rute Treasury dengan dataset role berhak (200/302) vs tidak berhak (403).
     *
     * @dataProvider treasuryRoutesProvider
     */
    public function test_treasury_routes_access_control(string $route, string $method, array $allowedRoles, array $forbiddenRoles): void
    {
        // 1. Unauthenticated -> Redirect to login
        $response = $this->call($method, $route);
        $response->assertRedirect('/login');

        // 2. Allowed Roles -> Status 200 or 302
        foreach ($allowedRoles as $role) {
            $user = User::factory()->create(['role' => $role]);
            $res = $this->actingAs($user)->call($method, $route);
            $this->assertTrue(
                in_array($res->status(), [200, 302], true),
                "Role '{$role}' should have access to '{$route}', got HTTP {$res->status()}"
            );
        }

        // 3. Forbidden Roles -> Status 403
        foreach ($forbiddenRoles as $role) {
            $unauthorizedUser = User::factory()->create(['role' => $role]);
            $res = $this->actingAs($unauthorizedUser)->call($method, $route);
            $this->assertSame(
                403,
                $res->status(),
                "Role '{$role}' should NOT have access to '{$route}', got HTTP {$res->status()}"
            );
        }
    }

    public static function treasuryRoutesProvider(): array
    {
        return [
            'dashboard_index' => [
                'route' => '/treasury',
                'method' => 'GET',
                'allowedRoles' => ['treasury', 'admin', 'auditor'],
                'forbiddenRoles' => ['customer', 'guest'],
            ],
            'cash_positions' => [
                'route' => '/treasury/positions',
                'method' => 'GET',
                'allowedRoles' => ['treasury', 'auditor', 'admin'],
                'forbiddenRoles' => ['customer', 'guest'],
            ],
            'fx_management' => [
                'route' => '/treasury/fx',
                'method' => 'GET',
                'allowedRoles' => ['treasury', 'admin'],
                'forbiddenRoles' => ['customer', 'guest'],
            ],
            'bank_reconciliation' => [
                'route' => '/treasury/reconciliation',
                'method' => 'GET',
                'allowedRoles' => ['treasury', 'admin'],
                'forbiddenRoles' => ['customer', 'guest'],
            ],
            'cash_forecast' => [
                'route' => '/treasury/forecast',
                'method' => 'GET',
                'allowedRoles' => ['treasury', 'auditor', 'admin'],
                'forbiddenRoles' => ['customer', 'guest'],
            ],
            'hedges_list' => [
                'route' => '/treasury/hedges',
                'method' => 'GET',
                'allowedRoles' => ['treasury', 'admin'],
                'forbiddenRoles' => ['customer', 'guest'],
            ],
            'credit_facilities' => [
                'route' => '/treasury/facilities',
                'method' => 'GET',
                'allowedRoles' => ['treasury', 'auditor', 'admin'],
                'forbiddenRoles' => ['customer', 'guest'],
            ],
            'cash_pools' => [
                'route' => '/treasury/pools',
                'method' => 'GET',
                'allowedRoles' => ['treasury', 'admin'],
                'forbiddenRoles' => ['customer', 'guest'],
            ],
        ];
    }
}
