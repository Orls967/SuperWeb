<?php

declare(strict_types=1);

namespace Modules\Telematics\tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\AutoServe\Domain\Models\Booking;
use Modules\Core\Domain\Models\Vehicle;
use Modules\Telematics\Application\Services\TelematicsIngestService;
use Modules\Telematics\Domain\Models\TelematicsDevice;
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
            'dtc_code' => 'P0300', // Random Misfire
        ];

        $service->ingestTick($tickData);

        $this->device->refresh();
        $this->assertEquals('grounded', $this->device->status);
    }
}
