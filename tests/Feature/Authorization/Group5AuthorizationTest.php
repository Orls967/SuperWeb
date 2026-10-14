<?php

declare(strict_types=1);

namespace Tests\Feature\Authorization;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class Group5AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('routesWithRolesProvider')]
    public function test_group5_routes_allow_authorized_roles(string $route, array $allowedRoles): void
    {
        foreach ($allowedRoles as $role) {
            $user = User::factory()->create(['role' => $role]);

            $response = $this->actingAs($user)->get(route($route));
            $response->assertOk();
        }
    }

    #[DataProvider('routesWithRolesProvider')]
    public function test_group5_routes_deny_unauthorized_roles(string $route, array $allowedRoles): void
    {
        $allPotentialUnauthorized = ['customer', 'driver', 'waiter', 'cleaner', 'cashier'];
        $unauthorizedRoles = array_diff($allPotentialUnauthorized, $allowedRoles);

        foreach ($unauthorizedRoles as $role) {
            $user = User::factory()->create(['role' => $role]);

            $response = $this->actingAs($user)->get(route($route));
            $response->assertStatus(403);
        }
    }

    #[DataProvider('routesWithRolesProvider')]
    public function test_group5_routes_redirect_guest(string $route, array $allowedRoles): void
    {
        $response = $this->get(route($route));
        $response->assertRedirect('/login');
    }

    public static function routesWithRolesProvider(): array
    {
        return [
            ['control_tower.index', ['admin', 'logistics_admin', 'planner', 'auditor']],
            ['control_tower.promises', ['admin', 'logistics_admin', 'planner', 'auditor']],
            ['esg.index', ['admin', 'esg_officer', 'auditor']],
            ['b2b.index', ['admin', 'b2b_buyer', 'procurement']],
            ['agri.index', ['admin', 'farmer', 'procurement']],
            ['epc.index', ['admin', 'epc_manager', 'asset_manager']],
            ['plm.index', ['admin', 'rnd_specialist']],
            ['integration.index', ['admin']],
            ['integration.webhooks', ['admin']],
        ];
    }
}
