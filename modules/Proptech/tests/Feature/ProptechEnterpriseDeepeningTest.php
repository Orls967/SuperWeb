<?php

declare(strict_types=1);

namespace Modules\Proptech\tests\Feature;

use App\Models\User;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Proptech\Application\Actions\BookFlexSpaceHotDeskOrRoomAction;
use Modules\Proptech\Application\Actions\CalculateGreenBuildingRetrofitRoiAction;
use Modules\Proptech\Application\Actions\GenerateSmartLockAccessPinAction;
use Modules\Proptech\Application\Actions\HandoverEpcBimToDigitalTwinAction;
use Modules\Proptech\Application\Actions\OptimizeBmsHvacAndLightingSetpointAction;
use Modules\Proptech\Application\Actions\RunEmergencyEvacuationSimulationAction;
use Modules\Proptech\Domain\Models\BuildingZone;
use Modules\Proptech\Domain\Models\Flex\FlexBooking;
use Modules\Proptech\Domain\Models\Flex\FlexSpace;
use Tests\TestCase;

class ProptechEnterpriseDeepeningTest extends TestCase
{
    use RefreshDatabase;

    private BuildingZone $zone;

    private FlexSpace $space;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);

        $this->zone = BuildingZone::create([
            'zone_code' => 'ZONE-TEST-01',
            'building_code' => 'BLD-HQ-01',
            'name' => 'HQ Floor 4 East Wing',
            'floor_level' => 'L4',
            'area_sqm' => 450.0,
            'current_temp_c' => 25.0,
            'current_occupancy' => 15,
            'current_kwh_rate' => 1650,
            'baseline_kwh_per_hour' => 12.5,
            'status' => 'ACTIVE',
        ]);

        $this->space = FlexSpace::create([
            'space_code' => 'FLX-SUITE-01',
            'name' => 'Boardroom Executive Suite',
            'space_type' => 'MEETING_ROOM',
            'zone_id' => $this->zone->id,
            'capacity_persons' => 16,
            'rate_per_hour_idr' => 250000,
            'area_sqm' => 45.0,
            'status' => 'AVAILABLE',
        ]);

        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    public function test_optimize_bms_hvac_and_lighting_setpoint_action_executes_setback_when_unoccupied(): void
    {
        $action = app(OptimizeBmsHvacAndLightingSetpointAction::class);

        $result = $action->handle(
            zoneId: $this->zone->id,
            ambientTempC: 30.5,
            currentOccupancy: 0
        );

        $this->assertEquals(26.5, $result['target_temp_c']);
        $this->assertEquals('OFF', $result['lighting_mode']);
        $this->assertEquals(4.5, $result['estimated_kwh_saved']);
        $this->assertEquals(7425, $result['savings_idr']); // 4.5 * 1650

        $this->assertDatabaseHas('prp_hvac_commands', [
            'zone_id' => $this->zone->id,
            'command_type' => 'SETBACK',
        ]);
    }

    public function test_optimize_bms_hvac_action_cooling_surge_when_densely_occupied(): void
    {
        $action = app(OptimizeBmsHvacAndLightingSetpointAction::class);

        $result = $action->handle(
            zoneId: $this->zone->id,
            ambientTempC: 33.0,
            currentOccupancy: 35
        );

        $this->assertEquals(22.0, $result['target_temp_c']);
        $this->assertEquals('HIGH_EFFICIENCY_100', $result['lighting_mode']);

        $this->assertDatabaseHas('prp_hvac_commands', [
            'zone_id' => $this->zone->id,
            'command_type' => 'SET_TEMP',
            'target_value' => 22.0,
        ]);
    }

    public function test_calculate_green_building_retrofit_roi_action_computes_payback_and_carbon_offset(): void
    {
        $action = app(CalculateGreenBuildingRetrofitRoiAction::class);

        $result = $action->handle(
            zoneId: $this->zone->id,
            investmentCostIdr: 50000000,
            expectedKwhSavingsMonthly: 4000.0
        );

        $this->assertEquals(6600000, $result['monthly_savings_idr']); // 4000 * 1650
        $this->assertGreaterThan(0, $result['annual_carbon_offset_kg']);
        $this->assertEquals(8, $result['payback_months']); // ceil(50m / 6.6m) = 8
        $this->assertGreaterThan(0.0, $result['five_year_roi_pct']);

        $this->assertDatabaseHas('prp_prescriptive_ops', [
            'zone_id' => $this->zone->id,
            'status' => 'PROPOSED',
        ]);
    }

    public function test_handover_epc_bim_to_digital_twin_action_creates_versioned_model_with_hash_chain(): void
    {
        $action = app(HandoverEpcBimToDigitalTwinAction::class);

        $components = [
            ['component_code' => 'CHILLER-01', 'category' => 'HVAC_CHILLER', 'name' => 'Main Chiller A'],
            ['component_code' => 'DUCT-ZONE-4', 'category' => 'DUCTWORK', 'name' => 'Floor 4 Supply Duct'],
        ];

        $result = $action->handle(
            projectCode: 'PRJ-SMART-TOWER',
            modelName: 'Smart Tower As-Built v1',
            components: $components
        );

        $this->assertEquals(1, $result['version']);
        $this->assertEquals(2, $result['components_count']);
        $this->assertNotEmpty($result['current_hash']);

        $this->assertDatabaseHas('prp_bim_models', [
            'project_code' => 'PRJ-SMART-TOWER',
            'version' => 1,
        ]);

        $this->assertDatabaseHas('prp_twin_components', [
            'component_code' => 'CHILLER-01',
            'status' => 'OPERATIONAL',
            'completion_pct' => 100,
        ]);
    }

    public function test_run_emergency_evacuation_simulation_action_evaluates_egress_time_and_bottlenecks(): void
    {
        $action = app(RunEmergencyEvacuationSimulationAction::class);

        $result = $action->handle(
            zoneId: $this->zone->id,
            hazardType: 'FIRE',
            initialOccupancy: 95
        );

        $this->assertGreaterThan(30, $result['estimated_clearance_sec']);
        $this->assertGreaterThanOrEqual(1, $result['bottleneck_count']);
        $this->assertNotEmpty($result['safety_rating']);
        $this->assertNotEmpty($result['evacuation_status']);
    }

    public function test_book_flex_space_hot_desk_or_room_action_enforces_anti_overlap_and_deposit(): void
    {
        $action = app(BookFlexSpaceHotDeskOrRoomAction::class);

        $result = $action->handle(
            flexSpaceId: $this->space->id,
            userId: $this->admin->id,
            startTime: '2026-11-20 09:00:00',
            endTime: '2026-11-20 12:00:00',
            depositAmountIdr: 150000
        );

        $this->assertStringStartsWith('BKG-PRP-', $result['booking']->booking_code);
        $this->assertEquals(750000, $result['total_price_idr']); // 3 hrs * 250k
        $this->assertEquals(150000, $result['deposit_amount_idr']);

        $this->assertDatabaseHas('prp_flex_bookings', [
            'flex_space_id' => $this->space->id,
            'status' => 'CONFIRMED',
        ]);

        // Attempt overlapping booking -> must throw RuntimeException
        $this->expectException(\RuntimeException::class);
        $action->handle(
            flexSpaceId: $this->space->id,
            userId: $this->admin->id,
            startTime: '2026-11-20 10:00:00',
            endTime: '2026-11-20 14:00:00',
            depositAmountIdr: 100000
        );
    }

    public function test_generate_smart_lock_access_pin_action_issues_cryptographic_pin(): void
    {
        $booking = FlexBooking::create([
            'booking_code' => 'BKG-PRP-LOCK01',
            'flex_space_id' => $this->space->id,
            'user_id' => $this->admin->id,
            'start_time' => now()->addHour(),
            'end_time' => now()->addHours(3),
            'deposit_amount_idr' => 100000,
            'total_price_idr' => 500000,
            'status' => 'CONFIRMED',
        ]);

        $action = app(GenerateSmartLockAccessPinAction::class);
        $result = $action->handle(
            flexBookingId: $booking->id,
            passportHash: 'deadbeef1234567890abcdef'
        );

        $this->assertMatchesRegularExpression('/^\d{6}$/', $result['pin_code']);
        $this->assertNotEmpty($result['door_relay_token']);

        $booking->refresh();
        $this->assertEquals('CHECKED_IN', $booking->status);
        $this->assertEquals('deadbeef1234567890abcdef', $booking->checked_in_passport_hash);
    }

    public function test_proptech_intelligence_http_endpoints_workflow(): void
    {
        // 1. BMS page
        $bmsGet = $this->actingAs($this->admin)->get(route('proptech.intelligence.bms'));
        $bmsGet->assertOk();
        $bmsGet->assertSeeText('Dynamic HVAC');

        // 2. BMS optimize post
        $bmsPost = $this->actingAs($this->admin)->post(route('proptech.intelligence.bms.optimize'), [
            'zone_id' => $this->zone->id,
            'ambient_temp_c' => 31.0,
            'current_occupancy' => 8,
        ]);
        $bmsPost->assertRedirect(route('proptech.intelligence.bms'));
        $bmsPost->assertSessionHas('success');

        // 3. Retrofit page & post
        $retroGet = $this->actingAs($this->admin)->get(route('proptech.intelligence.retrofit'));
        $retroGet->assertOk();

        $retroPost = $this->actingAs($this->admin)->post(route('proptech.intelligence.retrofit.calculate'), [
            'zone_id' => $this->zone->id,
            'investment_cost_idr' => 80000000,
            'expected_kwh_savings_monthly' => 6000,
        ]);
        $retroPost->assertRedirect(route('proptech.intelligence.retrofit'));
        $retroPost->assertSessionHas('success');

        // 4. Flex booking page
        $flexGet = $this->actingAs($this->admin)->get(route('proptech.intelligence.flex-booking'));
        $flexGet->assertOk();
    }
}
