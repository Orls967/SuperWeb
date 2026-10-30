<?php

declare(strict_types=1);

namespace Tests\Acceptance\Hotel;

use App\Models\User;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Modules\Banking\Domain\Models\LedgerEntry;
use Modules\Hotel\Application\Actions\CancelReservationAction;
use Modules\Hotel\Application\Actions\ChargeFolioOutletAction;
use Modules\Hotel\Application\Actions\CheckInGuestAction;
use Modules\Hotel\Application\Actions\CheckOutAndSettleFolioAction;
use Modules\Hotel\Application\Actions\ExecuteHotelNightAuditAction;
use Modules\Hotel\Application\Actions\ExecuteWalkCompensationAction;
use Modules\Hotel\Application\Actions\ProcessGuestDepositAction;
use Modules\Hotel\Application\Actions\RefundGuestDepositAction;
use Modules\Hotel\Application\Actions\ReserveRoomAction;
use Modules\Hotel\Application\Actions\TransferFolioToCityLedgerAction;
use Modules\Hotel\Application\DTOs\ReserveRoomDTO;
use Modules\Hotel\Application\Services\HotelBusinessDateService;
use Modules\Hotel\Application\Services\HotelReportService;
use Modules\Hotel\database\seeders\HotelDemoSeeder;
use Modules\Hotel\Domain\Models\HotelCityLedgerAccount;
use Modules\Hotel\Domain\Models\HotelDailyRevenueSnapshot;
use Modules\Hotel\Domain\Models\HotelDeposit;
use Modules\Hotel\Domain\Models\HotelFolio;
use Modules\Hotel\Domain\Models\HotelFolioLine;
use Modules\Hotel\Domain\Models\HotelProperty;
use Modules\Hotel\Domain\Models\HotelRateCalendar;
use Modules\Hotel\Domain\Models\HotelRatePlan;
use Modules\Hotel\Domain\Models\HotelReservation;
use Modules\Hotel\Domain\Models\HotelRoom;
use Modules\Hotel\Domain\Models\HotelRoomType;
use Tests\TestCase;

class HotelSevenDaySimulationTest extends TestCase
{
    use RefreshDatabase;

    private HotelProperty $property;

    private HotelRoomType $deluxeType;

    private HotelRoomType $suiteType;

    private HotelRatePlan $barPlan;

    private HotelRatePlan $nonRefPlan;

    private HotelBusinessDateService $businessDateService;

    private User $cashier;

