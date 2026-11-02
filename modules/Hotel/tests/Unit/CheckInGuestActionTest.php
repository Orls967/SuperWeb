<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Core\Contracts\AuditTrailInterface;
use Modules\Hotel\Application\Actions\CheckInGuestAction;
use Modules\Hotel\Application\Services\HotelInventoryService;
use Modules\Hotel\Domain\Models\HotelHousekeepingLog;
use Modules\Hotel\Domain\Models\HotelReservation;
use Modules\Hotel\Domain\Models\HotelRoom;
use Modules\Hotel\Domain\Models\HotelRoomNight;
use Modules\Hotel\tests\Unit\HotelUnitTestHelper;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class, HotelUnitTestHelper::class);

covers(CheckInGuestAction::class);

beforeEach(function () {
    $this->setupHotelFixture();
});

it('rejects check-in when reservation is not confirmed', function () {
    $reservation = HotelReservation::create([
        'id' => (string) Str::uuid(),
        'reservation_number' => 'HTL/RSV/CHECKIN-CANCELLED',
        'property_id' => $this->property->id,
        'room_id' => null,
        'guest_user_id' => $this->guest->id,
        'check_in_date' => now()->toDateString(),
        'check_out_date' => now()->addDays(1)->toDateString(),
        'daily_rate_idr' => 1000000,
        'total_room_charge_idr' => 1000000,
        'status' => 'dibatalkan',
    ]);

    $action = app(CheckInGuestAction::class);
    $action->handle(
        reservationId: $reservation->id,
        roomId: $this->room->id,
        identityType: 'KTP',
        identityNumber: '3171012345678901',
        fullName: 'Budi Santoso'
    );
})->throws(InvalidArgumentException::class, "Reservasi berstatus 'dibatalkan' tidak dapat di-check-in.");

it('rejects check-in when room is not inspected or available', function () {
    $this->room->update(['status' => 'dirty']);

    $reservation = HotelReservation::create([
        'id' => (string) Str::uuid(),
        'reservation_number' => 'HTL/RSV/CHECKIN-FAIL',
        'property_id' => $this->property->id,
        'room_id' => null,
        'guest_user_id' => $this->guest->id,
        'check_in_date' => now()->toDateString(),
        'check_out_date' => now()->addDays(1)->toDateString(),
        'daily_rate_idr' => 1000000,
        'total_room_charge_idr' => 1000000,
        'status' => 'confirmed',
    ]);

    $action = app(CheckInGuestAction::class);
    $action->handle(
        reservationId: $reservation->id,
        roomId: $this->room->id,
        identityType: 'KTP',
        identityNumber: '3171012345678901',
        fullName: 'Budi Santoso'
    );
})->throws(InvalidArgumentException::class);

it('successfully checks in guest into inspected room via handle and checkIn delegation', function () {
    $auditMock = Mockery::mock(AuditTrailInterface::class);
    $auditMock->shouldReceive('record')->atLeast()->once();
    $this->app->instance(AuditTrailInterface::class, $auditMock);

    $this->room->update(['status' => 'inspected']);

    $reservation = HotelReservation::create([
        'id' => (string) Str::uuid(),
        'reservation_number' => 'HTL/RSV/CHECKIN-OK',
        'property_id' => $this->property->id,
        'room_id' => null,
        'guest_user_id' => $this->guest->id,
        'check_in_date' => now()->toDateString(),
        'check_out_date' => now()->addDays(1)->toDateString(),
        'daily_rate_idr' => 1000000,
        'total_room_charge_idr' => 1000000,
        'status' => 'confirmed',
    ]);

    HotelRoomNight::create([
        'id' => (string) Str::uuid(),
        'property_id' => $this->property->id,
        'room_type_id' => $this->roomType->id,
        'reservation_id' => $reservation->id,
        'room_id' => null,
        'night_date' => now()->toDateString(),
        'rate_idr' => 1000000,
        'status' => 'reserved',
    ]);

    $action = app(CheckInGuestAction::class);
    $result = $action->checkIn(
        reservationId: $reservation->id,
        assignedRoomId: $this->room->id,
        idType: 'ktp',
        idNumber: '3171019998880001',
        fullName: 'Ahmad Dahlan',
        phone: '08123456789'
    );

    expect($result['reservation']->status)->toBe('checked_in')
        ->and($result['reservation']->room_id)->toBe($this->room->id)
        ->and($result['room']->status)->toBe('occupied')
        ->and($result['identity']->full_name)->toBe('Ahmad Dahlan')
        ->and($result['identity']->identity_type)->toBe('KTP');

    $this->room->refresh();
    expect($this->room->status)->toBe('occupied');

    expect(HotelHousekeepingLog::where('room_id', $this->room->id)->where('to_status', 'occupied')->exists())->toBeTrue();
    expect(HotelRoomNight::where('reservation_id', $reservation->id)->first()->room_id)->toBe($this->room->id);
});

