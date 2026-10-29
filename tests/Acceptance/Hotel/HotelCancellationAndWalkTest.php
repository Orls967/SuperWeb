<?php

declare(strict_types=1);

namespace Tests\Acceptance\Hotel;

use App\Models\User;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Banking\Domain\Models\LedgerEntry;
use Modules\Banking\Domain\Models\LedgerTransaction;
use Modules\Hotel\Application\Actions\CancelReservationAction;
use Modules\Hotel\Application\Actions\ExecuteWalkCompensationAction;
use Modules\Hotel\Application\Actions\ProcessGuestDepositAction;
use Modules\Hotel\Application\Actions\ReserveRoomAction;
use Modules\Hotel\Application\DTOs\ReserveRoomDTO;
use Modules\Hotel\database\seeders\HotelDemoSeeder;
use Modules\Hotel\Domain\Models\HotelProperty;
use Modules\Hotel\Domain\Models\HotelRatePlan;
use Modules\Hotel\Domain\Models\HotelRoom;
use Modules\Hotel\Domain\Models\HotelRoomNight;
use Modules\Hotel\Domain\Models\HotelRoomType;
use Tests\TestCase;

class HotelCancellationAndWalkTest extends TestCase
{
    use RefreshDatabase;

    private HotelProperty $property;

    private User $foStaff;

    private User $guest;

    private HotelRoomType $roomType;

    private HotelRatePlan $barPlan;