    private User $hkStaff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            LedgerAccountsSeeder::class,
            HotelDemoSeeder::class,
        ]);

        $this->property = HotelProperty::firstOrFail();
        $this->property->update([
            'service_charge_bps' => 1000,
            'tax_pbjt_bps' => 1000,
        ]);

        $this->deluxeType = HotelRoomType::where('property_id', $this->property->id)->where('code', 'DELUXE')->firstOrFail();
        $this->suiteType = HotelRoomType::where('property_id', $this->property->id)->where('code', 'SUITE')->firstOrFail();

        $this->barPlan = HotelRatePlan::where('property_id', $this->property->id)->where('plan_type', 'BAR')->firstOrFail();
        $this->nonRefPlan = HotelRatePlan::where('property_id', $this->property->id)->where('plan_type', 'NON_REFUNDABLE')->firstOrFail();

        // Bersihkan data lama
        HotelReservation::where('property_id', $this->property->id)->delete();
        HotelRoom::where('property_id', $this->property->id)->delete();

        // Buat 10 kamar fisik DELUXE dan 5 kamar SUITE
        for ($i = 101; $i <= 110; $i++) {
            HotelRoom::create([
                'id' => (string) Str::uuid(),
                'property_id' => $this->property->id,
                'room_type_id' => $this->deluxeType->id,
                'room_number' => "DLX-{$i}",
                'room_type' => 'DELUXE',
                'base_rate_idr' => 1000000,
                'status' => 'available',
                'energy_setback_active' => true,
            ]);
        }

        for ($i = 201; $i <= 205; $i++) {
            HotelRoom::create([
                'id' => (string) Str::uuid(),
                'property_id' => $this->property->id,
                'room_type_id' => $this->suiteType->id,
                'room_number' => "SUI-{$i}",
                'room_type' => 'SUITE',
                'base_rate_idr' => 2000000,
                'status' => 'available',
                'energy_setback_active' => true,
            ]);
        }

        // Isi kalender tarif 7 hari
        for ($d = 1; $d <= 10; $d++) {
            $date = sprintf('2026-11-%02d', $d);
            HotelRateCalendar::updateOrCreate(
                [
                    'property_id' => $this->property->id,
                    'room_type_id' => $this->deluxeType->id,
                    'rate_plan_id' => $this->barPlan->id,
                    'calendar_date' => $date,
                ],
                ['id' => (string) Str::uuid(), 'rate_idr' => 1000000]
            );

            HotelRateCalendar::updateOrCreate(
                [
                    'property_id' => $this->property->id,
                    'room_type_id' => $this->suiteType->id,
                    'rate_plan_id' => $this->barPlan->id,
                    'calendar_date' => $date,
                ],
                ['id' => (string) Str::uuid(), 'rate_idr' => 2000000]
            );
        }

        $this->cashier = User::factory()->create(['role' => 'hotel_cashier']);
        $this->hkStaff = User::factory()->create(['role' => 'housekeeping']);

        $this->businessDateService = app(HotelBusinessDateService::class);
        $this->businessDateService->initializeBusinessDate($this->property->id, '2026-11-01');
    }

    public function test_simulasi_penuh_tujuh_hari_siklus_tamu_dan_rekonsiliasi_ledger(): void
    {
        $reserveAction = app(ReserveRoomAction::class);
        $checkInAction = app(CheckInGuestAction::class);
        $checkOutAction = app(CheckOutAndSettleFolioAction::class);
        $depositAction = app(ProcessGuestDepositAction::class);
        $refundAction = app(RefundGuestDepositAction::class);
        $outletAction = app(ChargeFolioOutletAction::class);
        $transferAction = app(TransferFolioToCityLedgerAction::class);
        $cancelAction = app(CancelReservationAction::class);
        $walkAction = app(ExecuteWalkCompensationAction::class);
        $nightAuditAction = app(ExecuteHotelNightAuditAction::class);

        // Buat akun korporat
        $corpAlpha = HotelCityLedgerAccount::create([
            'id' => (string) Str::uuid(),
            'property_id' => $this->property->id,
            'company_name' => 'PT Alpha Nusantara',
            'company_code' => 'CORP-ALPHA',
            'credit_limit_idr' => 20000000,
            'outstanding_balance_idr' => 0,
            'is_active' => true,
        ]);

        $corpBeta = HotelCityLedgerAccount::create([
            'id' => (string) Str::uuid(),
            'property_id' => $this->property->id,
            'company_name' => 'PT Beta Global',
            'company_code' => 'CORP-BETA',
            'credit_limit_idr' => 20000000,
            'outstanding_balance_idr' => 0,
            'is_active' => true,
        ]);

        // ====================================================================
        // HARI 1 (2026-11-01)
        // ====================================================================
        $u1 = User::factory()->create(['role' => 'guest']);
        $res1 = $reserveAction->execute(new ReserveRoomDTO(
            propertyId: $this->property->id,
            roomTypeId: $this->deluxeType->id,
            ratePlanId: $this->barPlan->id,
            guestUserId: (string) $u1->id,
            checkInDate: '2026-11-01',
            checkOutDate: '2026-11-04',
            idempotencyKey: 'sim-res-1',
        ));
        $res1->update(['daily_rate_idr' => 1000000]);
        $dep1 = $depositAction->execute($this->property->id, $res1->id, (string) $u1->id, 500000, 'TRANSFER', 'sim-dep-1');
        $room101 = HotelRoom::where('room_number', 'DLX-101')->firstOrFail();
        $checkInAction->checkIn($res1->id, $room101->id, 'KTP', '31710001', 'Tamu Satu');

        $u2 = User::factory()->create(['role' => 'guest']);
        $res2 = $reserveAction->execute(new ReserveRoomDTO(
            propertyId: $this->property->id,
            roomTypeId: $this->deluxeType->id,
            ratePlanId: $this->barPlan->id,
            guestUserId: (string) $u2->id,
            checkInDate: '2026-11-01',
            checkOutDate: '2026-11-03',
            idempotencyKey: 'sim-res-2',
        ));
        $res2->update(['daily_rate_idr' => 1000000]);
        $room102 = HotelRoom::where('room_number', 'DLX-102')->firstOrFail();
        $checkInAction->checkIn($res2->id, $room102->id, 'KTP', '31710002', 'Tamu Dua');

        $u3 = User::factory()->create(['role' => 'guest']);
        $res3 = $reserveAction->execute(new ReserveRoomDTO(
            propertyId: $this->property->id,
            roomTypeId: $this->suiteType->id,
            ratePlanId: $this->barPlan->id,
            guestUserId: (string) $u3->id,
            checkInDate: '2026-11-01',
            checkOutDate: '2026-11-03',
            idempotencyKey: 'sim-res-3',
        ));
        $res3->update(['daily_rate_idr' => 2000000]);
        $room201 = HotelRoom::where('room_number', 'SUI-201')->firstOrFail();
        $checkInAction->checkIn($res3->id, $room201->id, 'KTP', '31710003', 'Tamu Tiga');

        // Skenario 1: Batal Gratis (Guest 4)
        $u4 = User::factory()->create(['role' => 'guest']);
        $res4 = $reserveAction->execute(new ReserveRoomDTO(
            propertyId: $this->property->id,
            roomTypeId: $this->deluxeType->id,
            ratePlanId: $this->barPlan->id,
            guestUserId: (string) $u4->id,
            checkInDate: '2026-11-05',
            checkOutDate: '2026-11-07',
            idempotencyKey: 'sim-res-4',
        ));
        $dep4 = $depositAction->execute($this->property->id, $res4->id, (string) $u4->id, 1000000, 'TRANSFER', 'sim-dep-4');
        $cancelAction->execute($res4->id, 'sim-cancel-free-4', 'Perubahan jadwal', '2026-11-01 10:00:00');

        // Skenario 2: Batal Kena Denda (Guest 5)
        $u5 = User::factory()->create(['role' => 'guest']);
        $res5 = $reserveAction->execute(new ReserveRoomDTO(
            propertyId: $this->property->id,
            roomTypeId: $this->deluxeType->id,
            ratePlanId: $this->barPlan->id,
            guestUserId: (string) $u5->id,
            checkInDate: '2026-11-01',
            checkOutDate: '2026-11-03',
            idempotencyKey: 'sim-res-5',
        ));
        $res5->update(['daily_rate_idr' => 1000000]);
        $dep5 = $depositAction->execute($this->property->id, $res5->id, (string) $u5->id, 1000000, 'TRANSFER', 'sim-dep-5');
        $cancelAction->execute($res5->id, 'sim-cancel-late-5', 'Batal hari H', '2026-11-01 12:00:00');

        // Skenario 3: No-Show (Guest 6)
        $u6 = User::factory()->create(['role' => 'guest']);
        $res6 = $reserveAction->execute(new ReserveRoomDTO(
            propertyId: $this->property->id,
            roomTypeId: $this->deluxeType->id,
            ratePlanId: $this->barPlan->id,
            guestUserId: (string) $u6->id,
            checkInDate: '2026-11-01',
            checkOutDate: '2026-11-03',
            idempotencyKey: 'sim-res-6',
        ));
        $res6->update(['status' => 'confirmed', 'daily_rate_idr' => 1000000]);
        $dep6 = $depositAction->execute($this->property->id, $res6->id, (string) $u6->id, 1000000, 'CARD', 'sim-dep-6');

        // EKSEKUSI NIGHT AUDIT 1 (2026-11-01)
        $snap1 = $nightAuditAction->execute($this->property->id);
        $this->assertEquals('2026-11-02', $this->businessDateService->getCurrentBusinessDate($this->property->id));

        // ====================================================================
        // HARI 2 (2026-11-02)
        // ====================================================================
        $folio1 = HotelFolio::where('reservation_id', $res1->id)->firstOrFail();
        $outletAction->execute($folio1->id, 200000, 'Restoran Dinner Buffet', 'sim-out-d2-1');

        $folio2 = HotelFolio::where('reservation_id', $res2->id)->firstOrFail();
        $outletAction->execute($folio2->id, 50000, 'Minibar Snack', 'sim-out-d2-2');

        $u7 = User::factory()->create(['role' => 'guest']);
        $res7 = $reserveAction->execute(new ReserveRoomDTO(
            propertyId: $this->property->id,
            roomTypeId: $this->deluxeType->id,
            ratePlanId: $this->barPlan->id,
            guestUserId: (string) $u7->id,
            checkInDate: '2026-11-02',
            checkOutDate: '2026-11-05',
            idempotencyKey: 'sim-res-7',
        ));
        $res7->update(['daily_rate_idr' => 1000000]);
        $dep7 = $depositAction->execute($this->property->id, $res7->id, (string) $u7->id, 4000000, 'TRANSFER', 'sim-dep-7');
        $room103 = HotelRoom::where('room_number', 'DLX-103')->firstOrFail();
        $checkInAction->checkIn($res7->id, $room103->id, 'KTP', '31710007', 'Tamu Tujuh');

        // Skenario 4: Overbooking Walk Guest (Guest 8)
        $u8 = User::factory()->create(['role' => 'guest']);
        $res8 = $reserveAction->execute(new ReserveRoomDTO(
            propertyId: $this->property->id,
            roomTypeId: $this->deluxeType->id,
            ratePlanId: $this->barPlan->id,
            guestUserId: (string) $u8->id,
            checkInDate: '2026-11-02',
            checkOutDate: '2026-11-04',
            idempotencyKey: 'sim-res-8',
        ));
        $res8->update(['daily_rate_idr' => 1000000]);
        $walkAction->execute($res8->id, 'Grand Hyatt Partner Hotel', 'sim-walk-8');

        // EKSEKUSI NIGHT AUDIT 2 (2026-11-02)
        $snap2 = $nightAuditAction->execute($this->property->id);
        $this->assertEquals('2026-11-03', $this->businessDateService->getCurrentBusinessDate($this->property->id));

        // ====================================================================
        // HARI 3 (2026-11-03)
        // ====================================================================
        // Check-out Guest 2
        $folio2->refresh();
        $checkOutAction->execute(
            reservationId: $res2->id,
            cashReceivedIdr: 0,
            cardReceivedIdr: $folio2->outstanding_balance,
            idempotencyKey: 'sim-co-2',
            cashierUserId: $this->cashier->id,
        );

        // Housekeeping membersihkan Room 102
        $checkInAction->updateHousekeepingStatus($room102->id, 'cleaning', (int) $this->hkStaff->id);
        $checkInAction->updateHousekeepingStatus($room102->id, 'inspected', (int) $this->hkStaff->id);
        $checkInAction->updateHousekeepingStatus($room102->id, 'available', (int) $this->hkStaff->id);

        // Guest 3: Corporate Split ke CORP-ALPHA
        $folio3 = HotelFolio::where('reservation_id', $res3->id)->firstOrFail();
        $transferAction->execute(
            folioId: $folio3->id,
            cityLedgerAccountIdOrCode: $corpAlpha->id,
            amountIdr: $folio3->total_room_charges,
            notes: 'Tagihan akomodasi eksekutif dipindahkan ke Alpha',
            idempotencyKey: 'sim-split-3',
        );
        $folio3->refresh();
        $checkOutAction->execute(
            reservationId: $res3->id,
            cashReceivedIdr: $folio3->outstanding_balance,
            cardReceivedIdr: 0,
            idempotencyKey: 'sim-co-3',
            cashierUserId: $this->cashier->id,
        );

        // Housekeeping membersihkan Room 201 setelah checkout Guest 3
        $checkInAction->updateHousekeepingStatus($room201->id, 'cleaning', (int) $this->hkStaff->id);
        $checkInAction->updateHousekeepingStatus($room201->id, 'inspected', (int) $this->hkStaff->id);
        $checkInAction->updateHousekeepingStatus($room201->id, 'available', (int) $this->hkStaff->id);

        // Guest 1 Pindah Kamar (Room 101 -> Room 104)
        $room104 = HotelRoom::where('room_number', 'DLX-104')->firstOrFail();
        $checkInAction->moveRoom($res1->id, $room104->id, (int) $this->cashier->id);

        // Guest 9 Check-in
        $u9 = User::factory()->create(['role' => 'guest']);
        $res9 = $reserveAction->execute(new ReserveRoomDTO(
            propertyId: $this->property->id,
            roomTypeId: $this->suiteType->id,
            ratePlanId: $this->barPlan->id,
            guestUserId: (string) $u9->id,
            checkInDate: '2026-11-03',
            checkOutDate: '2026-11-06',
            idempotencyKey: 'sim-res-9',
        ));
        $res9->update(['daily_rate_idr' => 2000000]);
        $checkInAction->checkIn($res9->id, $room201->id, 'KTP', '31710009', 'Tamu Sembilan');

        // EKSEKUSI NIGHT AUDIT 3 (2026-11-03)
        $snap3 = $nightAuditAction->execute($this->property->id);
        $this->assertEquals('2026-11-04', $this->businessDateService->getCurrentBusinessDate($this->property->id));

        // ====================================================================
        // HARI 4 (2026-11-04)
        // ====================================================================
        // Guest 1 Perpanjang Menginap 2 malam (hingga 2026-11-06)
        $checkInAction->extendStay($res1->id, 2);

        // Guest 10 Check-in di Room 102 yang sudah bersih
        $u10 = User::factory()->create(['role' => 'guest']);
        $res10 = $reserveAction->execute(new ReserveRoomDTO(
            propertyId: $this->property->id,
            roomTypeId: $this->deluxeType->id,
            ratePlanId: $this->barPlan->id,
            guestUserId: (string) $u10->id,
            checkInDate: '2026-11-04',
            checkOutDate: '2026-11-06',
            idempotencyKey: 'sim-res-10',
        ));
        $res10->update(['daily_rate_idr' => 1000000]);
        $checkInAction->checkIn($res10->id, $room102->id, 'KTP', '31710010', 'Tamu Sepuluh');

        // EKSEKUSI NIGHT AUDIT 4 (2026-11-04)
        $snap4 = $nightAuditAction->execute($this->property->id);
        $this->assertEquals('2026-11-05', $this->businessDateService->getCurrentBusinessDate($this->property->id));

        // ====================================================================
        // HARI 5 (2026-11-05)
        // ====================================================================
        // Guest 7 Check-out dengan Deposit 4.000.000 (Refund kelebihan deposit)
        $folio7 = HotelFolio::where('reservation_id', $res7->id)->firstOrFail();
        $checkOutAction->execute(
            reservationId: $res7->id,
            cashReceivedIdr: 0,
            cardReceivedIdr: 0,
            idempotencyKey: 'sim-co-7',
            cashierUserId: $this->cashier->id,
        );

        $dep7->refresh();
        $excessDeposit = $dep7->amount_idr - $dep7->applied_amount_idr;
        $this->assertGreaterThan(0, $excessDeposit);
        $refundAction->execute($dep7->id, $excessDeposit, 'sim-ref-excess-7', 'Refund kelebihan uang deposit check-out');

        // Guest 11 Check-in (Split ke CORP-BETA)
        $u11 = User::factory()->create(['role' => 'guest']);
        $res11 = $reserveAction->execute(new ReserveRoomDTO(
            propertyId: $this->property->id,
            roomTypeId: $this->deluxeType->id,
            ratePlanId: $this->barPlan->id,
            guestUserId: (string) $u11->id,
            checkInDate: '2026-11-05',
            checkOutDate: '2026-11-07',
            idempotencyKey: 'sim-res-11',
        ));
        $res11->update(['daily_rate_idr' => 1000000]);
        $room105 = HotelRoom::where('room_number', 'DLX-105')->firstOrFail();
        $checkInAction->checkIn($res11->id, $room105->id, 'KTP', '31710011', 'Tamu Sebelas');

        // Guests 12-16 Check-in pada Hari 5 (DLX-106..110, 1 malam 2026-11-05 -> 2026-11-06)
        $batchRooms = [];
        for ($g = 12; $g <= 16; $g++) {
            $ug = User::factory()->create(['role' => 'guest']);
            $resG = $reserveAction->execute(new ReserveRoomDTO(
                propertyId: $this->property->id,
                roomTypeId: $this->deluxeType->id,
                ratePlanId: $this->barPlan->id,
                guestUserId: (string) $ug->id,
                checkInDate: '2026-11-05',
                checkOutDate: '2026-11-06',
                idempotencyKey: "sim-res-{$g}",
            ));
            $resG->update(['daily_rate_idr' => 1000000]);
            $rmNum = 94 + $g; // 106..110
            $rmG = HotelRoom::where('room_number', "DLX-{$rmNum}")->firstOrFail();
            $checkInAction->checkIn($resG->id, $rmG->id, 'KTP', "317100{$g}", "Tamu {$g}");
            $batchRooms[$g] = $resG;
        }

        // Guests 17-20 Check-in pada Hari 5 (SUI-202..205, 1 malam 2026-11-05 -> 2026-11-06)
        for ($g = 17; $g <= 20; $g++) {
            $ug = User::factory()->create(['role' => 'guest']);
            $resG = $reserveAction->execute(new ReserveRoomDTO(
                propertyId: $this->property->id,
                roomTypeId: $this->suiteType->id,
                ratePlanId: $this->barPlan->id,
                guestUserId: (string) $ug->id,
                checkInDate: '2026-11-05',
                checkOutDate: '2026-11-06',
                idempotencyKey: "sim-res-{$g}",
            ));
            $resG->update(['daily_rate_idr' => 2000000]);
            $rmNum = 185 + $g; // 202..205
            $rmG = HotelRoom::where('room_number', "SUI-{$rmNum}")->firstOrFail();
            $checkInAction->checkIn($resG->id, $rmG->id, 'KTP', "317100{$g}", "Tamu {$g}");
            $batchRooms[$g] = $resG;
        }

        // EKSEKUSI NIGHT AUDIT 5 (2026-11-05)
        $snap5 = $nightAuditAction->execute($this->property->id);
        $this->assertEquals('2026-11-06', $this->businessDateService->getCurrentBusinessDate($this->property->id));

        // ====================================================================
        // HARI 6 (2026-11-06)
        // ====================================================================
        // Check-out Guests 12-20
        foreach ($batchRooms as $g => $resG) {
            $folG = HotelFolio::where('reservation_id', $resG->id)->firstOrFail();
            $checkOutAction->execute(
                reservationId: $resG->id,
                cashReceivedIdr: $folG->outstanding_balance,
                cardReceivedIdr: 0,
                idempotencyKey: "sim-co-{$g}",
                cashierUserId: $this->cashier->id,
            );
        }

        // Check-out Guest 1 (setelah perpanjangan)
        $folio1->refresh();
        $checkOutAction->execute(
            reservationId: $res1->id,
            cashReceivedIdr: 0,
            cardReceivedIdr: max(0, $folio1->outstanding_balance - 500000),
            idempotencyKey: 'sim-co-1',
            cashierUserId: $this->cashier->id,
        );

        // Check-out Guest 9
        $folio9 = HotelFolio::where('reservation_id', $res9->id)->firstOrFail();
        $checkOutAction->execute(
            reservationId: $res9->id,
            cashReceivedIdr: 0,
            cardReceivedIdr: $folio9->outstanding_balance,
            idempotencyKey: 'sim-co-9',
            cashierUserId: $this->cashier->id,
        );

        // Check-out Guest 10
        $folio10 = HotelFolio::where('reservation_id', $res10->id)->firstOrFail();
        $checkOutAction->execute(
            reservationId: $res10->id,
            cashReceivedIdr: $folio10->outstanding_balance,
            cardReceivedIdr: 0,
            idempotencyKey: 'sim-co-10',
            cashierUserId: $this->cashier->id,
        );

        // EKSEKUSI NIGHT AUDIT 6 (2026-11-06)
        $snap6 = $nightAuditAction->execute($this->property->id);
        $this->assertEquals('2026-11-07', $this->businessDateService->getCurrentBusinessDate($this->property->id));

        // ====================================================================
        // HARI 7 (2026-11-07)
        // ====================================================================
        $folio11 = HotelFolio::where('reservation_id', $res11->id)->firstOrFail();
        $outletAction->execute($folio11->id, 150000, 'Room Service Snack', 'sim-out-d7-11');
        $folio11->refresh();

        // Split kamar ke CORP-BETA
        $transferAction->execute(
            folioId: $folio11->id,
            cityLedgerAccountIdOrCode: $corpBeta->id,
            amountIdr: $folio11->total_room_charges,
            notes: 'Tagihan akomodasi meeting dialihkan ke Beta',
            idempotencyKey: 'sim-split-11',
        );

        $folio11->refresh();
        $checkOutAction->execute(
            reservationId: $res11->id,
            cashReceivedIdr: $folio11->outstanding_balance,
            cardReceivedIdr: 0,
            idempotencyKey: 'sim-co-11',
            cashierUserId: $this->cashier->id,
        );

        // EKSEKUSI NIGHT AUDIT 7 (2026-11-07)
        $snap7 = $nightAuditAction->execute($this->property->id);
        $this->assertEquals('2026-11-08', $this->businessDateService->getCurrentBusinessDate($this->property->id));

        // ====================================================================
        // VALIDASI AKHIR REKONSILIASI 7 HARI (I3)
        // ====================================================================
        // 0. Total 20 reservasi bervariasi telah dibuat
        $this->assertEquals(20, HotelReservation::where('property_id', $this->property->id)->count());

        // 1. Seluruh saldo sisa deposit tamu = 0 (semua sudah applied atau refunded)
        $deposits = HotelDeposit::all();
        foreach ($deposits as $dep) {
            $unsettled = $dep->amount_idr - $dep->applied_amount_idr - $dep->refunded_amount_idr;
            $this->assertEquals(0, $unsettled, "Deposit {$dep->id} masih memiliki sisa saldo {$unsettled}!");
        }

        // 2. Seluruh folio tamu yang check-out telah settled (outstanding balance = 0)
        $checkedOutRes = HotelReservation::whereIn('status', ['checked_out', 'selesai'])->pluck('id');
        $this->assertNotEmpty($checkedOutRes);
        $folios = HotelFolio::whereIn('reservation_id', $checkedOutRes)->get();
        foreach ($folios as $fol) {
            $this->assertEquals(0, $fol->outstanding_balance, "Folio {$fol->id} belum nol!");
            $this->assertEquals('settled', $fol->status);
        }

        // 3. Mutasi pendapatan kamar di ledger cocok dengan akumulasi snapshot dan jumlah baris folio kamar
        $totalSnapshotRoomRev = (int) HotelDailyRevenueSnapshot::sum('total_room_revenue_idr');
        $ledgerRoomRevEntries = (int) LedgerEntry::whereHas('account', fn ($q) => $q->where('code', 'htl:room_revenue:IDR'))->sum('amount');
        $totalFolioRoomBase = (int) HotelFolioLine::where('line_type', 'ROOM_CHARGE')->sum('base_amount_idr');
        $this->assertEquals($totalFolioRoomBase, $totalSnapshotRoomRev);
        $this->assertEquals($totalSnapshotRoomRev, $ledgerRoomRevEntries);

        // 4. Laporan RevPAR 7 hari sesuai hitungan manual
        $reportService = app(HotelReportService::class);
        $rangeSnapshots = $reportService->getDateRangeReport($this->property->id, '2026-11-01', '2026-11-07');
        $this->assertCount(7, $rangeSnapshots);
        $this->assertEquals($totalSnapshotRoomRev, (int) $rangeSnapshots->sum('total_room_revenue_idr'));
        foreach ($rangeSnapshots as $snap) {
            $avail = max(0, $snap->total_rooms - $snap->out_of_order_rooms);
            $expectedRevpar = $avail > 0 ? (int) round($snap->total_room_revenue_idr / $avail) : 0;
            $this->assertEquals($expectedRevpar, $snap->revpar_idr);
        }

        // 5. City ledger balance bertambah sesuai transfer
        $corpAlpha->refresh();
        $corpBeta->refresh();
        $this->assertGreaterThan(0, $corpAlpha->outstanding_balance_idr);
        $this->assertGreaterThan(0, $corpBeta->outstanding_balance_idr);

        // 6. Artisan Command hotel:folio-audit, hotel:availability-audit, dan bank:reconcile lolos exit 0
        $this->assertEquals(0, Artisan::call('hotel:folio-audit'));
        $this->assertEquals(0, Artisan::call('hotel:availability-audit'));
        $this->assertEquals(0, Artisan::call('bank:reconcile'));
    }
}
