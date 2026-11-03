<?php

declare(strict_types=1);

namespace Modules\Hotel\tests\Feature;

use App\Models\User;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Modules\Hotel\Application\Actions\ReserveRoomWithInventoryNightLockAction;
use Modules\Hotel\Application\Services\HotelBusinessDateService;
use Modules\Hotel\database\seeders\HotelDemoSeeder;
use Modules\Hotel\Domain\Models\HotelProperty;
use Modules\Hotel\Domain\Models\HotelRoom;
use Tests\TestCase;

class ReserveRoomAntiOverlapRegressionTest extends TestCase
{
    use RefreshDatabase;

    private HotelProperty $property;

    private HotelRoom $room;

    private User $guest;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            LedgerAccountsSeeder::class,
            HotelDemoSeeder::class,
        ]);

        $this->property = HotelProperty::firstOrFail();
        $this->room = HotelRoom::create([
            'id' => (string) Str::uuid(),
            'property_id' => $this->property->id,
            'room_number' => 'REGRESS-101',
            'room_type' => 'deluxe',
            'base_rate_idr' => 1000000,
            'status' => 'available',
        ]);
        $this->guest = User::factory()->create(['role' => 'customer']);
    }

    public function test_anti_overlap_query_correctly_blocks_booking_inside_existing_reservation(): void
    {
        $action = app(ReserveRoomWithInventoryNightLockAction::class);
        $bizDate = app(HotelBusinessDateService::class)->getCurrentBusinessDate($this->property->id);
        $dIn = Carbon::parse($bizDate)->toDateString();
        $dOut = Carbon::parse($bizDate)->addDays(4)->toDateString();
        $dMidIn = Carbon::parse($bizDate)->addDays(1)->toDateString();
        $dMidOut = Carbon::parse($bizDate)->addDays(2)->toDateString();

        // Booking 1: 4 malam
        $res1 = $action->handle(
            propertyId: $this->property->id,
            roomId: $this->room->id,
            guestUserId: (string) $this->guest->id,
            checkInDate: $dIn,
            checkOutDate: $dOut,
            dailyRateIdr: 1000000
        );
        $this->assertEquals('confirmed', $res1['reservation']->status);

        // Booking 2 di tengah masa inap wajib ditolak!
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Room {$this->room->room_number} is already booked for the selected dates.");

        $action->handle(
            propertyId: $this->property->id,
            roomId: $this->room->id,
            guestUserId: (string) $this->guest->id,
            checkInDate: $dMidIn,
            checkOutDate: $dMidOut,
            dailyRateIdr: 1000000
        );
    }

    public function test_back_to_back_booking_on_same_checkout_checkin_day_is_allowed(): void
    {
        $action = app(ReserveRoomWithInventoryNightLockAction::class);
        $bizDate = app(HotelBusinessDateService::class)->getCurrentBusinessDate($this->property->id);
        $dIn1 = Carbon::parse($bizDate)->toDateString();
        $dOut1 = Carbon::parse($bizDate)->addDays(2)->toDateString();
        $dIn2 = Carbon::parse($bizDate)->addDays(2)->toDateString();
        $dOut2 = Carbon::parse($bizDate)->addDays(4)->toDateString();

        // Booking 1
        $res1 = $action->handle(
            propertyId: $this->property->id,
            roomId: $this->room->id,
            guestUserId: (string) $this->guest->id,
            checkInDate: $dIn1,
            checkOutDate: $dOut1,
            dailyRateIdr: 1000000
        );
        $this->assertEquals('confirmed', $res1['reservation']->status);

        // Booking 2 dimulai tepat saat booking 1 checkout -> Harus diizinkan!
        $res2 = $action->handle(
            propertyId: $this->property->id,
            roomId: $this->room->id,
            guestUserId: (string) $this->guest->id,
            checkInDate: $dIn2,
            checkOutDate: $dOut2,
            dailyRateIdr: 1000000
        );
        $this->assertEquals('confirmed', $res2['reservation']->status);
    }

    public function test_single_night_booking_folio_and_checked_in_overlap_invariants(): void
    {
        $action = app(ReserveRoomWithInventoryNightLockAction::class);

        $res = $action->handle(
            propertyId: $this->property->id,
            roomId: $this->room->id,
            guestUserId: (string) $this->guest->id,
            checkInDate: '2026-11-10',
            checkOutDate: '2026-11-11',
            dailyRateIdr: 1500000
        );

        $reservation = $res['reservation'];
        $folio = $res['folio'];

        $this->assertEquals(1, $res['nights']);
        $this->assertEquals(1500000, $res['total_room_charge_idr']);
        $this->assertStringStartsWith('HTL/RSV/', $reservation->reservation_number);
        $this->assertMatchesRegularExpression('/^HTL\/RSV\/\d{6}-\d{5}$/', $reservation->reservation_number);

        $this->assertEquals(1500000, $folio->total_room_charges);
        $this->assertEquals(0, $folio->total_addon_charges);
        $this->assertEquals(0, $folio->total_paid);
        $this->assertEquals(1500000, $folio->outstanding_balance);
        $this->assertEquals('open', $folio->status);

        // Ubah status menjadi checked_in dan pastikan overlap tetap ditolak
        $reservation->update(['status' => 'checked_in']);

        $this->expectException(\RuntimeException::class);
        $action->handle(
            propertyId: $this->property->id,
            roomId: $this->room->id,
            guestUserId: (string) $this->guest->id,
            checkInDate: '2026-11-10',
            checkOutDate: '2026-11-11',
            dailyRateIdr: 1500000
        );
    }
}