    private HotelRatePlan $nonRefPlan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            LedgerAccountsSeeder::class,
            HotelDemoSeeder::class,
        ]);

        $this->property = HotelProperty::firstOrFail();
        $this->foStaff = User::factory()->create(['role' => 'front_office']);
        $this->guest = User::factory()->create(['role' => 'guest']);
        $this->roomType = HotelRoomType::where('property_id', $this->property->id)->firstOrFail();
        $this->barPlan = HotelRatePlan::where('property_id', $this->property->id)->where('plan_type', 'BAR')->firstOrFail();
        $this->nonRefPlan = HotelRatePlan::where('property_id', $this->property->id)->where('plan_type', 'NON_REFUNDABLE')->firstOrFail();

        // Buat kamar tambahan agar ketersediaan inventaris selalu aman
        for ($i = 1; $i <= 5; $i++) {
            HotelRoom::create([
                'id' => (string) Str::uuid(),
                'property_id' => $this->property->id,
                'room_type_id' => $this->roomType->id,
                'room_number' => "CNL-{$i}",
                'room_type' => 'DELUXE',
                'base_rate_idr' => 1500000,
                'status' => 'available',
                'energy_setback_active' => true,
            ]);
        }
    }

    public function test_pembatalan_sebelum_batas_waktu_bebas_denda_dan_deposit_dikembalikan_penuh(): void
    {
        $reserveAction = app(ReserveRoomAction::class);
        $res = $reserveAction->execute(new ReserveRoomDTO(
            propertyId: $this->property->id,
            roomTypeId: $this->roomType->id,
            ratePlanId: $this->barPlan->id,
            guestUserId: (string) $this->guest->id,
            checkInDate: '2026-11-10',
            checkOutDate: '2026-11-13',
            idempotencyKey: 'res-cancel-free-01',
        ));
        $res->update(['status' => 'confirmed', 'daily_rate_idr' => 1500000]);

        $depositAction = app(ProcessGuestDepositAction::class);
        $deposit = $depositAction->execute(
            propertyId: $this->property->id,
            reservationId: $res->id,
            guestUserId: (string) $this->guest->id,
            amountIdr: 1500000,
            paymentMethod: 'TRANSFER',
            idempotencyKey: 'dep-cancel-free-01',
        );

        $cancelAction = app(CancelReservationAction::class);

        // Batalkan pada 2026-11-05 (5 hari sebelum check in, jauh sebelum batas 24 jam)
        $result = $cancelAction->execute(
            reservationId: $res->id,
            idempotencyKey: 'cancel-free-key-01',
            reason: 'Perubahan rencana perjalanan',
            cancellationTime: '2026-11-05 10:00:00',
        );

        $this->assertTrue($result['is_free']);
        $this->assertEquals(0, $result['penalty_idr']);
        $this->assertEquals(1500000, $result['deposit_refunded_idr']);

        $res->refresh();
        $deposit->refresh();

        $this->assertEquals('dibatalkan', $res->status);
        $this->assertEquals('REFUNDED', $deposit->status);
        $this->assertEquals(1500000, $deposit->refunded_amount_idr);

        // Inventaris kamar dilepaskan
        $this->assertEquals(0, HotelRoomNight::where('reservation_id', $res->id)->count());

        // Cek ledger refund
        $tx = LedgerTransaction::where('type', 'HOTEL_DEPOSIT_REFUND')
            ->where('reference_id', $deposit->id)
            ->firstOrFail();
        $entries = LedgerEntry::where('transaction_id', $tx->id)->get();
        $this->assertEquals(0, $entries->sum('amount'));
    }

    public function test_pembatalan_setelah_lewat_batas_waktu_dikenakan_denda_sebesar_satu_malam(): void
    {
        $reserveAction = app(ReserveRoomAction::class);
        $res = $reserveAction->execute(new ReserveRoomDTO(
            propertyId: $this->property->id,
            roomTypeId: $this->roomType->id,
            ratePlanId: $this->barPlan->id,
            guestUserId: (string) $this->guest->id,
            checkInDate: '2026-11-10',
            checkOutDate: '2026-11-12',
            idempotencyKey: 'res-cancel-penalty-01',
        ));
        $res->update(['status' => 'confirmed', 'daily_rate_idr' => 1500000]);

        $cancelAction = app(CancelReservationAction::class);

        // Batalkan pada 2026-11-10 12:00:00 (hanya 2 jam sebelum check in 14:00, batas waktu 24 jam sudah lewat)
        $result = $cancelAction->execute(
            reservationId: $res->id,
            idempotencyKey: 'cancel-penalty-key-01',
            reason: 'Batal mendadak',
            cancellationTime: '2026-11-10 12:00:00',
        );

        $this->assertFalse($result['is_free']);
        $this->assertEquals(1500000, $result['penalty_idr']); // 1 malam denda

        $res->refresh();
        $this->assertEquals('dibatalkan_denda', $res->status);
        $this->assertEquals(0, HotelRoomNight::where('reservation_id', $res->id)->count());
    }

    public function test_denda_pembatalan_dipotong_dari_deposit_dan_diakui_sebagai_pendapatan_denda(): void
    {
        $reserveAction = app(ReserveRoomAction::class);
        $res = $reserveAction->execute(new ReserveRoomDTO(
            propertyId: $this->property->id,
            roomTypeId: $this->roomType->id,
            ratePlanId: $this->barPlan->id,
            guestUserId: (string) $this->guest->id,
            checkInDate: '2026-11-10',
            checkOutDate: '2026-11-13',
            idempotencyKey: 'res-cancel-dep-01',
        ));
        $res->update(['status' => 'confirmed', 'daily_rate_idr' => 1200000]);

        // Tamu membayar deposit 2.000.000
        $depositAction = app(ProcessGuestDepositAction::class);
        $deposit = $depositAction->execute(
            propertyId: $this->property->id,
            reservationId: $res->id,
            guestUserId: (string) $this->guest->id,
            amountIdr: 2000000,
            paymentMethod: 'CARD',
            idempotencyKey: 'dep-cancel-split-01',
        );

        $cancelAction = app(CancelReservationAction::class);

        // Batalkan telat: Denda 1 malam = 1.200.000. Sisa 800.000 dikembalikan.
        $result = $cancelAction->execute(
            reservationId: $res->id,
            idempotencyKey: 'cancel-with-dep-01',
            reason: 'Membatalkan di hari kedatangan',
            cancellationTime: '2026-11-10 10:00:00',
        );

        $this->assertEquals(1200000, $result['penalty_idr']);
        $this->assertEquals(800000, $result['deposit_refunded_idr']);

        $deposit->refresh();
        $this->assertEquals(1200000, $deposit->applied_amount_idr);
        $this->assertEquals(800000, $deposit->refunded_amount_idr);

        // Cek pengakuan pendapatan denda di ledger
        $txPenalty = LedgerTransaction::where('type', 'HOTEL_CANCELLATION_PENALTY')
            ->where('reference_id', $res->id)
            ->firstOrFail();
        $entries = LedgerEntry::where('transaction_id', $txPenalty->id)->get();
        $this->assertEquals(0, $entries->sum('amount'));

        $feeCredit = $entries->where('amount', '>', 0)->first();
        $this->assertNotNull($feeCredit);
        $this->assertEquals(1200000, (int) $feeCredit->amount);
    }

    public function test_walk_tamu_overbooking_mencatat_kompensasi_sebagai_beban_operasional_seimbang(): void
    {
        $reserveAction = app(ReserveRoomAction::class);
        $res = $reserveAction->execute(new ReserveRoomDTO(
            propertyId: $this->property->id,
            roomTypeId: $this->roomType->id,
            ratePlanId: $this->barPlan->id,
            guestUserId: (string) $this->guest->id,
            checkInDate: '2026-11-10',
            checkOutDate: '2026-11-12',
            idempotencyKey: 'res-walk-01',
        ));
        $res->update(['status' => 'confirmed', 'daily_rate_idr' => 1500000]);

        $walkAction = app(ExecuteWalkCompensationAction::class);
        $result = $walkAction->execute(
            reservationId: $res->id,
            alternatePropertyName: 'Hotel Mitra Bintang Lima',
            idempotencyKey: 'walk-comp-test-01',
        );

        $expectedCompensation = 1500000 + 350000 + 1000000; // 2.850.000
        $this->assertEquals($expectedCompensation, $result['total_compensation_idr']);

        // Assert posting double entry seimbang di Banking Ledger
        $tx = LedgerTransaction::where('type', 'HOTEL_OVERBOOKING_WALK')
            ->where('reference_id', $res->id)
            ->firstOrFail();
        $entries = LedgerEntry::where('transaction_id', $tx->id)->get();
        $this->assertEquals(0, $entries->sum('amount')); // Balanced
    }

    public function test_relokasi_tamu_walk_ke_properti_mitra_memperbarui_status_reservasi_relocated(): void
    {
        $reserveAction = app(ReserveRoomAction::class);
        $res = $reserveAction->execute(new ReserveRoomDTO(
            propertyId: $this->property->id,
            roomTypeId: $this->roomType->id,
            ratePlanId: $this->barPlan->id,
            guestUserId: (string) $this->guest->id,
            checkInDate: '2026-11-10',
            checkOutDate: '2026-11-12',
            idempotencyKey: 'res-walk-reloc-01',
        ));

        $walkAction = app(ExecuteWalkCompensationAction::class);
        $result = $walkAction->execute(
            reservationId: $res->id,
            alternatePropertyName: 'Aston Kartika Hotel',
            idempotencyKey: 'walk-reloc-key-01',
        );

        $res->refresh();
        $this->assertEquals('relocated_walk', $res->status);
        $this->assertEquals(0, HotelRoomNight::where('reservation_id', $res->id)->count());
    }
}
