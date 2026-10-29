<?php

declare(strict_types=1);

namespace Tests\Acceptance\Hotel;

use App\Models\User;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Banking\Domain\Models\LedgerEntry;
use Modules\Banking\Domain\Models\LedgerTransaction;
use Modules\Hotel\Application\Actions\ChargeFolioOutletAction;
use Modules\Hotel\Application\Actions\CheckInGuestAction;
use Modules\Hotel\Application\Actions\ExecuteHotelNightAuditAction;
use Modules\Hotel\Application\Actions\ProcessGuestDepositAction;
use Modules\Hotel\Application\Actions\ReserveRoomAction;
use Modules\Hotel\Application\DTOs\ReserveRoomDTO;
use Modules\Hotel\Application\Services\HotelBusinessDateService;
use Modules\Hotel\database\seeders\HotelDemoSeeder;
use Modules\Hotel\Domain\Models\HotelDailyRevenueSnapshot;
use Modules\Hotel\Domain\Models\HotelFolio;
use Modules\Hotel\Domain\Models\HotelFolioLine;
use Modules\Hotel\Domain\Models\HotelProperty;
use Modules\Hotel\Domain\Models\HotelRatePlan;
use Modules\Hotel\Domain\Models\HotelRoom;
use Modules\Hotel\Domain\Models\HotelRoomNight;
use Modules\Hotel\Domain\Models\HotelRoomType;
use Tests\TestCase;

class HotelNightAuditTest extends TestCase
{
    use RefreshDatabase;

    private HotelProperty $property;

    private User $auditor;

    private User $guest;

    private HotelRoomType $roomType;

    private HotelRatePlan $ratePlan;

