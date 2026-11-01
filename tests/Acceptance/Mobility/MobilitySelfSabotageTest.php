<?php

declare(strict_types=1);

namespace Tests\Acceptance\Mobility;

use App\Models\User;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Modules\Banking\Contracts\Ledger;
use Modules\Banking\Support\Posting;
use Modules\Ev\Application\Actions\RecordEvMeterValueAction;
use Modules\Ev\Application\Actions\StartEvChargingSessionAction;
use Modules\Ev\Application\DTOs\SmartChargingPlanDTO;
use Modules\Ev\Application\Services\FlowCutCertificateVerifier;
use Modules\Ev\Domain\Models\EvCharger;
use Modules\Ev\Domain\Models\EvStation;
use Modules\Fleet\Application\Actions\BillMonthlyFleetLeaseAction;
use Modules\Fleet\Database\Seeders\MobilityRbacSeeder;
use Modules\Fleet\Domain\Models\FleetContract;
use Modules\Fleet\Domain\Models\FleetContractUnit;
use Modules\Telematics\Application\Actions\ClearVehicleGroundingAction;
use Modules\Telematics\Application\Actions\IngestTelematicsTickAction;
use Modules\Telematics\Domain\Models\TelematicsDevice;
use Tests\TestCase;

class MobilitySelfSabotageTest extends TestCase
{
    use RefreshDatabase;

