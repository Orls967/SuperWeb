<?php

declare(strict_types=1);

namespace Modules\Telematics\tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Telematics\Application\Actions\DispatchEmergencyBreakdownTowAction;
use Modules\Telematics\Application\Actions\EvaluateCriticalDtcAndGroundVehicleAction;
use Modules\Telematics\Application\Actions\IngestTelemetryAndCalculateRollingBaselineAction;
use Modules\Telematics\Application\Actions\ScoreDriverBehaviorAndSafetyIndexAction;
use Modules\Telematics\Domain\Models\TelematicsBaseline;
use Modules\Telematics\Domain\Models\TelematicsDevice;
use Modules\Telematics\Domain\Models\TelematicsTick;
use Tests\TestCase;

class TelematicsEnterpriseDeepeningTest extends TestCase
{
    use RefreshDatabase;

    private function createDummyVehicle(): int
    {
        $user = User::factory()->create();

        return (int) DB::table('core_vehicles')->insertGetId([
            'user_id' => $user->id,
            'plate_number' => 'B '.rand(1000, 9999).' TST',
            'vin' => 'VIN-'.uniqid(),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_ingest_telemetry_calculates_rolling_baseline_accurately(): void
    {
        $action = app(IngestTelemetryAndCalculateRollingBaselineAction::class);
        $vehicleId = $this->createDummyVehicle();

        $device = TelematicsDevice::create([
            'device_id' => 'DEV-TEST-001',
            'vehicle_id' => $vehicleId,
            'serial_number' => 'SN-001',
            'protocol' => 'OBD2_CAN',
            'status' => 'active',
        ]);

        $result = $action->handle('DEV-TEST-001', $vehicleId, [
            'rpm' => 3200,
            'speed_kmh' => 80.0,
            'oil_temp_c' => 95.0,
            'battery_voltage' => 12.8,
            'fuel_level_pct' => 60.0,
        ]);

        $this->assertInstanceOf(TelematicsTick::class, $result['tick']);
        $this->assertInstanceOf(TelematicsBaseline::class, $result['baseline']);
        $this->assertEquals(95.0, $result['baseline']->avg_oil_temp_c);
        $this->assertEquals(12.8, $result['baseline']->avg_battery_voltage);
        $this->assertEquals(1, $result['sample_count']);
    }

    public function test_critical_dtc_evaluation_grounds_device_and_records_audit(): void
    {
        $action = app(EvaluateCriticalDtcAndGroundVehicleAction::class);
        $vehicleId = $this->createDummyVehicle();

        $device = TelematicsDevice::create([
            'device_id' => 'DEV-CRIT-999',
            'vehicle_id' => $vehicleId,
            'serial_number' => 'SN-CRIT-999',
            'protocol' => 'OBD2_CAN',
            'status' => 'active',
        ]);

        $result = $action->handle('DEV-CRIT-999', 'P0217');

        $this->assertTrue($result['grounded']);
        $this->assertEquals('critical', $result['severity']);
        $this->assertEquals('grounded', $device->fresh()->status);
    }

    public function test_advisory_dtc_does_not_ground_device(): void
    {
        $action = app(EvaluateCriticalDtcAndGroundVehicleAction::class);
        $vehicleId = $this->createDummyVehicle();

        $device = TelematicsDevice::create([
            'device_id' => 'DEV-ADV-111',
            'vehicle_id' => $vehicleId,
            'serial_number' => 'SN-ADV-111',
            'protocol' => 'OBD2_CAN',
            'status' => 'active',
        ]);

        $result = $action->handle('DEV-ADV-111', 'P0133');

        $this->assertFalse($result['grounded']);
        $this->assertEquals('advisory', $result['severity']);
        $this->assertEquals('active', $device->fresh()->status);
    }

    public function test_driver_behavior_scoring_calculates_safety_index_and_risk_tier(): void
    {
        $action = app(ScoreDriverBehaviorAndSafetyIndexAction::class);
        $vehicleId = $this->createDummyVehicle();

        // Populate ticks with 2 overspeed events and 3 high-RPM events
        for ($i = 0; $i < 5; $i++) {
            TelematicsTick::create([
                'device_id' => 'DEV-SCORE-01',
                'vehicle_id' => $vehicleId,
                'sequence' => $i + 1,
                'recorded_at' => now()->subMinutes($i * 10),
                'rpm' => $i < 3 ? 5000 : 2500, // 3 harsh events
                'speed_kmh' => $i < 2 ? 115.0 : 80.0, // 2 overspeeds
                'oil_temp_c' => 90.0,
                'battery_voltage' => 12.5,
                'fuel_level_pct' => 50.0,
                'idempotency_key' => "tick-score-{$i}-".uniqid(),
            ]);
        }

        $result = $action->handle($vehicleId, 7);

        // Deductions: 2*5 + 3*3 = 19 => 100 - 19 = 81
        $this->assertEquals(81, $result['safety_score']);
        $this->assertEquals('MODERATE', $result['risk_tier']);
        $this->assertEquals(2, $result['overspeed_events']);
        $this->assertEquals(3, $result['harsh_events']);
    }

    public function test_emergency_breakdown_tow_dispatch_creates_audit_and_grounds_vehicle(): void
    {
        $action = app(DispatchEmergencyBreakdownTowAction::class);
        $vehicleId = $this->createDummyVehicle();

        TelematicsDevice::create([
            'device_id' => 'DEV-TOW-505',
            'vehicle_id' => $vehicleId,
            'serial_number' => 'SN-TOW-505',
            'protocol' => 'OBD2_CAN',
            'status' => 'active',
        ]);

        $result = $action->handle($vehicleId, 'KM 12 Tol Cikampek', 'Transmisi macet dan overheat', 850000);

        $this->assertStringStartsWith('TOW-'.str_pad((string) $vehicleId, 5, '0', STR_PAD_LEFT).'-', $result['dispatch_id']);
        $this->assertEquals(850000, $result['fee_idr']);
        $this->assertEquals('DISPATCHED', $result['status']);
        $this->assertEquals('grounded', TelematicsDevice::where('vehicle_id', $vehicleId)->first()->status);
    }

    public function test_fleet_operations_routes_render_and_handle_requests(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $vehicleId = $this->createDummyVehicle();

        TelematicsDevice::create([
            'device_id' => 'DEV-OP-01',
            'vehicle_id' => $vehicleId,
            'serial_number' => 'SN-OP-01',
            'protocol' => 'OBD2_CAN',
            'status' => 'grounded',
        ]);

        $response = $this->actingAs($admin)->get(route('telematics.operations.baselines'));
        $response->assertStatus(200);
        $response->assertSee('Rolling Baseline Kendaraan');

        $response = $this->actingAs($admin)->get(route('telematics.operations.dtc-alerts'));
        $response->assertStatus(200);
        $response->assertSee('Monitoring Kerusakan Mesin OBD-II');

        $response = $this->actingAs($admin)->get(route('telematics.operations.driver-scores'));
        $response->assertStatus(200);
        $response->assertSee('Index Keselamatan Pengemudi Armada');

        $response = $this->actingAs($admin)->get(route('telematics.operations.emergency-dispatches'));
        $response->assertStatus(200);
        $response->assertSee('Panggilan Derek Darurat Otomatis');

        $postResponse = $this->actingAs($admin)->post(route('telematics.operations.evaluate-dtc'), [
            'device_id' => 'DEV-OP-01',
            'dtc_code' => 'P0606',
        ]);
        $postResponse->assertRedirect(route('telematics.operations.dtc-alerts'));
    }
}
