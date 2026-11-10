<?php

declare(strict_types=1);

namespace Tests\Acceptance\Intercompany;

use App\Models\User;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class IntercompanyAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);
    }

    #[DataProvider('intercompanyRoutesProvider')]
    public function test_intercompany_routes_access_control(string $route, string $method, array $allowedRoles, array $forbiddenRoles): void
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

    public static function intercompanyRoutesProvider(): array
    {
        return [
            'intercompany_index' => [
                'route' => '/intercompany',
                'method' => 'GET',
                'allowedRoles' => ['admin', 'auditor', 'legal', 'treasury'],
                'forbiddenRoles' => ['customer', 'guest'],
            ],
            'intercompany_transactions' => [
                'route' => '/intercompany/transactions',
                'method' => 'GET',
                'allowedRoles' => ['admin', 'auditor', 'legal', 'treasury'],
                'forbiddenRoles' => ['customer', 'guest'],
            ],
            'intercompany_consolidation_index' => [
                'route' => '/intercompany/consolidation',
                'method' => 'GET',
                'allowedRoles' => ['admin', 'auditor', 'legal', 'treasury'],
                'forbiddenRoles' => ['customer', 'guest'],
            ],
        ];
    }
}
