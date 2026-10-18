<?php

declare(strict_types=1);

namespace Modules\Fleet\tests\Feature;

use App\Models\User;
use App\Quality\Audit\Fixtures\FltAuditCorruptionFixture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Modules\Fleet\database\seeders\FleetDemoSeeder;
use Modules\Fleet\Domain\Models\FleetContract;
use Modules\Fleet\Domain\Models\FleetContractUnit;
use Tests\TestCase;

class FleetVerticalSliceHttpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        (new FleetDemoSeeder)->run();
    }

    public function test_a_authorized_user_can_access_fleet_index_and_amortize(): void
    {
        $manager = User::factory()->create(['role' => 'lease_manager']);
        $contract = FleetContract::firstOrFail();

        // 1. Access index
        $response = $this->actingAs($manager)->get(route('fleet.index'));
        $response->assertOk();
        $response->assertSeeText('B2B Fleet');
        $response->assertSee($contract->contract_number);

        // 2. Amortize month 1
        $amortizeResponse = $this->actingAs($manager)->post(route('fleet.contracts.amortize', $contract), [
            'month_index' => 1,
        ]);

        $amortizeResponse->assertRedirect(route('fleet.index'));
        $amortizeResponse->assertSessionHas('success');

        $this->assertSame(
            $contract->monthly_rental_idr,
            $contract->fresh()->accumulated_amortized_idr
        );
    }

    public function test_b_unauthorized_role_is_forbidden_403(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $driver = User::factory()->create(['role' => 'driver']);
        $operator = User::factory()->create(['role' => 'station_operator']);

        $this->actingAs($customer)->get(route('fleet.index'))->assertForbidden();
        $this->actingAs($driver)->get(route('fleet.index'))->assertForbidden();
        $this->actingAs($operator)->get(route('fleet.index'))->assertForbidden();
    }

    public function test_c_unauthenticated_user_is_redirected_to_login_302(): void
    {
        $response = $this->get(route('fleet.index'));
        $response->assertRedirect('/login');
    }

    public function test_d_two_source_fleet_audit_passes_cleanly(): void
    {
        $code = Artisan::call('flt:audit');
        $this->assertSame(0, $code);
    }

    public function test_e_flt_audit_fails_when_data_is_corrupted(): void
    {
        $fixture = new FltAuditCorruptionFixture;
        $fixture->corrupt();

        $code = Artisan::call('flt:audit');
        $this->assertSame(1, $code);
    }

    public function test_f_sla_claim_endpoint_role_restrictions(): void
    {
        $admin = User::factory()->create(['role' => 'fleet_admin']);
        $customer = User::factory()->create(['role' => 'customer']);
        $contract = FleetContract::firstOrFail();
        $unit = FleetContractUnit::where('contract_id', $contract->id)->firstOrFail();

        // Unauthorized
        $this->actingAs($customer)->post(route('fleet.contracts.sla-claim', $contract), [
            'vehicle_id' => $unit->vehicle_id,
            'incident_month' => '2026-08',
            'telematics_grounded_hours' => 30,
            'workshop_service_hours' => 5,
        ])->assertForbidden();

        // Authorized
        $response = $this->actingAs($admin)->post(route('fleet.contracts.sla-claim', $contract), [
            'vehicle_id' => $unit->vehicle_id,
            'incident_month' => '2026-08',
            'telematics_grounded_hours' => 30,
            'workshop_service_hours' => 5,
        ]);

        $response->assertRedirect(route('fleet.index'));
        $response->assertSessionHas('success');
    }

    public function test_g_return_unit_endpoint_role_restrictions(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $driver = User::factory()->create(['role' => 'driver']);
        $contract = FleetContract::firstOrFail();
        $unit = FleetContractUnit::where('contract_id', $contract->id)->firstOrFail();

        // Unauthorized
        $this->actingAs($driver)->post(route('fleet.contracts.return-unit', $contract), [
            'vehicle_id' => $unit->vehicle_id,
            'condition_notes' => 'Siap dikembalikan ke AutoDex',
        ])->assertForbidden();

        // Authorized
        $response = $this->actingAs($admin)->post(route('fleet.contracts.return-unit', $contract), [
            'vehicle_id' => $unit->vehicle_id,
            'condition_notes' => 'Siap dikembalikan ke AutoDex',
        ]);

        $response->assertRedirect(route('fleet.index'));
        $response->assertSessionHas('success');
    }
}
