<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Hotel\Application\Actions\ReserveRoomWithInventoryNightLockAction;
use Modules\Hotel\Domain\Models\HotelReservation;
use Modules\Hotel\tests\Unit\HotelUnitTestHelper;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class, HotelUnitTestHelper::class);

covers(ReserveRoomWithInventoryNightLockAction::class);

beforeEach(function () {
    $this->setupHotelFixture();
});

it('rejects reservation when checkout is before or equal checkin date', function () {
    $action = app(ReserveRoomWithInventoryNightLockAction::class);
    $action->handle(
        propertyId: $this->property->id,
        roomId: $this->room->id,
        guestUserId: (string) $this->guest->id,
        checkInDate: '2026-11-10',
        checkOutDate: '2026-11-09',
        dailyRateIdr: 1000000
    );
})->throws(InvalidArgumentException::class, 'Check-out date must be after check-in date.');

it('rejects reservation when overlapping booking exists', function () {
    HotelReservation::create([
        'id' => (string) Str::uuid(),
        'reservation_number' => 'HTL/RSV/LOCK-001',
        'property_id' => $this->property->id,
        'room_id' => $this->room->id,
        'guest_user_id' => $this->guest->id,
        'check_in_date' => '2026-11-10',
        'check_out_date' => '2026-11-15',
        'daily_rate_idr' => 1000000,
        'total_room_charge_idr' => 5000000,
        'status' => 'confirmed',
    ]);

    $action = app(ReserveRoomWithInventoryNightLockAction::class);
    $action->handle(
        propertyId: $this->property->id,
        roomId: $this->room->id,
        guestUserId: (string) $this->guest->id,
        checkInDate: '2026-11-12',
        checkOutDate: '2026-11-17',
        dailyRateIdr: 1000000
    );
})->throws(RuntimeException::class, 'is already booked for the selected dates.');

it('successfully reserves room with night lock', function () {
    $action = app(ReserveRoomWithInventoryNightLockAction::class);
    $result = $action->handle(
        propertyId: $this->property->id,
        roomId: $this->room->id,
        guestUserId: (string) $this->guest->id,
        checkInDate: '2026-11-20',
        checkOutDate: '2026-11-23',
        dailyRateIdr: 1200000
    );

    expect($result['reservation']->status)->toBe('confirmed')
        ->and((int) $result['nights'])->toBe(3)
        ->and((int) $result['total_room_charge_idr'])->toBe(3600000)
        ->and((int) $result['folio']->outstanding_balance)->toBe(3600000);
});
