<?php

declare(strict_types=1);

namespace Tests\Acceptance\Mobility;

use App\Models\User;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Modules\Banking\Contracts\Ledger;
use Modules\Mobility\Ev\Domain\Models\EvCharger;
use Modules\Mobility\Ev\Domain\Models\EvSession;
use Modules\Mobility\Ev\Domain\Models\EvStation;
use Modules\Mobility\Fleet\Domain\Models\FleetContract;
use Modules\Mobility\Fleet\Domain\Models\FleetInvoice;
use Modules\Mobility\Telematics\Domain\Models\TelematicsBaseline;
use Modules\Mobility\Telematics\Domain\Models\TelematicsDevice;
use Modules\Mobility\Telematics\Domain\Models\TelematicsTick;
use Tests\TestCase;

class MobilityAuditCommandTest extends TestCase
{
    use RefreshDatabase;

    private Ledger $ledger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);
        $this->ledger = app(Ledger::class);
    }

    public function test_audit_bersih_exit_0_pada_kondisi_data_sehat(): void
    {
        $exitMob = Artisan::call('mob:audit');
        $this->assertSame(0, $exitMob);

        $exitTlm = Artisan::call('tlm:audit');
        $this->assertSame(0, $exitTlm);

        $exitEv = Artisan::call('ev:audit');
        $this->assertSame(0, $exitEv);

        $exitFlt = Artisan::call('flt:audit');
        $this->assertSame(0, $exitFlt);
    }

    public function test_deteksi_korupsi_duplikasi_sequence_telemetri(): void
    {
        $user = User::factory()->create();
        $vehicleId = (int) DB::table('core_vehicles')->insertGetId([
            'user_id' => $user->id,
            'plate_number' => 'B 1111 COR',
            'vin' => 'VIN-COR-1',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $device = TelematicsDevice::create([
            'device_id' => 'DEV-COR-01',
            'vehicle_id' => $vehicleId,
            'serial_number' => 'SN-COR-01',
            'token_hash' => hash('sha256', 'tok'),
            'protocol' => 'OBD2_CAN',
            'status' => 'ACTIVE',
        ]);

        // Injeksi korupsi: 2 tick dengan sequence sama pada perangkat yang sama via direct query
        DB::table('oto_telematics_ticks')->insert([
            'device_id' => $device->device_id,
            'vehicle_id' => $vehicleId,
            'sequence' => 5,
            'recorded_at' => now(),
            'coolant_temp_deci_c' => 850,
            'battery_mv' => 12500,
            'idempotency_key' => 'corrupt-key-1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('oto_telematics_ticks')->insert([
            'device_id' => $device->device_id,
            'vehicle_id' => $vehicleId,
            'sequence' => 5, // DUPLIKAT SEQUENCE
            'recorded_at' => now(),
            'coolant_temp_deci_c' => 850,
            'battery_mv' => 12500,
            'idempotency_key' => 'corrupt-key-2',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $exitCode = Artisan::call('tlm:audit');
        $this->assertSame(1, $exitCode);
    }

    public function test_deteksi_korupsi_baseline_telemetri_palsu_tidak_sesuai_tick_nyata(): void
    {
        $user = User::factory()->create();
        $vehicleId = (int) DB::table('core_vehicles')->insertGetId([
            'user_id' => $user->id,
            'plate_number' => 'B 2222 COR',
            'vin' => 'VIN-COR-2',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $device = TelematicsDevice::create([
            'device_id' => 'DEV-COR-02',
            'vehicle_id' => $vehicleId,
            'serial_number' => 'SN-COR-02',
            'token_hash' => hash('sha256', 'tok'),
            'protocol' => 'OBD2_CAN',
            'status' => 'ACTIVE',
        ]);

        // Masukkan data tick nyata (median 850)
        TelematicsTick::create([
            'device_id' => $device->device_id,
            'vehicle_id' => $vehicleId,
            'sequence' => 1,
            'recorded_at' => now()->subDay(),
            'coolant_temp_deci_c' => 850,
            'battery_mv' => 12500,
            'idempotency_key' => 'tick-real-1',
        ]);

        // Injeksi baseline palsu (misal median dipalsukan menjadi 9999)
        TelematicsBaseline::create([
            'vehicle_id' => $vehicleId,
            'metric' => 'coolant_temp_deci_c',
            'median_value' => 9999, // PALSU
            'mad_value' => 0,
            'sample_count' => 1,
            'calculated_at' => now(),
            'window_days' => 7,
        ]);

        $exitCode = Artisan::call('tlm:audit');
        $this->assertSame(1, $exitCode);
    }

    public function test_deteksi_korupsi_selisih_capture_ev_revenue_vs_total_sesi(): void
    {
        $station = EvStation::create([
            'station_code' => 'SPKLU-COR-01',
            'name' => 'SPKLU Corrupt Audit',
            'timezone' => 'Asia/Jakarta',
            'max_grid_power_kw' => 100,
            'location_type' => 'mall',
        ]);

        $charger = EvCharger::create([
            'station_id' => $station->id,
            'charger_code' => 'CHG-COR-01',
            'charger_type' => 'DC_FAST',
            'max_power_kw' => 50,
            'status' => 'AVAILABLE',
        ]);

        $user = User::factory()->create();
        $vehicleId = (int) DB::table('core_vehicles')->insertGetId([
            'user_id' => $user->id,
            'plate_number' => 'B 3333 COR',
            'vin' => 'VIN-COR-3',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Sesi completed dengan biaya 50.000 IDR
        EvSession::create([
            'session_code' => 'SES-COR-99',
            'charger_id' => $charger->id,
            'vehicle_id' => $vehicleId,
            'user_id' => $user->id,
            'status' => 'COMPLETED',
            'total_cost_idr' => 50000,
            'deposit_held_idr' => 50000,
            'started_at' => now()->subHour(),
            'completed_at' => now(),
        ]);

        // Namun di ledger saldo oto:ev_charging_revenue:IDR tetap 0 (belum di-capture / dicuri)
        $exitCode = Artisan::call('ev:audit');
        $this->assertSame(1, $exitCode);
    }

    public function test_deteksi_korupsi_meter_value_menurun_non_monotonik(): void
    {
        $station = EvStation::create([
            'station_code' => 'SPKLU-COR-02',
            'name' => 'SPKLU Corrupt Meter',
            'timezone' => 'Asia/Jakarta',
            'max_grid_power_kw' => 100,
            'location_type' => 'mall',
        ]);

        $charger = EvCharger::create([
            'station_id' => $station->id,
            'charger_code' => 'CHG-COR-02',
            'charger_type' => 'DC_FAST',
            'max_power_kw' => 50,
            'status' => 'AVAILABLE',
        ]);

        $user = User::factory()->create();
        $vehicleId = (int) DB::table('core_vehicles')->insertGetId([
            'user_id' => $user->id,
            'plate_number' => 'B 4444 COR',
            'vin' => 'VIN-COR-4',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $session = EvSession::create([
            'session_code' => 'SES-MTR-DOWN',
            'charger_id' => $charger->id,
            'vehicle_id' => $vehicleId,
            'user_id' => $user->id,
            'status' => 'CHARGING',
            'deposit_held_idr' => 50000,
            'started_at' => now()->subHour(),
        ]);

        // Meter 1: 5000 Wh
        DB::table('oto_ev_meter_values')->insert([
            'session_id' => $session->id,
            'read_at' => now()->subMinutes(30),
            'energy_wh' => 5000,
            'power_w' => 10000,
            'idempotency_key' => 'mtr-1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        // Meter 2: 3000 Wh (MUNDUR / ANOMALI)
        DB::table('oto_ev_meter_values')->insert([
            'session_id' => $session->id,
            'read_at' => now()->subMinutes(15),
            'energy_wh' => 3000,
            'power_w' => 10000,
            'idempotency_key' => 'mtr-2',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $exitCode = Artisan::call('ev:audit');
        $this->assertSame(1, $exitCode);
    }

    public function test_deteksi_korupsi_selisih_invoice_fleet_vs_saldo_ar_ledger(): void
    {
        $contract = FleetContract::create([
            'contract_number' => 'CTR-COR-01',
            'party_id' => 8888,
            'monthly_rental_idr' => 20000000,
            'total_lease_value_idr' => 240000000,
            'duration_months' => 12,
            'start_date' => now()->startOfMonth(),
            'end_date' => now()->addMonths(12)->endOfMonth(),
            'max_downtime_hours_per_month' => 48,
            'status' => 'ACTIVE',
        ]);

        // Invoice dibuat di tabel tanpa posting ke ledger (atau posting disabotase)
        FleetInvoice::create([
            'invoice_number' => 'INV-COR-888',
            'contract_id' => $contract->id,
            'billing_period' => '2026-10',
            'rental_fee_idr' => 20000000,
            'penalty_credit_idr' => 0,
            'total_billed_idr' => 20000000,
            'idempotency_key' => 'inv-corrupt-key',
            'status' => 'ISSUED',
        ]);

        // Saldo AR di ledger adalah 0 (selisih 20 juta)
        $exitCode = Artisan::call('flt:audit');
        $this->assertSame(1, $exitCode);
    }

    public function test_deteksi_korupsi_jadwal_amortisasi_psak73_tidak_balance(): void
    {
        $contract = FleetContract::create([
            'contract_number' => 'CTR-COR-02',
            'party_id' => 7777,
            'monthly_rental_idr' => 10000000,
            'total_lease_value_idr' => 120000000,
            'duration_months' => 12,
            'start_date' => now()->startOfMonth(),
            'end_date' => now()->addMonths(12)->endOfMonth(),
            'max_downtime_hours_per_month' => 48,
            'status' => 'ACTIVE',
        ]);

        // Injeksi jadwal amortisasi rusak: pokok + bunga != pembayaran
        DB::table('oto_fleet_amortization_schedules')->insert([
            'contract_id' => $contract->id,
            'period_month' => 1,
            'principal_idr' => 5000000,
            'interest_idr' => 3000000, // Total 8 jt != payment 10 jt (selisih 2 jt)
            'payment_idr' => 10000000,
            'ending_liability_idr' => 95000000,
            'is_posted' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $exitCode = Artisan::call('flt:audit');
        $this->assertSame(1, $exitCode);
    }
}
