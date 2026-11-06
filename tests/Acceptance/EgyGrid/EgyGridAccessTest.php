<?php

declare(strict_types=1);

namespace Tests\Acceptance\EgyGrid;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EgyGridAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_route_access_matrix_for_energy_grid_and_metering(): void
    {
        $this->seed();
        (new \Modules\Egy\database\seeders\EgyRbacSeeder())->run();

        $routes = [
            ['url' => '/energy/grid', 'authorized' => ['admin', 'grid_operator'], 'unauthorized' => ['customer']],
            ['url' => '/energy/dispatch', 'authorized' => ['admin', 'grid_operator'], 'unauthorized' => ['energy_billing']],
            ['url' => '/energy/meters', 'authorized' => ['admin', 'energy_billing'], 'unauthorized' => ['customer']],
            ['url' => '/energy/bills', 'authorized' => ['admin', 'energy_billing'], 'unauthorized' => ['grid_operator']],
            ['url' => '/energy/ppa', 'authorized' => ['admin', 'genco_trader'], 'unauthorized' => ['energy_billing']],
            ['url' => '/customer/energy', 'authorized' => ['admin', 'customer'], 'unauthorized' => ['grid_operator']],
        ];

        foreach ($routes as $r) {
            foreach ($r['authorized'] as $roleName) {
                $user = User::factory()->create();
                $user->assignRole($roleName);

                $response = $this->actingAs($user)->get($r['url']);
                $this->assertContains(
                    $response->getStatusCode(),
                    [200, 302],
                    "Role {$roleName} harus diizinkan (200/302) mengakses {$r['url']}, got {$response->getStatusCode()}"
                );
            }

            foreach ($r['unauthorized'] as $roleName) {
                $user = User::factory()->create();
                $user->assignRole($roleName);

                $response = $this->actingAs($user)->get($r['url']);
                $this->assertEquals(
                    403,
                    $response->getStatusCode(),
                    "Role {$roleName} harus ditolak (403) pada rute {$r['url']}, got {$response->getStatusCode()}"
                );
            }
        }
    }
}
