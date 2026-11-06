<?php

declare(strict_types=1);

namespace Tests\Acceptance\EgyMicrogridResilience;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Egy\database\seeders\EgyRbacSeeder;
use Tests\TestCase;

final class EgyMicrogridAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_route_access_matrix_for_microgrid_and_bess(): void
    {
        $this->seed();
        (new EgyRbacSeeder)->run();

        $gridOperator = User::factory()->create();
        $gridOperator->assignRole('grid_operator');

        $customer = User::factory()->create();
        $customer->assignRole('customer');

        // 1. Grid operator berhak mengakses microgrid controllers index
        $response = $this->actingAs($gridOperator)->get(route('egy.microgrid.controllers.index'));
        $response->assertStatus(200);

        // 2. Customer dilarang mengakses microgrid controllers (403 Forbidden)
        $forbiddenResponse = $this->actingAs($customer)->get(route('egy.microgrid.controllers.index'));
        $forbiddenResponse->assertStatus(403);

        // 3. Grid operator berhak mengakses BESS index
        $bessResponse = $this->actingAs($gridOperator)->get(route('egy.bess.index'));
        $bessResponse->assertStatus(200);

        // 4. Customer dilarang mengakses BESS index (403 Forbidden)
        $customerBessResponse = $this->actingAs($customer)->get(route('egy.bess.index'));
        $customerBessResponse->assertStatus(403);
    }
}