    private Ledger $ledger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);
        $this->seed(MobilityRbacSeeder::class);
        $this->ledger = app(Ledger::class);
    }

    public function test_sabotase_1_pemalsuan_sertifikat_min_cut_smart_charging_ditolak_verifier(): void
    {
        $verifier = app(FlowCutCertificateVerifier::class);

        // Kasus kapasitas trafo 60 kW, namun penyerang memalsukan sertifikat min cut seolah kapasitas 100 kW
        $fakePlan = new SmartChargingPlanDTO(
            allocations: ['SES-01' => 60000],
            deficits: [],
            totalAllocatedWh: 60000,
            totalDeficitWh: 0,
            certificate: [
                'cut_capacity_wh' => 100000, // PALSU
                'max_flow_wh' => 60000,
            ]
        );

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Min-cut certificate is invalid');

        $verifier->verifyOrThrow($fakePlan);
    }

    public function test_sabotase_2_manipulasi_meter_value_mundur_ditolak_action_dan_tertangkap_audit(): void
    {
        $station = EvStation::create([
            'station_code' => 'SPKLU-SAB-01',
            'name' => 'SPKLU Sabotage Test',
            'timezone' => 'Asia/Jakarta',
            'max_grid_power_kw' => 100,
            'location_type' => 'mall',
        ]);

        $charger = EvCharger::create([
            'station_id' => $station->id,
            'charger_code' => 'CHG-SAB-01',
            'charger_type' => 'DC_FAST',
            'max_power_kw' => 50,
            'status' => 'AVAILABLE',
        ]);

        $user = User::factory()->create();
        $vehicleId = (int) DB::table('core_vehicles')->insertGetId([
            'user_id' => $user->id,
            'plate_number' => 'B 9999 SAB',
            'vin' => 'VIN-SAB-01',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $startAction = app(StartEvChargingSessionAction::class);
        $meterAction = app(RecordEvMeterValueAction::class);

        $session = $startAction->execute($charger->id, $vehicleId, $user->id, 50000, now());

        // Rekam meter pertama: 10.000 Wh
        $meterAction->execute($session->id, now(), 10000, 15000);

        // Sabotase: coba rekam meteran mundur ke 8.000 Wh via Action
        try {
            $meterAction->execute($session->id, now()->addMinutes(15), 8000, 15000);
            $this->fail('Harusnya melempar InvalidArgumentException untuk meter value non-monotonik.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('monotonically non-decreasing', $e->getMessage());
        }

        // Sabotase jalur belakang: menyuntikkan data meteran mundur langsung ke DB
        DB::table('oto_ev_meter_values')->insert([
            'session_id' => $session->id,
            'read_at' => now()->addMinutes(15),
            'energy_wh' => 8000, // MUNDUR
            'power_w' => 15000,
            'idempotency_key' => 'sabotage-meter-key',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Audit ev:audit dan mob:audit WAJIB menangkap korupsi ini
        $exitEv = Artisan::call('ev:audit');
        $this->assertSame(1, $exitEv, 'ev:audit harus mendeteksi korupsi nilai meteran mundur.');

        $exitMob = Artisan::call('mob:audit');
        $this->assertSame(1, $exitMob, 'mob:audit harus mendeteksi korupsi nilai meteran mundur.');
    }

    public function test_sabotase_3_append_only_fakta_menolak_update_dan_delete_pada_meter_dan_tick(): void
    {
        $user = User::factory()->create();
        $vehicleId = (int) DB::table('core_vehicles')->insertGetId([
            'user_id' => $user->id,
            'plate_number' => 'B 7777 SAB',
            'vin' => 'VIN-SAB-02',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $device = TelematicsDevice::create([
            'device_id' => 'DEV-SAB-01',
            'vehicle_id' => $vehicleId,
            'serial_number' => 'SN-SAB-01',
            'token_hash' => hash('sha256', 'tok-sab'),
            'protocol' => 'OBD2_CAN',
            'status' => 'ACTIVE',
        ]);

        $ingestAction = app(IngestTelematicsTickAction::class);
        $tick = $ingestAction->execute(
            device: $device,
            token: 'tok-sab',
            sequence: 1,
            recordedAt: now(),
            coolantTempDeciC: 850
        );

        // 1. Sabotase update tick telemetri
        try {
            $tick->update(['coolant_temp_deci_c' => 999]);
            $this->fail('TelematicsTick harus menolak update (PR5 append-only).');
        } catch (\LogicException $e) {
            $this->assertStringContainsString('append-only', $e->getMessage());
        }

        // 2. Sabotase delete tick telemetri
        try {
            $tick->delete();
            $this->fail('TelematicsTick harus menolak delete (PR5 append-only).');
        } catch (\LogicException $e) {
            $this->assertStringContainsString('append-only', $e->getMessage());
        }
    }

    public function test_sabotase_4_selisih_invoice_sewa_vs_saldo_ledger_tertangkap_audit(): void
    {
        $contract = FleetContract::create([
            'contract_number' => 'CTR-SAB-01',
            'party_id' => 9001,
            'start_date' => '2026-10-01',
            'end_date' => '2026-12-31',
            'duration_months' => 3,
            'monthly_rental_idr' => 20000000,
            'total_lease_value_idr' => 60000000,
            'total_units' => 1,
            'status' => 'ACTIVE',
        ]);

        $user = User::factory()->create();
        $vehId = (int) DB::table('core_vehicles')->insertGetId([
            'user_id' => $user->id,
            'plate_number' => 'B 6666 SAB',
            'vin' => 'VIN-SAB-03',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        FleetContractUnit::create([
            'contract_id' => $contract->id,
            'vehicle_id' => $vehId,
            'status' => 'ASSIGNED',
            'assigned_at' => now(),
        ]);

        $billAction = app(BillMonthlyFleetLeaseAction::class);
        $invoice = $billAction->execute($contract, '2026-10');

        // Kondisi awal sehat: saldo AR (-20.000.000) cocok dengan total tagihan invoice (20.000.000)
        $this->assertSame(0, Artisan::call('flt:audit'));

        // Sabotase: mengubah total_billed_idr di tabel invoice tanpa posting ledger (pencurian pembukuan)
        DB::table('oto_fleet_invoices')
            ->where('id', $invoice->id)
            ->update(['total_billed_idr' => 5000000]); // Disunat 15 jt

        // Audit flt:audit dan mob:audit WAJIB menangkap ketidakseimbangan ini
        $exitFlt = Artisan::call('flt:audit');
        $this->assertSame(1, $exitFlt, 'flt:audit harus mendeteksi korupsi manipulasi invoice sewa.');

        $exitMob = Artisan::call('mob:audit');
        $this->assertSame(1, $exitMob, 'mob:audit harus mendeteksi korupsi manipulasi invoice sewa.');
    }

    public function test_sabotase_5_clear_grounding_tanpa_alasan_atau_bukan_mekanik_ditolak(): void
    {
        $action = app(ClearVehicleGroundingAction::class);

        // Percobaan menghapus grounding tanpa catatan alasan
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Reason notes are mandatory');

        $action->execute(101, 1, '   ');
    }
}
