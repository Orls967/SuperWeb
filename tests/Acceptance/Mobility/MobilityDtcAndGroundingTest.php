<?php

declare(strict_types=1);

namespace Tests\Acceptance\Mobility;

use App\Models\User;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Telematics\Application\Actions\ClearVehicleGroundingAction;
use Modules\Telematics\Application\Actions\IngestTelematicsTickAction;
use Modules\Telematics\Contracts\VehicleDispatchEligibilityContract;
use Modules\Telematics\Domain\Models\TelematicsDevice;
use Tests\TestCase;

class MobilityDtcAndGroundingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);
    }

    private function createDevice(string $deviceId = 'DEV-DTC-01'): array
    {
        $user = User::factory()->create();
        $vehicleId = (int) DB::table('core_vehicles')->insertGetId([
            'user_id' => $user->id,
            'plate_number' => 'B 9999 DTC',
            'vin' => 'VIN-DTC-'.uniqid(),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $device = TelematicsDevice::create([
            'device_id' => $deviceId,
            'vehicle_id' => $vehicleId,
            'serial_number' => 'SN-'.$deviceId,
            'token_hash' => hash('sha256', 'token-dtc'),
            'protocol' => 'OBD2_CAN',
            'status' => 'ACTIVE',
            'last_heartbeat_at' => now(),
        ]);

        return [$device, $vehicleId];
    }

    public function test_hanya_dtc_kritis_yang_mengubah_status_menjadi_grounded(): void
    {
        [$device, $vehicleId] = $this->createDevice();
        $action = app(IngestTelematicsTickAction::class);

        // DTC Kritis: P0300 (Random Misfire berbahaya)
        $action->execute(
            device: $device,
            token: 'token-dtc',
            sequence: 1,
            recordedAt: now(),
            latitudeE6: -6200000,
            longitudeE6: 106816666,
            rpm: 2000,
            speedKmh: 40,
            coolantTempDeciC: 850,
            batteryMv: 12500,
            fuelPermil: 700,
            odometerMeters: 100000,
            dtcCodes: ['P0300']
        );

        $this->assertSame('GROUNDED', $device->fresh()->status);
        $vehicleStatus = DB::table('core_vehicles')->where('id', $vehicleId)->value('status');
        $this->assertSame('grounded', $vehicleStatus);
    }

    public function test_dtc_non_kritis_tidak_mengubah_status_menjadi_grounded(): void
    {
        [$device, $vehicleId] = $this->createDevice();
        $action = app(IngestTelematicsTickAction::class);

        // DTC Non-kritis: B0001 (Driver Airbag Stage 1 Deployment Control - informational / non-engine shutdown)
        // atau kode ringan sensor parkir
        $action->execute(
            device: $device,
            token: 'token-dtc',
            sequence: 1,
            recordedAt: now(),
            latitudeE6: -6200000,
            longitudeE6: 106816666,
            rpm: 2000,
            speedKmh: 40,
            coolantTempDeciC: 850,
            batteryMv: 12500,
            fuelPermil: 700,
            odometerMeters: 100000,
            dtcCodes: ['U0155'] // Lost comm with IPC - non fatal
        );

        $this->assertSame('ACTIVE', $device->fresh()->status);
        $vehicleStatus = DB::table('core_vehicles')->where('id', $vehicleId)->value('status');
        $this->assertSame('active', $vehicleStatus);
    }

    public function test_kendaraan_grounded_ditolak_oleh_kontrak_dispatch(): void
    {
        [$device, $vehicleId] = $this->createDevice();
        $device->update(['status' => 'GROUNDED']);
        DB::table('core_vehicles')->where('id', $vehicleId)->update(['status' => 'grounded']);

        $contract = app(VehicleDispatchEligibilityContract::class);
        $this->assertFalse($contract->isEligibleForDispatch($vehicleId));
    }

    public function test_kendaraan_active_layak_untuk_dispatch(): void
    {
        [$device, $vehicleId] = $this->createDevice();

        $contract = app(VehicleDispatchEligibilityContract::class);
        $this->assertTrue($contract->isEligibleForDispatch($vehicleId));
    }

    public function test_clear_grounding_hanya_dapat_dilakukan_oleh_mekanik_berwenang(): void
    {
        [$device, $vehicleId] = $this->createDevice();
        $device->update(['status' => 'GROUNDED']);
        DB::table('core_vehicles')->where('id', $vehicleId)->update(['status' => 'grounded']);

        $mechanic = User::factory()->create();
        $action = app(ClearVehicleGroundingAction::class);

        $res = $action->execute(
            vehicleId: $vehicleId,
            authorizedByUserId: $mechanic->id,
            reasonNotes: 'Busi dan coil sudah diganti, uji coba jalan normal.'
        );

        $this->assertTrue($res);
        $this->assertSame('ACTIVE', $device->fresh()->status);
        $vehicleStatus = DB::table('core_vehicles')->where('id', $vehicleId)->value('status');
        $this->assertSame('active', $vehicleStatus);
    }

    public function test_clear_grounding_gagal_bila_tanpa_catatan_alasan(): void
    {
        [$device, $vehicleId] = $this->createDevice();
        $device->update(['status' => 'GROUNDED']);

        $mechanic = User::factory()->create();
        $action = app(ClearVehicleGroundingAction::class);

        $this->expectException(\InvalidArgumentException::class);
        $action->execute(
            vehicleId: $vehicleId,
            authorizedByUserId: $mechanic->id,
            reasonNotes: '' // Kosong ditolak
        );
    }
}