it('moves in-house guest to another available room successfully', function () {
    $this->room->update(['status' => 'occupied']);

    $newRoom = HotelRoom::create([
        'id' => (string) Str::uuid(),
        'property_id' => $this->property->id,
        'room_type_id' => $this->roomType->id,
        'room_type' => $this->roomType->code ?? 'DELUXE',
        'base_rate_idr' => 1_000_000,
        'room_number' => '102',
        'floor' => 1,
        'status' => 'available',
    ]);

    $reservation = HotelReservation::create([
        'id' => (string) Str::uuid(),
        'reservation_number' => 'HTL/RSV/MOVE-01',
        'property_id' => $this->property->id,
        'room_id' => $this->room->id,
        'guest_user_id' => $this->guest->id,
        'check_in_date' => now()->toDateString(),
        'check_out_date' => now()->addDays(2)->toDateString(),
        'daily_rate_idr' => 1000000,
        'total_room_charge_idr' => 2000000,
        'status' => 'checked_in',
    ]);

    HotelRoomNight::create([
        'id' => (string) Str::uuid(),
        'property_id' => $this->property->id,
        'room_type_id' => $this->roomType->id,
        'reservation_id' => $reservation->id,
        'room_id' => $this->room->id,
        'night_date' => now()->toDateString(),
        'rate_idr' => 1000000,
        'status' => 'reserved',
    ]);

    $action = app(CheckInGuestAction::class);
    $res = $action->moveRoom(
        reservationId: $reservation->id,
        newRoomId: $newRoom->id,
        userId: (int) $this->guest->id
    );

    expect($res['old_room']->status)->toBe('dirty')
        ->and($res['new_room']->status)->toBe('occupied')
        ->and($res['reservation']->room_id)->toBe($newRoom->id);

    $this->room->refresh();
    $newRoom->refresh();
    expect($this->room->status)->toBe('dirty')
        ->and($newRoom->status)->toBe('occupied');

    expect(HotelRoomNight::where('reservation_id', $reservation->id)->first()->room_id)->toBe($newRoom->id);
});

it('rejects move room when guest is not in-house or target room not available', function () {
    $reservation = HotelReservation::create([
        'id' => (string) Str::uuid(),
        'reservation_number' => 'HTL/RSV/MOVE-FAIL',
        'property_id' => $this->property->id,
        'room_id' => $this->room->id,
        'guest_user_id' => $this->guest->id,
        'check_in_date' => now()->toDateString(),
        'check_out_date' => now()->addDays(2)->toDateString(),
        'daily_rate_idr' => 1000000,
        'total_room_charge_idr' => 2000000,
        'status' => 'confirmed',
    ]);

    $action = app(CheckInGuestAction::class);
    expect(fn () => $action->moveRoom($reservation->id, $this->room->id, (int) $this->guest->id))
        ->toThrow(InvalidArgumentException::class, 'Pindah kamar hanya dapat dilakukan untuk tamu in-house.');

    $reservation->update(['status' => 'checked_in']);
    $dirtyRoom = HotelRoom::create([
        'id' => (string) Str::uuid(),
        'property_id' => $this->property->id,
        'room_type_id' => $this->roomType->id,
        'room_type' => $this->roomType->code ?? 'DELUXE',
        'base_rate_idr' => 1_000_000,
        'room_number' => '103',
        'floor' => 1,
        'status' => 'dirty',
    ]);

    expect(fn () => $action->moveRoom($reservation->id, $dirtyRoom->id, (int) $this->guest->id))
        ->toThrow(InvalidArgumentException::class, 'Kamar baru 103 tidak tersedia (status: dirty).');
});

