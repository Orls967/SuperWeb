<?php

declare(strict_types=1);

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Banking\Contracts\Ledger;
use Modules\Hotel\Application\Actions\CancelReservationAction;
use Modules\Hotel\Domain\Models\HotelDeposit;
use Modules\Hotel\Domain\Models\HotelReservation;
use Modules\Hotel\Domain\Models\HotelRoomNight;
use Modules\Hotel\tests\Unit\HotelUnitTestHelper;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class, HotelUnitTestHelper::class);

covers(CancelReservationAction::class);

beforeEach(function () {
    $this->setupHotelFixture();
});

it('rejects empty idempotency key on cancel reservation', function () {
    $action = app(CancelReservationAction::class);
    $action->execute(
        reservationId: (string) Str::uuid(),
        idempotencyKey: '   '
    );
})->throws(InvalidArgumentException::class, 'Idempotency-Key diperlukan untuk pembatalan reservasi.');

it('rejects cancellation on already cancelled or checked in reservation', function () {
    $reservation = HotelReservation::create([
        'id' => (string) Str::uuid(),
        'reservation_number' => 'HTL/RSV/CANCEL-001',
        'property_id' => $this->property->id,
        'room_id' => $this->room->id,
        'guest_user_id' => $this->guest->id,
        'check_in_date' => now()->toDateString(),
        'check_out_date' => now()->addDays(1)->toDateString(),
        'daily_rate_idr' => 1000000,
        'total_room_charge_idr' => 1000000,
        'status' => 'checked_in',
    ]);

    $action = app(CancelReservationAction::class);
    $action->execute(
        reservationId: $reservation->id,
        idempotencyKey: 'key_cancel_fail'
    );
})->throws(DomainException::class);

it('cancels reservation free of charge before deadline and refunds deposit with ledger', function () {
    $ledgerMock = Mockery::mock(Ledger::class);
    $ledgerMock->shouldReceive('post')->once();

    $dateIn = Carbon::now()->addDays(5)->toDateString();
    $dateOut = Carbon::now()->addDays(7)->toDateString();

    $reservation = HotelReservation::create([
        'id' => (string) Str::uuid(),
        'reservation_number' => 'HTL/RSV/CANCEL-FREE',
        'property_id' => $this->property->id,
        'rate_plan_id' => $this->barPlan->id,
        'room_id' => $this->room->id,
        'guest_user_id' => $this->guest->id,
        'check_in_date' => $dateIn,
        'check_out_date' => $dateOut,
        'daily_rate_idr' => 1000000,
        'total_room_charge_idr' => 2000000,
        'status' => 'confirmed',
    ]);

    HotelRoomNight::create([
        'id' => (string) Str::uuid(),
        'property_id' => $this->property->id,
        'room_type_id' => $this->roomType->id,
        'reservation_id' => $reservation->id,
        'room_id' => $this->room->id,
        'night_date' => $dateIn,
        'rate_idr' => 1000000,
        'status' => 'reserved',
    ]);

    $deposit = HotelDeposit::create([
        'id' => (string) Str::uuid(),
        'property_id' => $this->property->id,
        'reservation_id' => $reservation->id,
        'guest_user_id' => $this->guest->id,
        'amount_idr' => 500000,
        'applied_amount_idr' => 0,
        'refunded_amount_idr' => 0,
        'payment_method' => 'CASH',
        'status' => 'HELD',
        'idempotency_key' => 'dep_fix_'.Str::random(8),
    ]);

    $action = new CancelReservationAction($ledgerMock);
    $res = $action->execute(
        reservationId: $reservation->id,
        idempotencyKey: 'cancel_free_key_01'
    );

    expect($res['is_free'])->toBeTrue()
        ->and($res['penalty_idr'])->toBe(0)
        ->and($res['deposit_refunded_idr'])->toBe(500000)
        ->and($res['reservation']->status)->toBe('dibatalkan');

    expect(HotelRoomNight::where('reservation_id', $reservation->id)->count())->toBe(0);

    $deposit->refresh();
    expect($deposit->status)->toBe('REFUNDED')
        ->and($deposit->refunded_amount_idr)->toBe(500000);
});

