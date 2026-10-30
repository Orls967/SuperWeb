<?php

declare(strict_types=1);

namespace Tests\Acceptance\Mobility\Eksplorasi;

use App\Models\User;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Modules\Ev\Application\Actions\SettleTimeOfUseChargingSessionAction;
use Modules\Ev\Application\Actions\StartOcppSmartChargingSessionAction;
use Modules\Ev\Domain\Models\EvCharger;
use Modules\Ev\Domain\Models\EvStation;
use Modules\Fleet\Application\Actions\RecognizeMonthlyLeaseInterestAndRouAmortizationAction;
use Modules\Fleet\Domain\Models\FleetContract;
use Modules\Telematics\Application\Actions\IngestTelemetryAndCalculateRollingBaselineAction;
use Modules\Telematics\Domain\Models\TelematicsDevice;
use Tests\TestCase;

class MobilityOldCodeExplorationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);
    }

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

    public function test_eksplorasi_telematics_lama_tanpa_geofencing_rtree_dan_append_only_meter(): void
    {
        $vehicleId = $this->createDummyVehicle();

        $device = TelematicsDevice::create([
            'device_id' => 'DEV-OLD-001',
            'vehicle_id' => $vehicleId,
            'serial_number' => 'SN-OLD-999888',
            'protocol' => 'OBD2_CAN',
            'status' => 'active',
            'last_heartbeat_at' => now(),
        ]);

        $action = app(IngestTelemetryAndCalculateRollingBaselineAction::class);
        $res = $action->handle(
            deviceId: 'DEV-OLD-001',
            vehicleId: $vehicleId,
            tickData: [
                'rpm' => 2500,
                'speed_kmh' => 60.0,
                'oil_temp_c' => 88.0,
                'fuel_level_pct' => 80.0,
                'battery_voltage' => 12.6,
                'dtc_code' => 'P0300',
            ]
        );

        $this->assertNotNull($res['tick']);
        $this->assertSame('active', $device->status);

        // Keterbatasan kode lama:
        // 1. Tidak memiliki R-tree spatial index untuk memeriksa apakah koordinat ada di dalam geofence.
        // 2. Evaluasi geofence tidak ada sama sekali atau O(N) linear scan seluruh poligon.
        $this->assertFalse(method_exists($action, 'evaluateGeofencesWithRTree'));
    }

    public function test_eksplorasi_ev_charging_lama_memakai_float_dan_tanpa_dinic_max_flow(): void
    {
        $station = EvStation::create([
            'station_code' => 'STN-OLD-01',
            'name' => 'Old Station Jakarta',
            'location_type' => 'mall',
            'address' => 'Jl. Sudirman No 1',
            'latitude' => -6.2,
            'longitude' => 106.8,
            'transformer_capacity_kw' => 500,
            'is_active' => true,
        ]);

        $charger = EvCharger::create([
            'station_id' => $station->id,
            'charger_code' => 'CHG-OLD-01',
            'type' => 'DC_FAST',
            'max_kw' => 50,
            'status' => 'available',
        ]);

        $user = User::factory()->create();
        $vehicleId = $this->createDummyVehicle();

        $startAction = app(StartOcppSmartChargingSessionAction::class);
        $startRes = $startAction->handle(
            chargerId: $charger->id,
            vehicleId: $vehicleId,
            userId: $user->id,
            depositHeldIdr: 50000,
            tariffPerKwhIdr: 2500
        );

        $session = $startRes['session'];
        $this->assertSame('charging', $session->status);

        // Keterbatasan kode lama:
        // 1. StartAction membuat session_code memakai date('YmdHis') sehingga tidak deterministik.
        $this->assertStringContainsString('SES-EV-', $session->session_code);

        // 2. SettleAction menghitung biaya memakai float division ($energyDeliveredWh / 1000.0) dan round()
        $settleAction = app(SettleTimeOfUseChargingSessionAction::class);
        $settleRes = $settleAction->handle(
            sessionId: $session->id,
            energyDeliveredWh: 15400,
            finalSohPct: 98.2,
            peakTempC: 34.5
        );

        $this->assertSame(38500, $settleRes['total_cost_idr']);
        // Tidak ada verifikasi Dinic Max Flow min-cut certificate untuk pembagian daya grid (O-6).
        $this->assertFalse(method_exists($startAction, 'verifyDinicCertificate'));
    }

    public function test_eksplorasi_fleet_leasing_amortisasi_berulang_tanpa_idempoten_dan_audit_commands(): void
    {
        $contract = FleetContract::create([
            'contract_number' => 'CTR-OLD-001',
            'party_id' => 1001,
            'total_units' => 0,
            'start_date' => now()->startOfMonth(),
            'end_date' => now()->addMonths(12)->endOfMonth(),
            'monthly_rental_idr' => 20000000,
            'total_lease_value_idr' => 240000000,
            'present_value_liability_idr' => 200000000,
            'remaining_liability_idr' => 200000000,
            'accumulated_amortized_idr' => 0,
            'security_deposit_idr' => 40000000,
            'max_downtime_hours_per_month' => 48,
            'requires_four_eyes' => false,
            'status' => 'ACTIVE',
            'duration_months' => 12,
        ]);

        $action = app(RecognizeMonthlyLeaseInterestAndRouAmortizationAction::class);

        // Amortisasi bulan pertama
        $res1 = $action->handle($contract->id, 840);
        $this->assertGreaterThan(0, $res1['principal_idr']);

        // Keterbatasan kode lama:
        // Pemanggilan kedua kali mengubah accumulated_amortized_idr kembali karena idempotency key
        // dibentuk dari mutable state ($contract->accumulated_amortized_idr) bukan (contract + periode).
        $cAfter1 = FleetContract::find($contract->id);
        $res2 = $action->handle($contract->id, 840);
        $cAfter2 = FleetContract::find($contract->id);

        // Terjadi double amortization yang mengubah sisa liabilitas
        $this->assertNotSame($cAfter1->remaining_liability_idr, $cAfter2->remaining_liability_idr);

        // Audit commands lama hanya mengecek status baris dasar, bukan rekonsiliasi dua sumber
        $exitTlm = Artisan::call('tlm:audit');
        $exitEv = Artisan::call('ev:audit');
        $exitFlt = Artisan::call('flt:audit');

        $this->assertSame(0, $exitTlm);
        $this->assertSame(0, $exitEv);
        $this->assertSame(0, $exitFlt);
    }
}
