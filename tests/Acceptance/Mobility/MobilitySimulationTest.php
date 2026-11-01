<?php

declare(strict_types=1);

namespace Tests\Acceptance\Mobility;

use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Banking\Contracts\Ledger;
use Modules\Mobility\Ev\Application\Actions\RecordEvMeterValueAction;
use Modules\Mobility\Ev\Application\Actions\SettleEvChargingSessionAction;
use Modules\Mobility\Ev\Application\Actions\StartEvChargingSessionAction;
use Modules\Mobility\Ev\Domain\Models\EvCharger;
use Modules\Mobility\Ev\Domain\Models\EvStation;
use Modules\Mobility\Ev\Domain\Models\EvTariffWindow;
use Modules\Mobility\Fleet\Application\Actions\AssignVehicleToContractAction;
use Modules\Mobility\Fleet\Application\Actions\BillMonthlyFleetLeaseAction;
use Modules\Mobility\Fleet\Application\Actions\CreditSlaPenaltyAction;
use Modules\Mobility\Fleet\Application\Services\Psak73AmortizationEngine;
use Modules\Mobility\Fleet\database\seeders\MobilityRbacSeeder;
use Modules\Mobility\Fleet\Domain\Models\FleetAmortizationSchedule;
use Modules\Mobility\Fleet\Domain\Models\FleetContract;
use Modules\Mobility\Fleet\Domain\Models\FleetContractUnit;
use Modules\Mobility\Fleet\Domain\Models\FleetSlaBreach;
use Modules\Mobility\Telematics\Application\Actions\IngestTelematicsTickAction;
use Modules\Mobility\Telematics\Application\Actions\RecomputeRobustBaselineAction;
use Modules\Mobility\Telematics\Domain\Models\TelematicsBaseline;
use Modules\Mobility\Telematics\Domain\Models\TelematicsDevice;
use Tests\TestCase;

