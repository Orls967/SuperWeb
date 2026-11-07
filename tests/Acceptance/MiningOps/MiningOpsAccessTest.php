<?php

declare(strict_types=1);

namespace Tests\Acceptance\MiningOps;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Mining\database\seeders\MiningRbacSeeder;
use Tests\TestCase;

class MiningOpsAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_route_access_matrix_for_mining_operations(): void
    {
        $this->seed();
        (new MiningRbacSeeder)->run();

        $routes = [
            ['url' => '/mining/plans', 'authorized' => ['admin', 'mine_planner'], 'unauthorized' => ['contractor']],
            ['url' => '/mining/dispatch', 'authorized' => ['admin', 'dispatcher_mine'], 'unauthorized' => ['finance_officer']],
            ['url' => '/mining/equipment', 'authorized' => ['admin', 'mine_manager'], 'unauthorized' => ['contractor']],
            ['url' => '/mining/fuel', 'authorized' => ['admin', 'finance_officer', 'hse_officer'], 'unauthorized' => ['mine_planner']],
            ['url' => '/contractor/mining', 'authorized' => ['admin', 'contractor'], 'unauthorized' => ['mine_planner']],
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
