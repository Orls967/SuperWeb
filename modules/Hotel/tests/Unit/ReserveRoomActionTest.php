<?php

declare(strict_types=1);

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Core\Contracts\AuditTrailInterface;
use Modules\Core\Contracts\DocumentNumberingInterface;
use Modules\Core\Contracts\OutboxBusInterface;
use Modules\Hotel\Application\Actions\ReserveRoomAction;
use Modules\Hotel\Application\DTOs\ReserveRoomDTO;
use Modules\Hotel\Application\Services\HotelBusinessDateService;
use Modules\Hotel\Application\Services\HotelInventoryService;
use Modules\Hotel\Domain\Exceptions\HotelRuleViolationException;
use Modules\Hotel\Domain\Exceptions\IdempotencyConflictException;
use Modules\Hotel\Domain\Models\HotelRateCalendar;
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

it('throws 409 conflict when idempotency key reused with different payload', function () {
    $dateIn = Carbon::now()->addDays(5)->toDateString();
    $dateOut = Carbon::now()->addDays(7)->toDateString();

    $action = app(ReserveRoomAction::class);
    $dto1 = new ReserveRoomDTO(
        propertyId: $this->property->id,
        roomTypeId: $this->roomType->id,
        ratePlanId: $this->barPlan->id,
        guestUserId: (string) $this->guest->id,
        checkInDate: $dateIn,
        checkOutDate: $dateOut,
        idempotencyKey: 'res_key_conflict_test'
    );
    $action->handle($dto1);

    // DTO 2 with different checkOutDate but same key
    $dto2 = new ReserveRoomDTO(
        propertyId: $this->property->id,
        roomTypeId: $this->roomType->id,
        ratePlanId: $this->barPlan->id,
        guestUserId: (string) $this->guest->id,
        checkInDate: $dateIn,
        checkOutDate: Carbon::now()->addDays(8)->toDateString(),
        idempotencyKey: 'res_key_conflict_test'
    );

    $action->handle($dto2);
})->throws(IdempotencyConflictException::class);

it('rejects arrival date before current business date', function () {
    $action = app(ReserveRoomAction::class);
    $currentDate = app(HotelBusinessDateService::class)->getCurrentBusinessDate($this->property->id);

    $dto = new ReserveRoomDTO(
        propertyId: $this->property->id,
        roomTypeId: $this->roomType->id,
        ratePlanId: $this->barPlan->id,
        guestUserId: (string) $this->guest->id,
        checkInDate: Carbon::parse($currentDate)->subDays(1)->toDateString(),
        checkOutDate: Carbon::parse($currentDate)->addDays(1)->toDateString(),
        idempotencyKey: 'res_key_past_arrival'
    );

    $action->handle($dto);
})->throws(HotelRuleViolationException::class, 'tidak boleh sebelum business date');

it('enforces min-stay and max-stay rate plan restrictions', function () {
    $this->barPlan->update(['min_stay' => 3, 'max_stay' => 5]);
    $action = app(ReserveRoomAction::class);

    // Case 1: min_stay violation (2 nights < 3)
    $dtoMin = new ReserveRoomDTO(
        propertyId: $this->property->id,
        roomTypeId: $this->roomType->id,
        ratePlanId: $this->barPlan->id,
        guestUserId: (string) $this->guest->id,
        checkInDate: Carbon::now()->addDays(10)->toDateString(),
        checkOutDate: Carbon::now()->addDays(12)->toDateString(),
        idempotencyKey: 'res_key_min_stay'
    );

    expect(fn () => $action->handle($dtoMin))
        ->toThrow(HotelRuleViolationException::class, 'minimum');

    // Case 2: max_stay violation (6 nights > 5)
    $dtoMax = new ReserveRoomDTO(
        propertyId: $this->property->id,
        roomTypeId: $this->roomType->id,
        ratePlanId: $this->barPlan->id,
        guestUserId: (string) $this->guest->id,
        checkInDate: Carbon::now()->addDays(10)->toDateString(),
        checkOutDate: Carbon::now()->addDays(16)->toDateString(),
        idempotencyKey: 'res_key_max_stay'
    );

    expect(fn () => $action->handle($dtoMax))
        ->toThrow(HotelRuleViolationException::class, 'maksimum');
});

