<?php

declare(strict_types=1);

namespace Tests\Acceptance\EgyUtilities;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Egy\database\seeders\EgyRbacSeeder;
use Tests\TestCase;

final class EgyUtilitiesAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_route_access_matrix_for_district_utilities(): void
    {
        $this->seed();
        (new EgyRbacSeeder)->run();

        $utilityOp = User::factory()->create();
        $energyBilling = User::factory()->create();
        $energyAuditor = User::factory()->create();
        $unauthorized = User::factory()->create();

        $utilityOp->assignRole('utility_operator');
        $energyBilling->assignRole('energy_billing');
        $energyAuditor->assignRole('energy_auditor');
        $unauthorized->assignRole('customer');

        // 1. /energy/water (utility_operator)
        $this->actingAs($utilityOp)->get(route('egy.water.index'))->assertOk();
        $this->actingAs($unauthorized)->get(route('egy.water.index'))->assertForbidden();

        // 2. /energy/waste (utility_operator)
        $this->actingAs($utilityOp)->get(route('egy.waste.index'))->assertOk();
        $this->actingAs($unauthorized)->get(route('egy.waste.index'))->assertForbidden();

        // 3. /energy/district-cooling (utility_operator)
        $this->actingAs($utilityOp)->get(route('egy.cooling.index'))->assertOk();
        $this->actingAs($unauthorized)->get(route('egy.cooling.index'))->assertForbidden();

        // 4. /energy/consolidated-bills (energy_billing)
        $this->actingAs($energyBilling)->get(route('egy.bills.consolidated.index'))->assertOk();
        $this->actingAs($unauthorized)->get(route('egy.bills.consolidated.index'))->assertForbidden();

        // 5. /energy/esco (energy_auditor)
        $this->actingAs($energyAuditor)->get(route('egy.esco.index'))->assertOk();
        $this->actingAs($unauthorized)->get(route('egy.esco.index'))->assertForbidden();
    }
}
