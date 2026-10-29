<?php

declare(strict_types=1);

namespace Modules\Hotel\tests\Feature;

use App\Models\User;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Hotel\Application\Actions\ReserveRoomWithInventoryNightLockAction;
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

        // Booking 1: 2026-11-01 to 2026-11-05
        $res1 = $action->handle(
            propertyId: $this->property->id,
            roomId: $this->room->id,
            guestUserId: (string) $this->guest->id,
            checkInDate: '2026-11-01',
            checkOutDate: '2026-11-05',
            dailyRateIdr: 1000000
        );
        $this->assertEquals('confirmed', $res1['reservation']->status);

        // Booking 2 di tengah masa inap (2026-11-02 s/d 2026-11-03) wajib ditolak!
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Room {$this->room->room_number} is already booked for the selected dates.");

        $action->handle(
            propertyId: $this->property->id,
            roomId: $this->room->id,
            guestUserId: (string) $this->guest->id,
            checkInDate: '2026-11-02',
            checkOutDate: '2026-11-03',
            dailyRateIdr: 1000000
        );
    }

    public function test_back_to_back_booking_on_same_checkout_checkin_day_is_allowed(): void
    {
        $action = app(ReserveRoomWithInventoryNightLockAction::class);

        // Booking 1: 2026-11-01 to 2026-11-03
        $res1 = $action->handle(
            propertyId: $this->property->id,
            roomId: $this->room->id,
            guestUserId: (string) $this->guest->id,
            checkInDate: '2026-11-01',
            checkOutDate: '2026-11-03',
            dailyRateIdr: 1000000
        );
        $this->assertEquals('confirmed', $res1['reservation']->status);

        // Booking 2 dimulai tepat saat booking 1 checkout (2026-11-03 to 2026-11-05) -> Harus diizinkan!
        $res2 = $action->handle(
            propertyId: $this->property->id,
            roomId: $this->room->id,
            guestUserId: (string) $this->guest->id,
            checkInDate: '2026-11-03',
            checkOutDate: '2026-11-05',
            dailyRateIdr: 1000000
        );
        $this->assertEquals('confirmed', $res2['reservation']->status);
    }
}
