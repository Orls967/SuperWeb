<?php

declare(strict_types=1);

namespace Modules\Mobility\Fleet\tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Contracts\Vehicle;
use Modules\Mobility\Fleet\Application\Actions\BillExcessMileageAndWearTearAction;
use Modules\Mobility\Fleet\Application\Actions\OptimizeFleetTcoReplacementScheduleAction;
use Modules\Mobility\Fleet\Application\Actions\OriginatePsak73LeaseWithRouAndPvLiabilityAction;
use Modules\Mobility\Fleet\Application\Actions\RecognizeMonthlyLeaseInterestAndRouAmortizationAction;
use Modules\Mobility\Fleet\Domain\Models\FleetContract;
use Modules\Mobility\Fleet\Domain\Models\FleetContractUnit;
use Tests\TestCase;

class FleetEnterpriseDeepeningTest extends TestCase
{
    use RefreshDatabase;

    private function createDummyVehicle(): int
    {
        $user = User::factory()->create();

        return (int) Vehicle::insertGetId([
            'user_id' => $user->id,
            'plate_number' => 'B '.rand(1000, 9999).' FLT',
            'vin' => 'VIN-FLT-'.uniqid(),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_originate_psak73_lease_calculates_pv_liability_accurately_with_integer_precision(): void
    {
        $action = app(OriginatePsak73LeaseWithRouAndPvLiabilityAction::class);

        // 36 months, 10,000,000 IDR / month, 840 bps IBR (8.4% p.a.)
        $result = $action->handle('FLT-TEST-001', 1, 36, 10000000, 840, 20000000, 24);

        $contract = $result['contract'];
        $this->assertEquals(360000000, $result['total_lease_value_idr']);
        $this->assertGreaterThan(0, $result['pv_liability_idr']);
        // Discounted PV must be strictly less than nominal total lease value
        $this->assertLessThan($result['total_lease_value_idr'], $result['pv_liability_idr']);
        $this->assertEquals($result['pv_liability_idr'], $contract->remaining_liability_idr);
        $this->assertFalse($contract->requires_four_eyes); // PV < 500M
    }

    public function test_originate_large_lease_sets_four_eyes_requirement(): void
    {
        $action = app(OriginatePsak73LeaseWithRouAndPvLiabilityAction::class);

        // 36 months, 25,000,000 IDR / month => PV > 500M
        $result = $action->handle('FLT-LARGE-001', 2, 36, 25000000, 840, 50000000, 12);

        $this->assertTrue($result['contract']->requires_four_eyes);
    }

    public function test_recognize_monthly_lease_interest_and_rou_amortization_decreases_remaining_liability(): void
    {
        $originateAction = app(OriginatePsak73LeaseWithRouAndPvLiabilityAction::class);
        $amortizeAction = app(RecognizeMonthlyLeaseInterestAndRouAmortizationAction::class);

        $originate = $originateAction->handle('FLT-AMORT-001', 1, 36, 10000000, 840);
        $contract = $originate['contract'];
        $initialRemaining = $contract->remaining_liability_idr;

        $amortizeResult = $amortizeAction->handle($contract->id, 840);

        $this->assertGreaterThan(0, $amortizeResult['interest_idr']);
        $this->assertGreaterThan(0, $amortizeResult['principal_idr']);
        $this->assertGreaterThan(0, $amortizeResult['rou_depreciation_idr']);
        $this->assertEquals($initialRemaining - $amortizeResult['principal_idr'], $amortizeResult['remaining_liability_idr']);
        $this->assertEquals($amortizeResult['principal_idr'], $contract->fresh()->accumulated_amortized_idr);
    }

    public function test_bill_excess_mileage_and_wear_tear_calculates_total_billed_idr(): void
    {
        $vehicleId = $this->createDummyVehicle();
        $contract = FleetContract::create([
            'contract_number' => 'FLT-UNIT-001',
            'party_id' => 1,
            'duration_months' => 36,
            'total_units' => 1,
            'monthly_rental_idr' => 10000000,
            'total_lease_value_idr' => 360000000,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonths(36)->toDateString(),
            'status' => 'active',
        ]);

        $unit = FleetContractUnit::create([
            'contract_id' => $contract->id,
            'vehicle_id' => $vehicleId,
            'baseline_odometer_km' => 10000,
            'current_odometer_km' => 10000,
            'max_km_per_year' => 30000,
            'downtime_hours_recorded' => 5,
            'status' => 'active',
        ]);

        $action = app(BillExcessMileageAndWearTearAction::class);

        // Actual return: 45,000 km. Baseline: 10,000. Driven: 35,000 km. Cap: 30,000. Excess: 5,000 km.
        // Rate: 2500 IDR/km => 12,500,000 IDR. Wear & tear: 1,500,000 IDR. Total: 14,000,000 IDR.
        $result = $action->handle($unit->id, 45000, 30000, 2500, 1500000, 'Minor bumper scratches repaired');

        $this->assertEquals(5000, $result['excess_km']);
        $this->assertEquals(12500000, $result['excess_mileage_fee_idr']);
        $this->assertEquals(1500000, $result['wear_tear_fee_idr']);
        $this->assertEquals(14000000, $result['total_billed_idr']);
        $this->assertEquals(45000, $unit->fresh()->current_odometer_km);
    }

    public function test_optimize_fleet_tco_replacement_identifies_high_mileage_units(): void
    {
        $vehicleId = $this->createDummyVehicle();
        $contract = FleetContract::create([
            'contract_number' => 'FLT-TCO-001',
            'party_id' => 1,
            'duration_months' => 36,
            'total_units' => 1,
            'monthly_rental_idr' => 10000000,
            'total_lease_value_idr' => 360000000,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonths(36)->toDateString(),
            'status' => 'active',
        ]);

        FleetContractUnit::create([
            'contract_id' => $contract->id,
            'vehicle_id' => $vehicleId,
            'baseline_odometer_km' => 0,
            'current_odometer_km' => 120000, // > 100,000 km threshold
            'max_km_per_year' => 30000,
            'downtime_hours_recorded' => 55, // > 48 hours threshold
            'status' => 'active',
        ]);

        $action = app(OptimizeFleetTcoReplacementScheduleAction::class);
        $result = $action->handle($contract->id);

        $this->assertEquals('REPLACE_CRITICAL_UNITS', $result['recommendation']);
        $this->assertCount(1, $result['replace_now_unit_ids']);
        $this->assertEquals(15000000, $result['projected_savings_idr']);
    }

    public function test_fleet_lifecycle_http_routes_render_and_handle_requests(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get(route('fleet.lifecycle.psak73-leases'));
        $response->assertStatus(200);
        $response->assertSee('Fleet PSAK 73');

        $response = $this->actingAs($admin)->get(route('fleet.lifecycle.amortization-schedule'));
        $response->assertStatus(200);
        $response->assertSee('Fleet Lease Amortization');

        $response = $this->actingAs($admin)->get(route('fleet.lifecycle.excess-mileage'));
        $response->assertStatus(200);
        $response->assertSee('Fleet Excess Mileage');

        $response = $this->actingAs($admin)->get(route('fleet.lifecycle.tco-optimization'));
        $response->assertStatus(200);
        $response->assertSee('Fleet TCO');

        $postResponse = $this->actingAs($admin)->post(route('fleet.lifecycle.originate-psak73'), [
            'contract_number' => 'FLT-HTTP-001',
            'party_id' => 1,
            'duration_months' => 36,
            'monthly_rental_idr' => 12000000,
            'annual_ibr_bps' => 840,
        ]);
        $postResponse->assertRedirect(route('fleet.lifecycle.psak73-leases'));
    }
}