    private HotelBusinessDateService $businessDateService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            LedgerAccountsSeeder::class,
            HotelDemoSeeder::class,
        ]);

        $this->property = HotelProperty::firstOrFail();
        $this->auditor = User::factory()->create(['role' => 'night_auditor']);
        $this->guest = User::factory()->create(['role' => 'guest']);
        $this->roomType = HotelRoomType::where('property_id', $this->property->id)->firstOrFail();
        $this->ratePlan = HotelRatePlan::where('property_id', $this->property->id)->firstOrFail();

        $this->businessDateService = app(HotelBusinessDateService::class);
        $this->businessDateService->initializeBusinessDate($this->property->id, '2026-11-01');
    }

    private function createInHouseBooking(int $rateIdr = 1500000, string $checkIn = '2026-11-01', string $checkOut = '2026-11-04'): array
    {
        $room = HotelRoom::create([
            'id' => (string) Str::uuid(),
            'property_id' => $this->property->id,
            'room_type_id' => $this->roomType->id,
            'room_number' => 'NA-'.Str::random(4),
            'room_type' => 'DELUXE',
            'base_rate_idr' => $rateIdr,
            'status' => 'available',
            'energy_setback_active' => true,
        ]);

        $reserveAction = app(ReserveRoomAction::class);
        $reservation = $reserveAction->execute(new ReserveRoomDTO(
            propertyId: $this->property->id,
            roomTypeId: $this->roomType->id,
            ratePlanId: $this->ratePlan->id,
            guestUserId: (string) $this->guest->id,
            checkInDate: $checkIn,
            checkOutDate: $checkOut,
            idempotencyKey: (string) Str::uuid(),
        ));

        $reservation->update(['daily_rate_idr' => $rateIdr]);

        $checkInAction = app(CheckInGuestAction::class);
        $checkInAction->checkIn(
            reservationId: $reservation->id,
            assignedRoomId: $room->id,
            idType: 'KTP',
            idNumber: '3201012345670001',
            fullName: 'Bambang Sudirman',
            idempotencyKey: (string) Str::uuid(),
        );

        $reservation->refresh();
        $folio = HotelFolio::where('reservation_id', $reservation->id)->firstOrFail();

        return [$reservation, $folio, $room];
    }

    public function test_pendapatan_kamar_diakui_dan_diposting_per_malam_secara_akurat(): void
    {
        [$reservation, $folio] = $this->createInHouseBooking(1500000);

        $action = app(ExecuteHotelNightAuditAction::class);
        $snapshot = $action->execute($this->property->id);

        $this->assertInstanceOf(HotelDailyRevenueSnapshot::class, $snapshot);

        $folio->refresh();
        // H-3: rate 1.500.000, service 150.000 (10%), PBJT 165.000 (10% of 1.650.000) = 1.815.000
        $this->assertEquals(1815000, $folio->total_room_charges);

        $line = HotelFolioLine::where('folio_id', $folio->id)
            ->whereDate('business_date', '2026-11-01')
            ->where('line_type', 'ROOM_CHARGE')
            ->firstOrFail();

        $this->assertEquals(1500000, $line->base_amount_idr);
        $this->assertEquals(150000, $line->service_charge_idr);
        $this->assertEquals(165000, $line->tax_pbjt_idr);
        $this->assertEquals(1815000, $line->amount_idr);

        // Check Ledger balance
        $tx = LedgerTransaction::where('reference_id', $line->id)->firstOrFail();
        $entries = LedgerEntry::where('transaction_id', $tx->id)->get();
        $this->assertEquals(0, $entries->sum('amount'));
    }

    public function test_night_audit_yang_dijalankan_dua_kali_pada_hari_yang_sama_tidak_menduplikasi_posting(): void
    {
        [$reservation, $folio] = $this->createInHouseBooking(1000000);

        $action = app(ExecuteHotelNightAuditAction::class);
        $action->execute($this->property->id, '2026-11-01');

        $folio->refresh();
        $chargesFirst = $folio->total_room_charges;
        $linesCountFirst = HotelFolioLine::where('folio_id', $folio->id)->count();

        // Jalankan kedua kali pada tanggal yang sama
        $action->execute($this->property->id, '2026-11-01');

        $folio->refresh();
        $this->assertEquals($chargesFirst, $folio->total_room_charges);
        $this->assertEquals($linesCountFirst, HotelFolioLine::where('folio_id', $folio->id)->count());
    }

    public function test_night_audit_sukses_memajukan_business_date_properti_satu_hari(): void
    {
        $this->assertEquals('2026-11-01', $this->businessDateService->getCurrentBusinessDate($this->property->id));

        $action = app(ExecuteHotelNightAuditAction::class);
        $action->execute($this->property->id);

        $this->assertEquals('2026-11-02', $this->businessDateService->getCurrentBusinessDate($this->property->id));
        $record = $this->businessDateService->getBusinessDateRecord($this->property->id);
        $this->assertEquals('open', $record->status);
        $this->assertEquals('COMPLETED', $record->last_checkpoint);
    }

    public function test_reservasi_yang_tidak_check_in_hingga_night_audit_diproses_sebagai_no_show(): void
    {
        $reserveAction = app(ReserveRoomAction::class);
        $res = $reserveAction->execute(new ReserveRoomDTO(
            propertyId: $this->property->id,
            roomTypeId: $this->roomType->id,
            ratePlanId: $this->ratePlan->id,
            guestUserId: (string) $this->guest->id,
            checkInDate: '2026-11-01',
            checkOutDate: '2026-11-03',
            idempotencyKey: (string) Str::uuid(),
        ));

        // Konfirmasi reservasi tanpa check-in
        $res->update(['status' => 'confirmed']);

        $action = app(ExecuteHotelNightAuditAction::class);
        $action->execute($this->property->id);

        $res->refresh();
        $this->assertEquals('no_show', $res->status);
    }

    public function test_denda_no_show_satu_malam_dikenakan_dan_sisa_malam_dilepaskan_ke_inventaris(): void
    {
        HotelRoom::create([
            'id' => (string) Str::uuid(),
            'property_id' => $this->property->id,
            'room_type_id' => $this->roomType->id,
            'room_number' => 'NS-'.Str::random(4),
            'room_type' => 'DELUXE',
            'base_rate_idr' => 1200000,
            'status' => 'available',
            'energy_setback_active' => true,
        ]);

        $reserveAction = app(ReserveRoomAction::class);
        $res = $reserveAction->execute(new ReserveRoomDTO(
            propertyId: $this->property->id,
            roomTypeId: $this->roomType->id,
            ratePlanId: $this->ratePlan->id,
            guestUserId: (string) $this->guest->id,
            checkInDate: '2026-11-01',
            checkOutDate: '2026-11-04', // 3 nights
            idempotencyKey: (string) Str::uuid(),
        ));
        $res->update(['status' => 'confirmed', 'daily_rate_idr' => 1200000]);

        // Bayar deposit 1.200.000
        $depositAction = app(ProcessGuestDepositAction::class);
        $deposit = $depositAction->execute(
            propertyId: $this->property->id,
            reservationId: $res->id,
            guestUserId: (string) $this->guest->id,
            amountIdr: 1200000,
            paymentMethod: 'CASH',
            idempotencyKey: 'dep-noshow-key-01',
        );

        $this->assertEquals(3, HotelRoomNight::where('reservation_id', $res->id)->count());

        $action = app(ExecuteHotelNightAuditAction::class);
        $action->execute($this->property->id);

        $res->refresh();
        $deposit->refresh();

        $this->assertEquals('no_show', $res->status);
        $this->assertEquals(1200000, $deposit->applied_amount_idr);
        $this->assertEquals('APPLIED', $deposit->status);

        // Sisa malam setelah hari kedatangan (2026-11-02 dan 2026-11-03) dilepaskan ke inventaris
        $remainingNights = HotelRoomNight::where('reservation_id', $res->id)->count();
        $this->assertEquals(1, $remainingNights); // Hanya malam pertama yang tersisa sebagai rekaman audit
    }

    public function test_pajak_hotel_pbjt_10_persen_dihitung_dari_tarif_kamar_ditambah_service_charge(): void
    {
        [$reservation, $folio] = $this->createInHouseBooking(1000000);

        $action = app(ExecuteHotelNightAuditAction::class);
        $action->execute($this->property->id);

        $line = HotelFolioLine::where('folio_id', $folio->id)
            ->whereDate('business_date', '2026-11-01')
            ->firstOrFail();

        // Tarif: 1.000.000, Service: 100.000
        // Dasar pengenaan pajak = 1.000.000 + 100.000 = 1.100.000
        // PBJT 10% = 110.000
        $this->assertEquals(110000, $line->tax_pbjt_idr);
    }

    public function test_service_charge_10_persen_dihitung_dari_tarif_dasar_kamar(): void
    {
        [$reservation, $folio] = $this->createInHouseBooking(2000000);

        $action = app(ExecuteHotelNightAuditAction::class);
        $action->execute($this->property->id);

        $line = HotelFolioLine::where('folio_id', $folio->id)
            ->whereDate('business_date', '2026-11-01')
            ->firstOrFail();

        // 10% dari 2.000.000 = 200.000
        $this->assertEquals(200000, $line->service_charge_idr);
    }

    public function test_pembulatan_rupiah_per_baris_folio_menggunakan_metode_half_up_secara_persis(): void
    {
        [$reservation, $folio] = $this->createInHouseBooking(1234567);

        $action = app(ExecuteHotelNightAuditAction::class);
        $action->execute($this->property->id);

        $line = HotelFolioLine::where('folio_id', $folio->id)
            ->whereDate('business_date', '2026-11-01')
            ->firstOrFail();

        // Base: 1.234.567
        // Service (10%): round(123456.7, 0, PHP_ROUND_HALF_UP) = 123.457
        // Tax (10% dari 1.234.567 + 123.457 = 1.358.024): round(135802.4, 0, PHP_ROUND_HALF_UP) = 135.802
        // Total: 1.234.567 + 123.457 + 135.802 = 1.493.826
        $this->assertEquals(1234567, $line->base_amount_idr);
        $this->assertEquals(123457, $line->service_charge_idr);
        $this->assertEquals(135802, $line->tax_pbjt_idr);
        $this->assertEquals(1493826, $line->amount_idr);
    }

    public function test_kegagalan_di_tengah_proses_night_audit_dapat_dipulihkan_melalui_mekanisme_checkpoint(): void
    {
        [$reservation, $folio] = $this->createInHouseBooking(1000000);

        $action = app(ExecuteHotelNightAuditAction::class);

        // Simulasi interupsi di checkpoint POST_ROOM_CHARGES
        $action->simulateCheckpointFailure($this->property->id, 'POST_ROOM_CHARGES');

        // Jalankan ulang night audit
        $snapshot = $action->execute($this->property->id, '2026-11-01');

        $this->assertInstanceOf(HotelDailyRevenueSnapshot::class, $snapshot);
        $record = $this->businessDateService->getBusinessDateRecord($this->property->id);
        $this->assertEquals('COMPLETED', $record->last_checkpoint);
        $this->assertEquals('open', $record->status);
    }

    public function test_snapshot_harian_hasil_audit_memiliki_total_pendapatan_yang_identik_dengan_mutasi_ledger(): void
    {
        [$reservation, $folio] = $this->createInHouseBooking(2000000);

        $action = app(ExecuteHotelNightAuditAction::class);
        $snapshot = $action->execute($this->property->id, '2026-11-01');

        $this->assertGreaterThan(0, $snapshot->total_room_revenue_idr);

        // Cari mutasi kredit pada htl:room_revenue:IDR
        $entries = LedgerEntry::whereHas('account', fn ($q) => $q->where('code', 'htl:room_revenue:IDR'))->get();
        $this->assertNotEmpty($entries);
        $this->assertEquals($snapshot->total_room_revenue_idr, $entries->sum('amount'));
    }

    public function test_pembebanan_transaksi_setelah_night_audit_menggunakan_business_date_baru(): void
    {
        [$reservation, $folio] = $this->createInHouseBooking(1000000);

        $auditAction = app(ExecuteHotelNightAuditAction::class);
        $auditAction->execute($this->property->id);

        $newDate = $this->businessDateService->getCurrentBusinessDate($this->property->id);
        $this->assertEquals('2026-11-02', $newDate);

        // Pembebanan outlet setelah night audit
        $outletAction = app(ChargeFolioOutletAction::class);
        $line = $outletAction->execute(
            reservationOrFolioId: $folio->id,
            baseAmountIdr: 100000,
            description: 'Breakfast Buffet Hari Ke-2',
            idempotencyKey: 'outlet-day2-01',
        );

        $this->assertEquals('2026-11-02', $line->business_date->toDateString());
    }

    public function test_night_audit_tidak_membebankan_biaya_kamar_pada_reservasi_status_tentatif_atau_batal(): void
    {
        HotelRoom::create([
            'id' => (string) Str::uuid(),
            'property_id' => $this->property->id,
            'room_type_id' => $this->roomType->id,
            'room_number' => 'AVAIL-'.Str::random(4),
            'room_type' => 'DELUXE',
            'base_rate_idr' => 1000000,
            'status' => 'available',
            'energy_setback_active' => true,
        ]);
        HotelRoom::create([
            'id' => (string) Str::uuid(),
            'property_id' => $this->property->id,
            'room_type_id' => $this->roomType->id,
            'room_number' => 'AVAIL-'.Str::random(4),
            'room_type' => 'DELUXE',
            'base_rate_idr' => 1000000,
            'status' => 'available',
            'energy_setback_active' => true,
        ]);

        $reserveAction = app(ReserveRoomAction::class);

        // Reservasi tentatif
        $tentative = $reserveAction->execute(new ReserveRoomDTO(
            propertyId: $this->property->id,
            roomTypeId: $this->roomType->id,
            ratePlanId: $this->ratePlan->id,
            guestUserId: (string) $this->guest->id,
            checkInDate: '2026-11-01',
            checkOutDate: '2026-11-03',
            idempotencyKey: (string) Str::uuid(),
        ));
        $tentative->update(['status' => 'tentatif']);

        // Reservasi batal
        $cancelled = $reserveAction->execute(new ReserveRoomDTO(
            propertyId: $this->property->id,
            roomTypeId: $this->roomType->id,
            ratePlanId: $this->ratePlan->id,
            guestUserId: (string) $this->guest->id,
            checkInDate: '2026-11-01',
            checkOutDate: '2026-11-03',
            idempotencyKey: (string) Str::uuid(),
        ));
        $cancelled->update(['status' => 'dibatalkan']);

        $action = app(ExecuteHotelNightAuditAction::class);
        $action->execute($this->property->id);

        $folioTentative = HotelFolio::where('reservation_id', $tentative->id)->first();
        $this->assertEquals(0, $folioTentative->total_room_charges);

        $folioCancelled = HotelFolio::where('reservation_id', $cancelled->id)->first();
        $this->assertEquals(0, $folioCancelled->total_room_charges);
    }
}