it('extends in-house guest stay and updates checkout date and total charges', function () {
    $reservation = HotelReservation::create([
        'id' => (string) Str::uuid(),
        'reservation_number' => 'HTL/RSV/EXT-01',
        'property_id' => $this->property->id,
        'room_type_id' => $this->roomType->id,
        'room_id' => $this->room->id,
        'guest_user_id' => $this->guest->id,
        'check_in_date' => now()->toDateString(),
        'check_out_date' => now()->addDays(2)->toDateString(),
        'daily_rate_idr' => 500000,
        'total_room_charge_idr' => 1000000,
        'status' => 'checked_in',
    ]);

    $invMock = Mockery::mock(HotelInventoryService::class);
    $invMock->shouldReceive('lockAndAssignNights')->once();

    $action = new CheckInGuestAction($invMock);
    $res = $action->extendStay(
        reservationId: $reservation->id,
        additionalNights: 2
    );

    $expectedOut = now()->addDays(4)->toDateString();
    expect($res['new_check_out_date'])->toBe($expectedOut);

    $reservation->refresh();
    expect($reservation->check_out_date->toDateString())->toBe($expectedOut)
        ->and($reservation->total_room_charge_idr)->toBe(2000000);
});

it('rejects stay extension when guest not in-house', function () {
    $reservation = HotelReservation::create([
        'id' => (string) Str::uuid(),
        'reservation_number' => 'HTL/RSV/EXT-FAIL',
        'property_id' => $this->property->id,
        'room_id' => $this->room->id,
        'guest_user_id' => $this->guest->id,
        'check_in_date' => now()->toDateString(),
        'check_out_date' => now()->addDays(1)->toDateString(),
        'daily_rate_idr' => 500000,
        'total_room_charge_idr' => 500000,
        'status' => 'confirmed',
    ]);

    $action = app(CheckInGuestAction::class);
    $action->extendStay($reservation->id, 1);
})->throws(InvalidArgumentException::class, 'Perpanjangan masa menginap hanya dapat dilakukan untuk tamu in-house.');

it('updates housekeeping status following valid state transitions and rejects invalid', function () {
    $action = app(CheckInGuestAction::class);

    $this->room->update(['status' => 'dirty']);

    // dirty -> cleaning
    $room = $action->updateHousekeepingStatus($this->room->id, 'cleaning', (int) $this->guest->id, 'Mulai dibersihkan');
    expect($room->status)->toBe('cleaning');

    // cleaning -> inspected
    $room = $action->updateHousekeepingStatus($this->room->id, 'inspected', (int) $this->guest->id);
    expect($room->status)->toBe('inspected');

    // inspected -> available
    $room = $action->updateHousekeepingStatus($this->room->id, 'available', (int) $this->guest->id);
    expect($room->status)->toBe('available');

    // available -> out_of_order
    $room = $action->updateHousekeepingStatus($this->room->id, 'out_of_order', (int) $this->guest->id);
    expect($room->status)->toBe('out_of_order');

    // out_of_order -> dirty
    $room = $action->updateHousekeepingStatus($this->room->id, 'dirty', (int) $this->guest->id);
    expect($room->status)->toBe('dirty');

    // invalid: dirty -> available directly
    expect(fn () => $action->updateHousekeepingStatus($this->room->id, 'available', (int) $this->guest->id))
        ->toThrow(InvalidArgumentException::class, "Transisi status housekeeping tidak valid: dari 'dirty' ke 'available'.");
});