it('enforces closed-to-arrival and stop-sell calendar restrictions', function () {
    $action = app(ReserveRoomAction::class);
    $inDate = Carbon::now()->addDays(20)->toDateString();
    $outDate = Carbon::now()->addDays(22)->toDateString();

    // CTA on arrival date
    HotelRateCalendar::updateOrCreate(
        [
            'property_id' => $this->property->id,
            'room_type_id' => $this->roomType->id,
            'rate_plan_id' => $this->barPlan->id,
            'calendar_date' => $inDate,
        ],
        [
            'id' => (string) Str::uuid(),
            'rate_idr' => 1000000,
            'closed_to_arrival' => true,
            'stop_sell' => false,
        ]
    );

    $dtoCta = new ReserveRoomDTO(
        propertyId: $this->property->id,
        roomTypeId: $this->roomType->id,
        ratePlanId: $this->barPlan->id,
        guestUserId: (string) $this->guest->id,
        checkInDate: $inDate,
        checkOutDate: $outDate,
        idempotencyKey: 'res_key_cta'
    );

    expect(fn () => $action->handle($dtoCta))
        ->toThrow(HotelRuleViolationException::class, 'Closed to Arrival');

    // Reset CTA and set Stop-sell
    HotelRateCalendar::where('calendar_date', $inDate)->update(['closed_to_arrival' => false, 'stop_sell' => true]);

    $dtoStop = new ReserveRoomDTO(
        propertyId: $this->property->id,
        roomTypeId: $this->roomType->id,
        ratePlanId: $this->barPlan->id,
        guestUserId: (string) $this->guest->id,
        checkInDate: $inDate,
        checkOutDate: $outDate,
        idempotencyKey: 'res_key_stop_sell'
    );

    expect(fn () => $action->handle($dtoStop))
        ->toThrow(HotelRuleViolationException::class, 'Stop Sell');
});

it('tests fallback base rate, room assignment fallback, overbooking flag, and audit outbox', function () {
    $dateIn = Carbon::now()->addDays(30)->toDateString();
    $dateOut = Carbon::now()->addDays(32)->toDateString();

    // Set physical room base rate
    $this->room->update([
        'status' => 'occupied',
        'base_rate_idr' => 1_250_000,
    ]);

    // Enable overbooking on room type
    $this->roomType->update([
        'overbooking_max_percent' => 50,
    ]);

    $docNumberMock = Mockery::mock(DocumentNumberingInterface::class);
    $docNumberMock->shouldReceive('nextNumber')->with('HTL', 'RSV')->andReturn('HTL/RSV/FALLBACK-01');

    $auditMock = Mockery::mock(AuditTrailInterface::class);
    $auditMock->shouldReceive('record')->once()->withArgs(function ($action, $auditable = null, $oldValues = null, $newValues = null) {
        expect($action)->toBe('hotel.reservation.created');

        return true;
    });
    app()->instance(AuditTrailInterface::class, $auditMock);

    $outboxMock = Mockery::mock(OutboxBusInterface::class);
    $outboxMock->shouldReceive('record')->once()->withArgs(function ($eventType, $payload, $idempotencyKey) {
        expect($eventType)->toBe('HotelReservationCreated')
            ->and($idempotencyKey)->toBe('res_key_fallback_unit');

        return true;
    });
    app()->instance(OutboxBusInterface::class, $outboxMock);

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
        idempotencyKey: 'res_key_fallback_unit'
    );

    $res = $action->handle($dto);

    expect($res['reservation']->status)->toBe('confirmed')
        ->and($res['reservation']->daily_rate_idr)->toBe(1_250_000)
        ->and($res['total_room_charge_idr'])->toBe(2_500_000)
        ->and($res['reservation']->room_id)->toBe($this->room->id);
});