it('cancels reservation with penalty past deadline and applies deposit with ledger', function () {
    $ledgerMock = Mockery::mock(Ledger::class);
    $ledgerMock->shouldReceive('post')->twice(); // 1 for penalty, 1 for refund remaining

    $dateIn = Carbon::now()->toDateString();
    $dateOut = Carbon::now()->addDays(2)->toDateString();

    $reservation = HotelReservation::create([
        'id' => (string) Str::uuid(),
        'reservation_number' => 'HTL/RSV/CANCEL-PEN',
        'property_id' => $this->property->id,
        'rate_plan_id' => $this->barPlan->id,
        'room_id' => $this->room->id,
        'guest_user_id' => $this->guest->id,
        'check_in_date' => $dateIn,
        'check_out_date' => $dateOut,
        'daily_rate_idr' => 1500000,
        'total_room_charge_idr' => 3000000,
        'status' => 'confirmed',
    ]);

    $deposit = HotelDeposit::create([
        'id' => (string) Str::uuid(),
        'property_id' => $this->property->id,
        'reservation_id' => $reservation->id,
        'guest_user_id' => $this->guest->id,
        'amount_idr' => 2000000,
        'applied_amount_idr' => 0,
        'refunded_amount_idr' => 0,
        'payment_method' => 'CASH',
        'status' => 'HELD',
        'idempotency_key' => 'dep_fix_'.Str::random(8),
    ]);

    $action = new CancelReservationAction($ledgerMock);
    $res = $action->execute(
        reservationId: $reservation->id,
        idempotencyKey: 'cancel_pen_key_01',
        cancellationTime: Carbon::now()->setTime(18, 0, 0)->toDateTimeString()
    );

    expect($res['is_free'])->toBeFalse()
        ->and($res['penalty_idr'])->toBe(1500000)
        ->and($res['deposit_refunded_idr'])->toBe(500000)
        ->and($res['reservation']->status)->toBe('dibatalkan_denda');

    $deposit->refresh();
    expect($deposit->status)->toBe('PARTIALLY_REFUNDED')
        ->and($deposit->applied_amount_idr)->toBe(1500000)
        ->and($deposit->refunded_amount_idr)->toBe(500000);
});

it('cancels reservation with exact deposit applied and no refund remaining', function () {
    $ledgerMock = Mockery::mock(Ledger::class);
    $ledgerMock->shouldReceive('post')->once(); // penalty only

    $reservation = HotelReservation::create([
        'id' => (string) Str::uuid(),
        'reservation_number' => 'HTL/RSV/CANCEL-EXACT',
        'property_id' => $this->property->id,
        'rate_plan_id' => $this->barPlan->id,
        'room_id' => $this->room->id,
        'guest_user_id' => $this->guest->id,
        'check_in_date' => now()->toDateString(),
        'check_out_date' => now()->addDays(1)->toDateString(),
        'daily_rate_idr' => 1000000,
        'total_room_charge_idr' => 1000000,
        'status' => 'confirmed',
    ]);

    $deposit = HotelDeposit::create([
        'id' => (string) Str::uuid(),
        'property_id' => $this->property->id,
        'reservation_id' => $reservation->id,
        'guest_user_id' => $this->guest->id,
        'amount_idr' => 1000000,
        'applied_amount_idr' => 0,
        'refunded_amount_idr' => 0,
        'payment_method' => 'CASH',
        'status' => 'HELD',
        'idempotency_key' => 'dep_fix_'.Str::random(8),
    ]);

    $action = new CancelReservationAction($ledgerMock);
    $res = $action->execute(
        reservationId: $reservation->id,
        idempotencyKey: 'cancel_exact_key_01',
        cancellationTime: Carbon::now()->setTime(18, 0, 0)->toDateTimeString()
    );

    expect($res['deposit_refunded_idr'])->toBe(0);

    $deposit->refresh();
    expect($deposit->status)->toBe('APPLIED')
        ->and($deposit->applied_amount_idr)->toBe(1000000);
});

it('cancels reservation without rate plan and without deposit using fallback daily rate', function () {
    $reservation = HotelReservation::create([
        'id' => (string) Str::uuid(),
        'reservation_number' => 'HTL/RSV/CANCEL-NODEP',
        'property_id' => $this->property->id,
        'rate_plan_id' => null,
        'room_id' => $this->room->id,
        'guest_user_id' => $this->guest->id,
        'check_in_date' => now()->toDateString(),
        'check_out_date' => now()->addDays(1)->toDateString(),
        'daily_rate_idr' => 0, // tests fallback 1000000
        'total_room_charge_idr' => 0,
        'status' => 'confirmed',
    ]);

    $action = app(CancelReservationAction::class);
    $res = $action->execute(
        reservationId: $reservation->id,
        idempotencyKey: 'cancel_nodep_key_01',
        cancellationTime: Carbon::now()->setTime(18, 0, 0)->toDateTimeString()
    );

    expect($res['is_free'])->toBeFalse()
        ->and($res['penalty_idr'])->toBe(1000000)
        ->and($res['deposit_refunded_idr'])->toBe(0)
        ->and($res['reservation']->status)->toBe('dibatalkan_denda');
});
