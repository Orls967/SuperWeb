<?php

declare(strict_types=1);

namespace Modules\Telematics\tests\Feature;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Modules\AutoServe\Domain\Models\Booking;
use Modules\Core\Domain\Models\Vehicle;
use Modules\Telematics\Application\Services\TelematicsIngestService;
use Modules\Telematics\Domain\Events\VehicleAnomalyDetected;
use Modules\Telematics\Domain\Models\TelematicsDevice;
use Modules\Telematics\Domain\Models\TelematicsTick;
use Tests\TestCase;

class TelematicsTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Vehicle $vehicle;

    protected TelematicsDevice $device;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        $this->user = User::factory()->create();
        $this->vehicle = Vehicle::create([
            'user_id' => $this->user->id,
            'plate_number' => 'B 1234 XYZ',
            'brand' => 'Toyota',
            'model' => 'Innova Zenix',
            'year' => 2023,
            'vin' => 'MHF12345678901234',
        ]);

        $this->device = TelematicsDevice::create([
            'device_id' => 'DEV-OBD-001',
            'vehicle_id' => $this->vehicle->id,
            'serial_number' => 'SN-882910',
            'protocol' => 'OBD2_CAN',
            'status' => 'active',
        ]);
    }

    public function test_telematics_tick_ingest_idempotency(): void
    {
        /** @var TelematicsIngestService $service */
        $service = app(TelematicsIngestService::class);

        $tickData = [
            'device_id' => 'DEV-OBD-001',
            'sequence' => 101,
            'recorded_at' => '2026-10-07 10:00:00',
            'speed_kmh' => 60.5,
            'rpm' => 2200,
            'oil_temp_c' => 91.0,
            'battery_voltage' => 12.6,
            'fuel_level_pct' => 85.0,
        ];

        $tick1 = $service->ingestTick($tickData);
        $tick2 = $service->ingestTick($tickData);

        $this->assertEquals($tick1->id, $tick2->id);
    }

    public function test_anomaly_triggers_draft_booking_once(): void
    {
        /** @var TelematicsIngestService $service */
        $service = app(TelematicsIngestService::class);

        // High oil temperature > 15% above 90.0C baseline => > 103.5C
        $tickData = [
            'device_id' => 'DEV-OBD-001',
            'sequence' => 102,
            'recorded_at' => '2026-10-07 10:05:00',
            'speed_kmh' => 80.0,
            'rpm' => 3000,
            'oil_temp_c' => 115.0, // High overheat
            'battery_voltage' => 12.5,
            'fuel_level_pct' => 80.0,
        ];

        $service->ingestTick($tickData);

        $bookings = Booking::where('vehicle_id', $this->vehicle->id)
            ->where('complaint', 'like', '%[PREDICTIVE_MAINTENANCE]%')
            ->get();

        $this->assertCount(1, $bookings);
        $this->assertStringContainsString('OIL_OVERHEAT', $bookings->first()->complaint);

        // Submitting another anomalous tick does not create duplicate booking if pending exists
        $tickData2 = [
            'device_id' => 'DEV-OBD-001',
            'sequence' => 103,
            'recorded_at' => '2026-10-07 10:10:00',
            'oil_temp_c' => 118.0,
            'battery_voltage' => 12.5,
            'fuel_level_pct' => 80.0,
        ];
        $service->ingestTick($tickData2);

        $this->assertEquals(1, Booking::where('vehicle_id', $this->vehicle->id)->where('complaint', 'like', '%[PREDICTIVE_MAINTENANCE]%')->count());
    }

    public function test_critical_dtc_grounds_vehicle_device(): void
    {
        /** @var TelematicsIngestService $service */
        $service = app(TelematicsIngestService::class);

        $tickData = [
            'device_id' => 'DEV-OBD-001',
            'sequence' => 104,
            'recorded_at' => '2026-10-07 10:15:00',
            'oil_temp_c' => 90.0,
            'battery_voltage' => 12.6,
            'fuel_level_pct' => 80.0,
            'dtc_code' => 'P0300', // Random Misfire (Critical)
        ];

        $service->ingestTick($tickData);

        $this->device->refresh();
        $this->assertEquals('grounded', $this->device->status);
    }

    public function test_non_critical_dtc_does_not_ground_device_and_does_not_trigger_critical_anomaly(): void
    {
        /** @var TelematicsIngestService $service */
        $service = app(TelematicsIngestService::class);

        $tickData = [
            'device_id' => 'DEV-OBD-001',
            'sequence' => 105,
            'recorded_at' => '2026-10-07 10:20:00',
            'oil_temp_c' => 90.0,
            'battery_voltage' => 12.6,
            'fuel_level_pct' => 80.0,
            'dtc_code' => 'P0420', // Catalyst System Efficiency Below Threshold (Advisory/Non-critical)
        ];

        $service->ingestTick($tickData);

        $this->device->refresh();
        // Non-critical DTC must NOT ground the vehicle device (status remains active)
        $this->assertEquals('active', $this->device->status);

        // Booking complaint should NOT be tagged with CRITICAL_DTC
        $booking = Booking::where('vehicle_id', $this->vehicle->id)->first();
        if ($booking) {
            $this->assertStringNotContainsString('CRITICAL_DTC', $booking->complaint);
        }
    }

    public function test_seven_day_rolling_baseline_calculated_from_actual_historical_ticks(): void
    {
        /** @var TelematicsIngestService $service */
        $service = app(TelematicsIngestService::class);

        $baseTime = Carbon::parse('2026-10-07 12:00:00');

        // Seed 4 historical ticks across the last 3 days with cool running engine (oil temp 70.0C)
        for ($i = 1; $i <= 4; $i++) {
            TelematicsTick::create([
                'device_id' => 'DEV-OBD-001',
                'vehicle_id' => $this->vehicle->id,
                'sequence' => 200 + $i,
                'recorded_at' => (clone $baseTime)->subDays(4 - $i)->setHour(10),
                'speed_kmh' => 50.0,
                'rpm' => 1800,
                'oil_temp_c' => 70.0,
                'battery_voltage' => 12.8,
                'fuel_level_pct' => 80.0,
                'idempotency_key' => "hist-tick-{$i}",
            ]);
        }

        // Calculate baseline: must reflect real historical ticks (avg = 70.0C, samples = 4)
        $baseline = $service->calculateRollingBaseline($this->vehicle->id, $baseTime);
        $this->assertEquals(4, $baseline->sample_count);
        $this->assertEquals(70.0, $baseline->avg_oil_temp_c);
        $this->assertEquals(12.8, $baseline->avg_battery_voltage);

        // Ingest a tick at 82.0C.
        // Against standard 90.0C, 82.0C is normal (82 < 103.5C).
        // Against this vehicle's true 70.0C baseline, 82.0C is > 15% overheat (82 > 80.5C).
        $tickData = [
            'device_id' => 'DEV-OBD-001',
            'sequence' => 210,
            'recorded_at' => $baseTime->toDateTimeString(),
            'oil_temp_c' => 82.0,
            'battery_voltage' => 12.8,
            'fuel_level_pct' => 80.0,
        ];
        $service->ingestTick($tickData);

        // Anomaly OIL_OVERHEAT is detected because of the real 70.0C rolling baseline
        $booking = Booking::where('vehicle_id', $this->vehicle->id)
            ->where('complaint', 'like', '%OIL_OVERHEAT%')
            ->first();

        $this->assertNotNull($booking);
        $this->assertStringContainsString('OIL_OVERHEAT', $booking->complaint);
    }

    public function test_anomaly_event_dispatched_after_commit_and_deduplicated_hourly(): void
    {
        /** @var TelematicsIngestService $service */
        $service = app(TelematicsIngestService::class);

        Event::fake([VehicleAnomalyDetected::class]);

        $tick1 = [
            'device_id' => 'DEV-OBD-001',
            'sequence' => 301,
            'recorded_at' => '2026-10-07 14:05:00',
            'oil_temp_c' => 115.0,
            'battery_voltage' => 12.5,
            'fuel_level_pct' => 80.0,
        ];
        $service->ingestTick($tick1);

        // Second tick with same anomaly in the same hour
        $tick2 = [
            'device_id' => 'DEV-OBD-001',
            'sequence' => 302,
            'recorded_at' => '2026-10-07 14:25:00',
            'oil_temp_c' => 120.0,
            'battery_voltage' => 12.5,
            'fuel_level_pct' => 80.0,
        ];
        $service->ingestTick($tick2);

        // Event should be dispatched exactly once due to hourly deduplication
        Event::assertDispatched(VehicleAnomalyDetected::class, 1);
    }
}
