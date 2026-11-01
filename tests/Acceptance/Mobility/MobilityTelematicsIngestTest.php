<?php

declare(strict_types=1);

namespace Tests\Acceptance\Mobility;

use App\Models\User;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Mobility\Telematics\Application\Actions\IngestTelematicsTickAction;
use Modules\Mobility\Telematics\Domain\Models\TelematicsDevice;
use Modules\Mobility\Telematics\Domain\Models\TelematicsSequenceGap;
use Modules\Mobility\Telematics\Domain\Models\TelematicsTick;
use Tests\TestCase;

class MobilityTelematicsIngestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);
    }

    private function createDevice(string $deviceId = 'DEV-001', string $token = 'secret-token-123'): TelematicsDevice
    {
        $user = User::factory()->create();
        $vehicleId = (int) DB::table('core_vehicles')->insertGetId([
            'user_id' => $user->id,
            'plate_number' => 'B 1234 MOB',
            'vin' => 'VIN-MOB-001',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return TelematicsDevice::create([
            'device_id' => $deviceId,
            'vehicle_id' => $vehicleId,
            'serial_number' => 'SN-'.$deviceId,
            'token_hash' => hash('sha256', $token),
            'protocol' => 'OBD2_CAN',
            'status' => 'ACTIVE',
            'last_heartbeat_at' => now(),
        ]);
    }

    public function test_ingest_tick_dengan_satuan_integer_murni(): void
    {
        $device = $this->createDevice();
        $action = app(IngestTelematicsTickAction::class);

        $tick = $action->execute(
            device: $device,
            token: 'secret-token-123',
            sequence: 1,
            recordedAt: now(),
            latitudeE6: -6200000,
            longitudeE6: 106816666,
            rpm: 2800,
            speedKmh: 75,
            coolantTempDeciC: 885, // 88.5 C
            batteryMv: 12650,      // 12.65 V
            fuelPermil: 805,       // 80.5%
            odometerMeters: 145200 // 145.2 km
        );

        $this->assertInstanceOf(TelematicsTick::class, $tick);
        $this->assertSame(885, $tick->coolant_temp_deci_c);
        $this->assertSame(12650, $tick->battery_mv);
        $this->assertSame(805, $tick->fuel_permil);
        $this->assertSame(145200, $tick->odometer_meters);
    }

    public function test_sensor_hilang_tetap_null_tanpa_default_siluman(): void
    {
        $device = $this->createDevice();
        $action = app(IngestTelematicsTickAction::class);

        // Sensor tidak mengirim suhu, tegangan, dan BBM
        $tick = $action->execute(
            device: $device,
            token: 'secret-token-123',
            sequence: 1,
            recordedAt: now(),
            latitudeE6: -6200000,
            longitudeE6: 106816666,
            rpm: 1000,
            speedKmh: 0,
            coolantTempDeciC: null,
            batteryMv: null,
            fuelPermil: null,
            odometerMeters: 50000
        );

        $this->assertNull($tick->coolant_temp_deci_c, 'Suhu harus NULL bukan default 90 C');
        $this->assertNull($tick->battery_mv, 'Tegangan harus NULL bukan default 12.6 V');
        $this->assertNull($tick->fuel_permil, 'BBM harus NULL bukan default 100%');
    }

    public function test_ingest_duplikat_idempoten_mengembalikan_tick_yang_sama(): void
    {
        $device = $this->createDevice();
        $action = app(IngestTelematicsTickAction::class);

        $tick1 = $action->execute(
            device: $device,
            token: 'secret-token-123',
            sequence: 1,
            recordedAt: now(),
            latitudeE6: -6200000,
            longitudeE6: 106816666,
            rpm: 2000,
            speedKmh: 40,
            coolantTempDeciC: 850,
            batteryMv: 12500,
            fuelPermil: 700,
            odometerMeters: 100000
        );

        $tick2 = $action->execute(
            device: $device,
            token: 'secret-token-123',
            sequence: 1,
            recordedAt: now(),
            latitudeE6: -6200000,
            longitudeE6: 106816666,
            rpm: 2000,
            speedKmh: 40,
            coolantTempDeciC: 850,
            batteryMv: 12500,
            fuelPermil: 700,
            odometerMeters: 100000
        );

        $this->assertSame($tick1->id, $tick2->id);
        $this->assertSame(1, TelematicsTick::where('device_id', $device->device_id)->count());
    }

    public function test_terima_tick_di_luar_urutan_out_of_order(): void
    {
        $device = $this->createDevice();
        $action = app(IngestTelematicsTickAction::class);

        // Sequence 3 datang lebih dulu
        $tick3 = $action->execute(
            device: $device,
            token: 'secret-token-123',
            sequence: 3,
            recordedAt: now()->addSeconds(20),
            latitudeE6: -6200000,
            longitudeE6: 106816666,
            rpm: 2000,
            speedKmh: 50,
            coolantTempDeciC: 850,
            batteryMv: 12500,
            fuelPermil: 700,
            odometerMeters: 100200
        );

        // Sequence 1 datang belakangan
        $tick1 = $action->execute(
            device: $device,
            token: 'secret-token-123',
            sequence: 1,
            recordedAt: now(),
            latitudeE6: -6200000,
            longitudeE6: 106816666,
            rpm: 1500,
            speedKmh: 30,
            coolantTempDeciC: 840,
            batteryMv: 12500,
            fuelPermil: 705,
            odometerMeters: 100000
        );

        $this->assertNotNull($tick3);
        $this->assertNotNull($tick1);
        $this->assertSame(2, TelematicsTick::where('device_id', $device->device_id)->count());
    }

    public function test_celah_sequence_dicatat_pada_tabel_gaps(): void
    {
        $device = $this->createDevice();
        $action = app(IngestTelematicsTickAction::class);

        // Sequence 1 datang
        $action->execute(
            device: $device,
            token: 'secret-token-123',
            sequence: 1,
            recordedAt: now(),
            latitudeE6: -6200000,
            longitudeE6: 106816666,
            rpm: 1000,
            speedKmh: 0,
            coolantTempDeciC: 800,
            batteryMv: 12400,
            fuelPermil: 600,
            odometerMeters: 100000
        );

        // Sequence 4 langsung datang (gap 2 dan 3 hilang)
        $action->execute(
            device: $device,
            token: 'secret-token-123',
            sequence: 4,
            recordedAt: now()->addSeconds(30),
            latitudeE6: -6200000,
            longitudeE6: 106816666,
            rpm: 2500,
            speedKmh: 60,
            coolantTempDeciC: 870,
            batteryMv: 12600,
            fuelPermil: 590,
            odometerMeters: 100500
        );

        $gap = TelematicsSequenceGap::where('device_id', $device->device_id)->first();
        $this->assertNotNull($gap);
        $this->assertSame(2, $gap->expected_sequence);
        $this->assertSame(4, $gap->received_sequence);
    }

    public function test_token_autentikasi_salah_ditolak(): void
    {
        $device = $this->createDevice();
        $action = app(IngestTelematicsTickAction::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid device token');

        $action->execute(
            device: $device,
            token: 'wrong-token-999',
            sequence: 1,
            recordedAt: now(),
            latitudeE6: -6200000,
            longitudeE6: 106816666,
            rpm: 1000,
            speedKmh: 0,
            coolantTempDeciC: 800,
            batteryMv: 12400,
            fuelPermil: 600,
            odometerMeters: 100000
        );
    }

    public function test_append_only_menolak_update_dan_delete_pada_tick(): void
    {
        $device = $this->createDevice();
        $action = app(IngestTelematicsTickAction::class);

        $tick = $action->execute(
            device: $device,
            token: 'secret-token-123',
            sequence: 1,
            recordedAt: now(),
            latitudeE6: -6200000,
            longitudeE6: 106816666,
            rpm: 1000,
            speedKmh: 0,
            coolantTempDeciC: 800,
            batteryMv: 12400,
            fuelPermil: 600,
            odometerMeters: 100000
        );

        $this->expectException(\LogicException::class);
        $tick->update(['rpm' => 9999]);
    }

    public function test_append_only_menolak_delete_pada_tick(): void
    {
        $device = $this->createDevice();
        $action = app(IngestTelematicsTickAction::class);

        $tick = $action->execute(
            device: $device,
            token: 'secret-token-123',
            sequence: 1,
            recordedAt: now(),
            latitudeE6: -6200000,
            longitudeE6: 106816666,
            rpm: 1000,
            speedKmh: 0,
            coolantTempDeciC: 800,
            batteryMv: 12400,
            fuelPermil: 600,
            odometerMeters: 100000
        );

        $this->expectException(\LogicException::class);
        $tick->delete();
    }

    public function test_ingest_batch_ticks_memperbarui_last_heartbeat_at(): void
    {
        $device = $this->createDevice();
        $action = app(IngestTelematicsTickAction::class);

        $timeBefore = now()->subMinutes(10);
        $device->update(['last_heartbeat_at' => $timeBefore]);

        $now = now();
        $action->execute(
            device: $device,
            token: 'secret-token-123',
            sequence: 1,
            recordedAt: $now,
            latitudeE6: -6200000,
            longitudeE6: 106816666,
            rpm: 1000,
            speedKmh: 0,
            coolantTempDeciC: 800,
            batteryMv: 12400,
            fuelPermil: 600,
            odometerMeters: 100000
        );

        $this->assertTrue($device->fresh()->last_heartbeat_at->gte($now->subSecond()));
    }

    public function test_rate_limit_ingest_terlalu_sering_dari_sequence_sama(): void
    {
        $device = $this->createDevice();
        $action = app(IngestTelematicsTickAction::class);

        $t1 = $action->execute(
            device: $device,
            token: 'secret-token-123',
            sequence: 1,
            recordedAt: now(),
            latitudeE6: -6200000,
            longitudeE6: 106816666,
            rpm: 1000,
            speedKmh: 0,
            coolantTempDeciC: 800,
            batteryMv: 12400,
            fuelPermil: 600,
            odometerMeters: 100000
        );

        $this->assertNotNull($t1);
        $this->assertSame(1, $t1->sequence);
    }
}
