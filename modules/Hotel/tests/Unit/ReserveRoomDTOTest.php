<?php

declare(strict_types=1);

use Modules\Hotel\Application\DTOs\ReserveRoomDTO;
use Tests\TestCase;

uses(TestCase::class);

covers(ReserveRoomDTO::class);

it('instantiates valid reserve room dto with all properties', function () {
    $dto = new ReserveRoomDTO(
        propertyId: 'prop-1',
        roomTypeId: 'type-1',
        ratePlanId: 'plan-1',
        guestUserId: 'guest-1',
        checkInDate: '2026-11-10',
        checkOutDate: '2026-11-12',
        idempotencyKey: 'key-123',
        roomId: 'room-1',
        depositAmountIdr: 500000
    );

    expect($dto->propertyId)->toBe('prop-1')
        ->and($dto->roomTypeId)->toBe('type-1')
        ->and($dto->ratePlanId)->toBe('plan-1')
        ->and($dto->guestUserId)->toBe('guest-1')
        ->and($dto->checkInDate)->toBe('2026-11-10')
        ->and($dto->checkOutDate)->toBe('2026-11-12')
        ->and($dto->idempotencyKey)->toBe('key-123')
        ->and($dto->roomId)->toBe('room-1')
        ->and($dto->depositAmountIdr)->toBe(500000);
});

it('instantiates reserve room dto with zero deposit', function () {
    $dto = new ReserveRoomDTO(
        propertyId: 'prop-1',
        roomTypeId: 'type-1',
        ratePlanId: 'plan-1',
        guestUserId: 'guest-1',
        checkInDate: '2026-11-10',
        checkOutDate: '2026-11-10',
        idempotencyKey: 'key-123',
        depositAmountIdr: 0
    );

    expect($dto->depositAmountIdr)->toBe(0);
});

it('instantiates reserve room dto with default null optionals', function () {
    $dto = new ReserveRoomDTO(
        propertyId: 'prop-2',
        roomTypeId: 'type-2',
        ratePlanId: 'plan-2',
        guestUserId: 'guest-2',
        checkInDate: '2026-11-15',
        checkOutDate: '2026-11-17',
        idempotencyKey: 'key-456'
    );

    expect($dto->roomId)->toBeNull()
        ->and($dto->depositAmountIdr)->toBeNull();
});

it('rejects empty idempotency key', function () {
    new ReserveRoomDTO(
        propertyId: 'prop-1',
        roomTypeId: 'type-1',
        ratePlanId: 'plan-1',
        guestUserId: 'guest-1',
        checkInDate: '2026-11-10',
        checkOutDate: '2026-11-12',
        idempotencyKey: '   '
    );
})->throws(InvalidArgumentException::class, 'Idempotency-Key wajib disertakan.');

it('rejects check-in date after check-out date', function () {
    new ReserveRoomDTO(
        propertyId: 'prop-1',
        roomTypeId: 'type-1',
        ratePlanId: 'plan-1',
        guestUserId: 'guest-1',
        checkInDate: '2026-11-15',
        checkOutDate: '2026-11-10',
        idempotencyKey: 'key-123'
    );
})->throws(InvalidArgumentException::class, 'Tanggal check-in tidak boleh melampaui tanggal check-out.');

it('rejects negative deposit amount', function () {
    new ReserveRoomDTO(
        propertyId: 'prop-1',
        roomTypeId: 'type-1',
        ratePlanId: 'plan-1',
        guestUserId: 'guest-1',
        checkInDate: '2026-11-10',
        checkOutDate: '2026-11-12',
        idempotencyKey: 'key-123',
        depositAmountIdr: -1
    );
})->throws(InvalidArgumentException::class, 'Nominal deposit tidak boleh negatif.');
