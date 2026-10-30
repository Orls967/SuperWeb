<?php

declare(strict_types=1);

namespace Tests\Acceptance\Mobility;

use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Modules\Banking\Contracts\Ledger;
use Modules\Ev\Application\Actions\CancelEvChargingSessionAction;
use Modules\Ev\Application\Actions\RecordEvMeterValueAction;
use Modules\Ev\Application\Actions\SettleEvChargingSessionAction;
use Modules\Ev\Application\Actions\StartEvChargingSessionAction;
use Modules\Ev\Application\Services\TimeOfUseTariffCalculator;
use Modules\Ev\Domain\Events\EvSessionCompletedEvent;
use Modules\Ev\Domain\Models\EvCharger;
use Modules\Ev\Domain\Models\EvMeterValue;
use Modules\Ev\Domain\Models\EvSession;
use Modules\Ev\Domain\Models\EvStation;
use Modules\Ev\Domain\Models\EvTariffWindow;
use Tests\TestCase;

class MobilityEvSessionAndTariffTest extends TestCase
{
    use RefreshDatabase;

    private Ledger $ledger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);
        $this->ledger = app(Ledger::class);
    }

    private function createStationAndCharger(): array
    {
        $station = EvStation::create([
            'station_code' => 'SPKLU-JKT-01',
            'name' => 'SPKLU Senayan Mall',
            'timezone' => 'Asia/Jakarta',
            'max_grid_power_kw' => 200,
            'location_type' => 'mall',
        ]);

        $charger = EvCharger::create([
            'station_id' => $station->id,
            'charger_code' => 'CHG-DC-01',
            'charger_type' => 'DC_FAST',
            'max_power_kw' => 60,
            'status' => 'AVAILABLE',
        ]);

        // Jendela Tarif Luar Puncak: 00:00 - 17:00 (2.000 IDR / kWh)
        EvTariffWindow::create([
            'station_id' => $station->id,
            'name' => 'Off-Peak Day',
            'start_time' => '00:00',
            'end_time' => '17:00',
            'rate_per_kwh_idr' => 2000,
            'is_cross_midnight' => false,
        ]);

        // Jendela Tarif Beban Puncak: 17:00 - 22:00 (3.500 IDR / kWh)
        EvTariffWindow::create([
            'station_id' => $station->id,
            'name' => 'Peak Hours',
            'start_time' => '17:00',
            'end_time' => '22:00',
            'rate_per_kwh_idr' => 3500,
            'is_cross_midnight' => false,
        ]);

        // Jendela Tarif Malam Lintas Tengah Malam: 22:00 - 04:00 (1.800 IDR / kWh)
        EvTariffWindow::create([
            'station_id' => $station->id,
            'name' => 'Off-Peak Midnight',
            'start_time' => '22:00',
            'end_time' => '04:00',
            'rate_per_kwh_idr' => 1800,
            'is_cross_midnight' => true,
        ]);

        $user = User::factory()->create();
        $vehicleId = (int) DB::table('core_vehicles')->insertGetId([
            'user_id' => $user->id,
            'plate_number' => 'B 8888 EV',
            'vin' => 'VIN-EV-'.uniqid(),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$station, $charger, $user, $vehicleId];
    }

    public function test_mulai_sesi_melakukan_hold_deposit_pada_ledger(): void
    {
        [$station, $charger, $user, $vehicleId] = $this->createStationAndCharger();
        $startAction = app(StartEvChargingSessionAction::class);

        $session = $startAction->execute(
            chargerId: $charger->id,
            vehicleId: $vehicleId,
            userId: $user->id,
            estimatedDepositIdr: 150000,
            startTime: Carbon::parse('2026-10-30 16:30:00', 'Asia/Jakarta')
        );

        $this->assertInstanceOf(EvSession::class, $session);
        $this->assertSame('CHARGING', $session->status);
        $this->assertSame('OCCUPIED', $charger->fresh()->status);
        $this->assertSame(150000, $session->deposit_held_idr);

        // Periksa akun escrow menerima kredit hold (+150.000) dan dompet debit (-150.000)
        $escrowBalance = $this->ledger->getAccountBalance('escrow:payment:IDR');
        $this->assertSame(150000, $escrowBalance);
    }

    public function test_vektor_tagihan_sesi_1630_sampai_1800_wib_90000_rupiah(): void
    {
        [$station, $charger, $user, $vehicleId] = $this->createStationAndCharger();
        $startAction = app(StartEvChargingSessionAction::class);
        $meterAction = app(RecordEvMeterValueAction::class);
        $settleAction = app(SettleEvChargingSessionAction::class);

        // Mulai sesi pada 16:30 WIB
        $start = Carbon::parse('2026-10-30 16:30:00', 'Asia/Jakarta');
        $session = $startAction->execute(
            chargerId: $charger->id,
            vehicleId: $vehicleId,
            userId: $user->id,
            estimatedDepositIdr: 150000,
            startTime: $start
        );

        // Rekam meter value:
        // 16:30 -> 0 Wh
        $meterAction->execute($session->id, $start, 0, 20000);
        // 17:00 (30 menit kemudian @ 20 kW konstan = 10 kWh = 10.000 Wh)
        $t1700 = Carbon::parse('2026-10-30 17:00:00', 'Asia/Jakarta');
        $meterAction->execute($session->id, $t1700, 10000, 20000);
        // 18:00 (60 menit kemudian @ 20 kW konstan = 20 kWh tambahan = 30.000 Wh total)
        $t1800 = Carbon::parse('2026-10-30 18:00:00', 'Asia/Jakarta');
        $meterAction->execute($session->id, $t1800, 30000, 20000);

        // Settle sesi pada 18:00 WIB
        // Luar puncak: 10 kWh @ 2.000 = 20.000 IDR
        // Puncak: 20 kWh @ 3.500 = 70.000 IDR
        // Total = 90.000 IDR. Hold = 150.000 IDR. Capture = 90.000 IDR. Refund = 60.000 IDR.
        $res = $settleAction->execute(
            sessionId: $session->id,
            completedAt: $t1800,
            finalSohBps: 9850 // 98.50%
        );

        $this->assertSame(90000, $res->total_cost_idr);
        $this->assertSame(60000, $res->refund_idr);
        $this->assertSame('COMPLETED', $res->status);
        $this->assertSame('AVAILABLE', $charger->fresh()->status);

        // Periksa ledger pendapatan ter-posting persis 90.000
        $revBalance = $this->ledger->getAccountBalance('oto:ev_charging_revenue:IDR');
        $this->assertSame(90000, $revBalance);

        // Sisa hold di escrow kembali ke 0
        $escrowBalance = $this->ledger->getAccountBalance('escrow:payment:IDR');
        $this->assertSame(0, $escrowBalance);
    }

    public function test_vektor_apportionment_sampel_1652_ke_1707_largest_remainder_2667_dan_2333(): void
    {
        $calculator = app(TimeOfUseTariffCalculator::class);

        // Sampel 16:52 (0 Wh) dan 17:07 (5.000 Wh). Total 15 menit.
        // Batas perubahan tarif terjadi pada 17:00 (8 menit luar-puncak, 7 menit puncak).
        $t1 = Carbon::parse('2026-10-30 16:52:00', 'Asia/Jakarta');
        $t2 = Carbon::parse('2026-10-30 17:07:00', 'Asia/Jakarta');

        $windows = [
            ['name' => 'Off-Peak', 'start' => '00:00', 'end' => '17:00', 'rate' => 2000],
            ['name' => 'Peak', 'start' => '17:00', 'end' => '22:00', 'rate' => 3500],
        ];

        $allocation = $calculator->apportionEnergy(
            fromTime: $t1,
            toTime: $t2,
            energyDeltaWh: 5000,
            windows: $windows
        );

        // Alokasi harus bulat integer Wh dan berjumlah persis 5.000
        $this->assertSame(2667, $allocation['Off-Peak']);
        $this->assertSame(2333, $allocation['Peak']);
        $this->assertSame(5000, $allocation['Off-Peak'] + $allocation['Peak']);
    }

    public function test_stop_sesi_dua_kali_hanya_menghasilkan_satu_capture(): void
    {
        [$station, $charger, $user, $vehicleId] = $this->createStationAndCharger();
        $startAction = app(StartEvChargingSessionAction::class);
        $meterAction = app(RecordEvMeterValueAction::class);
        $settleAction = app(SettleEvChargingSessionAction::class);

        $start = Carbon::parse('2026-10-30 10:00:00', 'Asia/Jakarta');
        $session = $startAction->execute($charger->id, $vehicleId, $user->id, 50000, $start);

        $meterAction->execute($session->id, $start, 0, 10000);
        $end = Carbon::parse('2026-10-30 11:00:00', 'Asia/Jakarta');
        $meterAction->execute($session->id, $end, 10000, 10000);

        // Stop 1
        $res1 = $settleAction->execute($session->id, $end, 9900);
        $this->assertSame('COMPLETED', $res1->status);

        // Stop 2 (idempoten)
        $res2 = $settleAction->execute($session->id, $end, 9900);
        $this->assertSame($res1->id, $res2->id);

        // Revenue ledger hanya bertambah sekali (10 kWh @ 2000 = 20.000 IDR)
        $revBalance = $this->ledger->getAccountBalance('oto:ev_charging_revenue:IDR');
        $this->assertSame(20000, $revBalance);
    }

    public function test_batalkan_sesi_merelease_penuh_deposit_hold(): void
    {
        [$station, $charger, $user, $vehicleId] = $this->createStationAndCharger();
        $startAction = app(StartEvChargingSessionAction::class);
        $cancelAction = app(CancelEvChargingSessionAction::class);

        $session = $startAction->execute($charger->id, $vehicleId, $user->id, 75000, now());
        $this->assertSame(75000, $this->ledger->getAccountBalance('escrow:payment:IDR'));

        $cancelled = $cancelAction->execute($session->id, 'Pengemudi membatalkan sebelum pengisian');

        $this->assertSame('CANCELLED', $cancelled->status);
        $this->assertSame('AVAILABLE', $charger->fresh()->status);
        $this->assertSame(0, $this->ledger->getAccountBalance('escrow:payment:IDR'));
    }

    public function test_meter_value_append_only_menolak_update_dan_delete(): void
    {
        [$station, $charger, $user, $vehicleId] = $this->createStationAndCharger();
        $startAction = app(StartEvChargingSessionAction::class);
        $meterAction = app(RecordEvMeterValueAction::class);

        $session = $startAction->execute($charger->id, $vehicleId, $user->id, 50000, now());
        $meter = $meterAction->execute($session->id, now(), 1500, 7000);

        $this->expectException(\LogicException::class);
        $meter->update(['energy_wh' => 99999]);
    }

    public function test_meter_value_menolak_delete(): void
    {
        [$station, $charger, $user, $vehicleId] = $this->createStationAndCharger();
        $startAction = app(StartEvChargingSessionAction::class);
        $meterAction = app(RecordEvMeterValueAction::class);

        $session = $startAction->execute($charger->id, $vehicleId, $user->id, 50000, now());
        $meter = $meterAction->execute($session->id, now(), 1500, 7000);

        $this->expectException(\LogicException::class);
        $meter->delete();
    }

    public function test_meter_value_mundur_non_monotonik_ditolak(): void
    {
        [$station, $charger, $user, $vehicleId] = $this->createStationAndCharger();
        $startAction = app(StartEvChargingSessionAction::class);
        $meterAction = app(RecordEvMeterValueAction::class);

        $session = $startAction->execute($charger->id, $vehicleId, $user->id, 50000, now());

        $meterAction->execute($session->id, now(), 5000, 7000);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Meter value must be monotonically non-decreasing');

        // Nilai meteran berikutnya lebih rendah dari 5000 Wh (tampering/mundur)
        $meterAction->execute($session->id, now()->addMinutes(1), 4000, 7000);
    }

    public function test_jendela_tarif_tou_yang_melewati_tengah_malam(): void
    {
        $calculator = app(TimeOfUseTariffCalculator::class);

        // Rentang pengisian 23:30 s.d. 01:30 (total 2 jam = 120 menit).
        // Jendela malam lintas tengah malam: 22:00 s.d. 04:00 (tarif 1.800/kWh).
        $t1 = Carbon::parse('2026-10-30 23:30:00', 'Asia/Jakarta');
        $t2 = Carbon::parse('2026-10-31 01:30:00', 'Asia/Jakarta');

        $windows = [
            ['name' => 'Off-Peak Midnight', 'start' => '22:00', 'end' => '04:00', 'rate' => 1800, 'cross_midnight' => true],
        ];

        $allocation = $calculator->apportionEnergy(
            fromTime: $t1,
            toTime: $t2,
            energyDeltaWh: 20000, // 20 kWh
            windows: $windows
        );

        $this->assertSame(20000, $allocation['Off-Peak Midnight']);
    }

    public function test_selesai_sesi_memancarkan_event_auto_ev_session_completed_dengan_soh(): void
    {
        Event::fake([EvSessionCompletedEvent::class]);
        [$station, $charger, $user, $vehicleId] = $this->createStationAndCharger();

        $startAction = app(StartEvChargingSessionAction::class);
        $meterAction = app(RecordEvMeterValueAction::class);
        $settleAction = app(SettleEvChargingSessionAction::class);

        $session = $startAction->execute($charger->id, $vehicleId, $user->id, 50000, now());
        $meterAction->execute($session->id, now(), 0, 5000);
        $meterAction->execute($session->id, now()->addMinutes(30), 2500, 5000);

        $settleAction->execute($session->id, now()->addMinutes(30), 9750);

        Event::assertDispatched(EvSessionCompletedEvent::class, function ($e) use ($session) {
            return $e->sessionId === $session->id && $e->finalSohBps === 9750;
        });
    }

    public function test_tidak_bisa_memulai_sesi_pada_charger_yang_sedang_occupied(): void
    {
        [$station, $charger, $user, $vehicleId] = $this->createStationAndCharger();
        $startAction = app(StartEvChargingSessionAction::class);

        $startAction->execute($charger->id, $vehicleId, $user->id, 50000, now());

        $user2 = User::factory()->create();
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Charger is not available');

        $startAction->execute($charger->id, $vehicleId, $user2->id, 50000, now());
    }

    public function test_meter_value_idempoten_berdasarkan_timestamp_dan_sesi(): void
    {
        [$station, $charger, $user, $vehicleId] = $this->createStationAndCharger();
        $startAction = app(StartEvChargingSessionAction::class);
        $meterAction = app(RecordEvMeterValueAction::class);

        $session = $startAction->execute($charger->id, $vehicleId, $user->id, 50000, now());

        $t = now();
        $m1 = $meterAction->execute($session->id, $t, 1000, 5000);
        $m2 = $meterAction->execute($session->id, $t, 1000, 5000);

        $this->assertSame($m1->id, $m2->id);
        $this->assertSame(1, EvMeterValue::where('session_id', $session->id)->count());
    }
}
