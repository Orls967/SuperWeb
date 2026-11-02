<?php

declare(strict_types=1);

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Contracts\DocumentNumberingInterface;
use Modules\Hotel\Application\Actions\ReserveRoomAction;
use Modules\Hotel\Application\DTOs\ReserveRoomDTO;
use Modules\Hotel\Application\Services\HotelInventoryService;
use Modules\Hotel\tests\Unit\HotelUnitTestHelper;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class, HotelUnitTestHelper::class);

covers(ReserveRoomAction::class);

beforeEach(function () {
    $this->setupHotelFixture();
});

it('rejects empty idempotency key on reserve room', function () {
    $action = app(ReserveRoomAction::class);
    $dto = new ReserveRoomDTO(
        propertyId: $this->property->id,
        roomTypeId: $this->roomType->id,
        ratePlanId: $this->barPlan->id,
        guestUserId: (string) $this->guest->id,
        checkInDate: now()->addDays(1)->toDateString(),
        checkOutDate: now()->addDays(3)->toDateString(),
        idempotencyKey: '   '
    );

    $action->handle($dto);
})->throws(InvalidArgumentException::class, 'Idempotency-Key wajib disertakan.');

it('rejects when check out date is before or equal to check in date', function () {
    $action = app(ReserveRoomAction::class);
    $dto = new ReserveRoomDTO(
        propertyId: $this->property->id,
        roomTypeId: $this->roomType->id,
        ratePlanId: $this->barPlan->id,
        guestUserId: (string) $this->guest->id,
        checkInDate: now()->addDays(3)->toDateString(),
        checkOutDate: now()->addDays(2)->toDateString(),
        idempotencyKey: 'key_invalid_dates'
    );

    $action->handle($dto);
})->throws(InvalidArgumentException::class, 'Tanggal check-in tidak boleh melampaui tanggal check-out.');

it('rejects rate plan requiring deposit when deposit not provided', function () {
    $this->barPlan->update(['requires_deposit' => true]);

    $action = app(ReserveRoomAction::class);
    $dto = new ReserveRoomDTO(
        propertyId: $this->property->id,
        roomTypeId: $this->roomType->id,
        ratePlanId: $this->barPlan->id,
        guestUserId: (string) $this->guest->id,
        checkInDate: now()->addDays(2)->toDateString(),
        checkOutDate: now()->addDays(4)->toDateString(),
        depositAmountIdr: null,
        idempotencyKey: 'key_no_dep'
    );

    $action->handle($dto);
})->throws(InvalidArgumentException::class, 'mewajibkan pembayaran deposit di muka');

it('creates reservation successfully and returns existing on same key', function () {
    $dateIn = Carbon::now()->addDays(5)->toDateString();
    $dateOut = Carbon::now()->addDays(7)->toDateString();

    $docNumberMock = Mockery::mock(DocumentNumberingInterface::class);
    $docNumberMock->shouldReceive('nextNumber')->with('HTL', 'RSV')->andReturn('HTL/RSV/TEST-001');

    $action = new ReserveRoomAction(
        inventoryService: app(HotelInventoryService::class),
        documentNumbering: $docNumberMock
    );

    $dto = new ReserveRoomDTO(
        propertyId: $this->property->id,
        roomTypeId: $this->roomType->id,
        ratePlanId: $this->barPlan->id,
        guestUserId: (string) $this->guest->id,
        checkInDate: $dateIn,
        checkOutDate: $dateOut,
        idempotencyKey: 'res_key_unit_01'
    );

    $res1 = $action->handle($dto);
    expect($res1['reservation']->status)->toBe('confirmed')
        ->and($res1['nights'])->toBe(2)
        ->and($res1['reservation']->reservation_number)->toBe('HTL/RSV/TEST-001')
        ->and($res1['folio'])->not->toBeNull()
        ->and($res1['daily_rate_idr'])->toBeGreaterThan(0)
        ->and($res1['total_room_charge_idr'])->toBeGreaterThan(0);

    // Call execute delegation
    $resExecuted = $action->execute($dto);
    expect($resExecuted->id)->toBe($res1['reservation']->id);

    // Idempotency: second call returns same reservation and folio
    $resDup = $action->handle($dto);
    expect($resDup['reservation']->id)->toBe($res1['reservation']->id)
        ->and($resDup['folio']->id)->toBe($res1['folio']->id)
        ->and($resDup['nights'])->toBe(2);
});
