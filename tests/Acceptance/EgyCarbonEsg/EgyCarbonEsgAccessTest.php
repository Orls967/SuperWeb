<?php

declare(strict_types=1);

namespace Tests\Acceptance\EgyCarbonEsg;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Egy\database\seeders\EgyRbacSeeder;
use Tests\TestCase;

final class EgyCarbonEsgAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_route_access_matrix_for_carbon_rec_and_esg(): void
    {
        $this->seed();
        (new EgyRbacSeeder)->run();

        $admin = User::factory()->create();
        $carbonTrader = User::factory()->create();
        $renewableDev = User::factory()->create();
        $exportOfficer = User::factory()->create();
        $customer = User::factory()->create();
        $unauthorized = User::factory()->create();

        $admin->assignRole('admin');
        $carbonTrader->assignRole('carbon_trader');
        $renewableDev->assignRole('renewable_dev');
        $exportOfficer->assignRole('export_officer');
        $customer->assignRole('customer');

        // 1. /energy/carbon (Carbon Trader & Admin)
        $this->actingAs($carbonTrader)->get(route('egy.carbon.index'))->assertOk();
        $this->actingAs($admin)->get(route('egy.carbon.index'))->assertOk();
        $this->actingAs($unauthorized)->get(route('egy.carbon.index'))->assertForbidden();

        // 2. /energy/rec (Renewable Dev & Admin)
        $this->actingAs($renewableDev)->get(route('egy.rec.index'))->assertOk();
        $this->actingAs($admin)->get(route('egy.rec.index'))->assertOk();
        $this->actingAs($unauthorized)->get(route('egy.rec.index'))->assertForbidden();

        // 3. /energy/cbam (Export Officer & Admin)
        $this->actingAs($exportOfficer)->get(route('egy.cbam.index'))->assertOk();
        $this->actingAs($admin)->get(route('egy.cbam.index'))->assertOk();
        $this->actingAs($unauthorized)->get(route('egy.cbam.index'))->assertForbidden();

        // 4. /energy/esg-marketplace (Customer, Auditor, Admin)
        $this->actingAs($customer)->get(route('egy.esg.marketplace.index'))->assertOk();
        $this->actingAs($admin)->get(route('egy.esg.marketplace.index'))->assertOk();
        $this->actingAs($unauthorized)->get(route('egy.esg.marketplace.index'))->assertForbidden();
    }
}