class MobilitySimulationTest extends TestCase
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

    public function test_simulasi_90_hari_200_kendaraan_50_perangkat_3_spklu_600_sesi_dan_3_kontrak_leasing(): void
    {
        // Tetapkan seed pseudorandom untuk determinisme mutlak (PR3)
        mt_srand(424242);

        $startTime = Carbon::parse('2026-08-01 00:00:00', 'Asia/Jakarta');

        // ==========================================
        // 1. SETUP MASTER DATA MOBILITY
        // ==========================================

        // Buat 10 driver / users
        $users = [];
        for ($u = 1; $u <= 10; $u++) {
            $user = User::factory()->create([
                'name' => "Driver {$u}",
                'email' => "driver{$u}@example.com",
            ]);
            $user->assignRole('driver');
            $users[] = $user;
        }

        // Buat 200 kendaraan core_vehicles
        $vehicleIds = [];
        for ($v = 1; $v <= 200; $v++) {
            $vehicleId = (int) DB::table('core_vehicles')->insertGetId([
                'user_id' => $users[($v - 1) % 10]->id,
                'plate_number' => sprintf('B %04d MOB', $v),
                'vin' => sprintf('VIN-MOB-90D-%04d', $v),
                'status' => 'active',
                'created_at' => $startTime,
                'updated_at' => $startTime,
            ]);
            $vehicleIds[] = $vehicleId;
        }

        // Pasang 50 perangkat telemetri pada 50 kendaraan pertama
        $devices = [];
        for ($d = 1; $d <= 50; $d++) {
            $device = TelematicsDevice::create([
                'device_id' => sprintf('DEV-TEL-%03d', $d),
                'vehicle_id' => $vehicleIds[$d - 1],
                'serial_number' => sprintf('SN-TEL-%03d', $d),
                'token_hash' => hash('sha256', "token-sim-{$d}"),
                'protocol' => 'OBD2_CAN',
                'status' => 'ACTIVE',
            ]);
            $devices[] = $device;
        }

        // Setup 3 SPKLU (Jakarta WIB, Surabaya WIB, Denpasar WITA)
        $stationsData = [
            ['code' => 'SPKLU-JKT-CENTRAL', 'name' => 'SPKLU Jakarta Central Hub', 'tz' => 'Asia/Jakarta', 'power' => 300],
            ['code' => 'SPKLU-SBY-AIRPORT', 'name' => 'SPKLU Surabaya Airport Depot', 'tz' => 'Asia/Jakarta', 'power' => 200],
            ['code' => 'SPKLU-DPS-KUTA', 'name' => 'SPKLU Bali Kuta Gateway', 'tz' => 'Asia/Makassar', 'power' => 250],
        ];

        $stations = [];
        $allChargers = [];

        foreach ($stationsData as $sData) {
            $station = EvStation::create([
                'station_code' => $sData['code'],
                'name' => $sData['name'],
                'timezone' => $sData['tz'],
                'max_grid_power_kw' => $sData['power'],
                'location_type' => 'hub',
                'address' => "Terminal Area {$sData['code']}",
            ]);

            // Windows tarif TOU
            EvTariffWindow::create([
                'station_id' => $station->id,
                'name' => 'Off-Peak Day',
                'start_time' => '00:00',
                'end_time' => '17:00',
                'rate_per_kwh_idr' => 2000,
                'is_cross_midnight' => false,
            ]);
            EvTariffWindow::create([
                'station_id' => $station->id,
                'name' => 'Peak Hours',
                'start_time' => '17:00',
                'end_time' => '22:00',
                'rate_per_kwh_idr' => 3500,
                'is_cross_midnight' => false,
            ]);
            EvTariffWindow::create([
                'station_id' => $station->id,
                'name' => 'Off-Peak Night',
                'start_time' => '22:00',
                'end_time' => '04:00',
                'rate_per_kwh_idr' => 1800,
                'is_cross_midnight' => true,
            ]);

            // 6 Charger per stasiun (total 18 charger)
            for ($c = 1; $c <= 6; $c++) {
                $charger = EvCharger::create([
                    'station_id' => $station->id,
                    'charger_code' => sprintf('%s-CHG-%02d', $sData['code'], $c),
                    'charger_type' => $c <= 2 ? 'DC_ULTRA' : 'DC_FAST',
                    'max_power_kw' => $c <= 2 ? 100 : 50,
                    'status' => 'AVAILABLE',
                ]);
                $allChargers[] = $charger;
            }

            $stations[] = $station;
        }

        // ==========================================
        // 2. SIMULASI TELEMETRI (BATCH REPRESENTATIF 90 HARI)
        // ==========================================
        $ingestAction = app(IngestTelematicsTickAction::class);
        $recomputeAction = app(RecomputeRobustBaselineAction::class);

        // Ingest sequence tick bertahap untuk membuktikan deteksi baseline dan kesehatan telemetri
        for ($d = 0; $d < 5; $d++) {
            $dev = $devices[$d];
            for ($seq = 1; $seq <= 7; $seq++) {
                $tickTime = now()->subDays(7 - $seq)->setTime(8, 0, 0);
                $ingestAction->execute(
                    device: $dev,
                    token: 'token-sim-'.($d + 1),
                    sequence: $seq,
                    recordedAt: $tickTime,
                    coolantTempDeciC: 840 + ($seq * 2),
                    batteryMv: 12500,
                    fuelPermil: 800 - ($seq * 10)
                );
            }
            $recomputeAction->execute($dev->vehicle_id, 'coolant_temp_deci_c', 7);
        }

        $baseline = TelematicsBaseline::where('vehicle_id', $devices[0]->vehicle_id)->first();
        $this->assertNotNull($baseline);
        $this->assertGreaterThan(800, $baseline->median_value);

        // ==========================================
        // 3. SIMULASI 600 SESI PENGISIAN DAYA EV TOU
        // ==========================================
        $startAction = app(StartEvChargingSessionAction::class);
        $meterAction = app(RecordEvMeterValueAction::class);
        $settleAction = app(SettleEvChargingSessionAction::class);

        $totalExpectedRevenue = 0;
        $totalSessionsCount = 0;

        for ($s = 1; $s <= 600; $s++) {
            $charger = $allChargers[($s - 1) % count($allChargers)];
            $user = $users[($s - 1) % count($users)];
            $vehicleId = $vehicleIds[($s - 1) % count($vehicleIds)];

            // Waktu sesi tersebar dalam 90 hari
            $dayOffset = ($s - 1) % 90;
            $hourOffset = 10 + (($s * 3) % 10); // antara jam 10:00 s.d. 20:00
            $sessionStart = $startTime->copy()->addDays($dayOffset)->setHour($hourOffset)->setMinute(0)->setSecond(0);

            // Buka sesi (Hold 100.000 IDR)
            $session = $startAction->execute(
                chargerId: $charger->id,
                vehicleId: $vehicleId,
                userId: $user->id,
                estimatedDepositIdr: 100000,
                startTime: $sessionStart
            );

            // Rekam meter values: 0 Wh -> 20.000 Wh (20 kWh) selama 1 jam
            $meterAction->execute($session->id, $sessionStart, 0, 20000);

            $sessionEnd = $sessionStart->copy()->addHour();
            $meterAction->execute($session->id, $sessionEnd, 20000, 20000);

            // Settle sesi
            $completedSession = $settleAction->execute(
                sessionId: $session->id,
                completedAt: $sessionEnd,
                finalSohBps: 9800
            );

            $this->assertSame('COMPLETED', $completedSession->status);
            $totalExpectedRevenue += (int) $completedSession->total_cost_idr;
            $totalSessionsCount++;
        }

        // Verifikasi 600 sesi selesai
        $this->assertSame(600, $totalSessionsCount);
        $this->assertGreaterThan(0, $totalExpectedRevenue);

        // Rekonsiliasi capture revenue EV vs saldo buku besar oto:ev_charging_revenue:IDR
        $ledgerRevBalance = (int) $this->ledger->getCachedBalance('oto:ev_charging_revenue:IDR');
        $this->assertSame($totalExpectedRevenue, $ledgerRevBalance);

        // Saldo escrow hold kembali bersih 0 setelah semua settlement
        $escrowBalance = (int) $this->ledger->getCachedBalance('escrow:payment:IDR');
        $this->assertSame(0, $escrowBalance);

        // ==========================================
        // 4. SIMULASI B2B FLEET LEASING 3 KONTRAK × 3 BULAN
        // ==========================================
        $assignAction = app(AssignVehicleToContractAction::class);
        $billAction = app(BillMonthlyFleetLeaseAction::class);
        $creditAction = app(CreditSlaPenaltyAction::class);
        $amortizationEngine = app(Psak73AmortizationEngine::class);

        $parties = [9001, 9002, 9003];
        $contracts = [];

        // Buat 3 kontrak sewa armada 10 unit per kontrak
        foreach ($parties as $idx => $partyId) {
            $contractNumber = sprintf('CTR-B2B-2026-%02d', $idx + 1);
            $contract = FleetContract::create([
                'contract_number' => $contractNumber,
                'party_id' => $partyId,
                'start_date' => '2026-08-01',
                'end_date' => '2026-10-31',
                'duration_months' => 3,
                'monthly_rental_idr' => 25000000, // Rp 25.000.000/bulan
                'total_lease_value_idr' => 75000000, // Rp 75.000.000
                'total_units' => 0,
                'monthly_quota_km' => 3000,
                'excess_rate_per_km_idr' => 1500,
                'sla_target_availability_bps' => 9500, // 95%
                'status' => 'ACTIVE',
            ]);

            // Assign 10 kendaraan unik ke kontrak
            for ($k = 0; $k < 10; $k++) {
                $vehId = $vehicleIds[50 + ($idx * 10) + $k];
                $assignAction->execute(
                    contractId: $contract->id,
                    vehicleId: $vehId
                );
            }

            // Hitung jadwal amortisasi PSAK 73 (3 bulan, discount rate 8% p.a.)
            $schedules = $amortizationEngine->generateSchedule(
                contractId: $contract->id,
                presentValueLiabilityIdr: 72000000,
                monthlyPaymentIdr: 25000000,
                durationMonths: 3,
                annualRateBps: 800
            );

            foreach ($schedules as $sched) {
                FleetAmortizationSchedule::create($sched);
            }

            // Verifikasi saldo akhir liabilitas PSAK 73 tepat 0
            $finalSchedule = end($schedules);
            $this->assertSame(0, $finalSchedule['ending_liability_idr']);

            // Tagih sewa bulanan selama 3 bulan (Agustus, September, Oktober 2026)
            $periods = ['2026-08', '2026-09', '2026-10'];
            foreach ($periods as $period) {
                $invoice = $billAction->execute($contract, $period);
                $this->assertSame('ISSUED', $invoice->status);
                $this->assertSame(25000000, (int) $invoice->total_billed_idr);
            }

            // Injeksi 1 breach SLA ketersediaan di bulan September (500 bps)
            $breach = FleetSlaBreach::create([
                'breach_uuid' => (string) Str::uuid(),
                'contract_id' => $contract->id,
                'unit_id' => FleetContractUnit::where('contract_id', $contract->id)->first()->id,
                'incident_date' => '2026-09-15',
                'downtime_hours' => 60,
                'breach_type' => 'DOWNTIME_EXCESS',
                'penalty_bps' => 500,
                'penalty_amount_idr' => 1250000,
                'status' => 'RECORDED',
            ]);
            $creditedBreach = $creditAction->execute($breach);
            $this->assertSame('CREDITED', $creditedBreach->status);
            $this->assertSame(1250000, (int) $creditedBreach->penalty_amount_idr);

            $contracts[] = $contract;
        }

        // ==========================================
        // 5. AUDIT DUA SUMBER KONSOLIDASI (MOB:AUDIT)
        // ==========================================
        $exitMobAudit = Artisan::call('mob:audit');
        $this->assertSame(0, $exitMobAudit, 'Audit konsolidasi mob:audit harus exit code 0 tanpa diskrepansi.');

        // Pastikan seluruh 18 charger berstatus AVAILABLE
        $occupiedCount = EvCharger::where('status', 'OCCUPIED')->count();
        $this->assertSame(0, $occupiedCount);
    }
}
