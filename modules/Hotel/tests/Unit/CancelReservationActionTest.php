<?php

declare(strict_types=1);

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Banking\Contracts\Ledger;
use Modules\Hotel\Application\Actions\CancelReservationAction;
use Modules\Hotel\Domain\Exceptions\HotelRuleViolationException;
use Modules\Hotel\Domain\Models\HotelDeposit;
use Modules\Hotel\Domain\Models\HotelRatePlan;
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

it('cancels non-refundable reservation with full stay penalty and idempotent re-execution', function () {
    $nonRefPlan = HotelRatePlan::firstOrCreate(
        ['property_id' => $this->property->id, 'code' => 'NON_REF_CUSTOM'],
        [
            'id' => (string) Str::uuid(),
            'name' => 'Non-Refundable Promo',
            'plan_type' => 'non_refundable',
            'free_cancellation_hours' => 0,
            'penalty_nights' => 1,
            'requires_deposit' => false,
        ]
    );

    $reservation = HotelReservation::create([
        'id' => (string) Str::uuid(),
        'reservation_number' => 'HTL/RSV/CANCEL-NONREF',
        'property_id' => $this->property->id,
        'rate_plan_id' => $nonRefPlan->id,
        'room_id' => $this->room->id,
        'guest_user_id' => $this->guest->id,
        'check_in_date' => Carbon::now()->addDays(5)->toDateString(),
        'check_out_date' => Carbon::now()->addDays(8)->toDateString(),
        'daily_rate_idr' => 1000000,
        'total_room_charge_idr' => 3000000,
        'status' => 'confirmed',
    ]);

    $action = app(CancelReservationAction::class);
    $res = $action->execute(
        reservationId: $reservation->id,
        idempotencyKey: 'cancel_nonref_key_01'
    );

    // Full stay penalty for non-refundable
    expect($res['is_free'])->toBeFalse()
        ->and($res['penalty_idr'])->toBe(3000000)
        ->and($res['reservation']->status)->toBe('dibatalkan_denda');

    // Idempotent re-execution returns same result
    $resDup = $action->execute(
        reservationId: $reservation->id,
        idempotencyKey: 'cancel_nonref_key_01'
    );
    expect($resDup['reservation']->status)->toBe('dibatalkan_denda')
        ->and($resDup['deposit_refunded_idr'])->toBe(0);
});

// === Pembunuh mutan CancelReservationAction ===

it('rejects each terminal status individually with 422: checked_out, selesai, no_show', function (string $status) {
    $reservation = HotelReservation::create([
        'id' => (string) Str::uuid(),
        'reservation_number' => 'HTL/RSV/TERM-'.strtoupper($status),
        'property_id' => $this->property->id,
        'room_id' => $this->room->id,
        'guest_user_id' => $this->guest->id,
        'check_in_date' => now()->toDateString(),
        'check_out_date' => now()->addDays(1)->toDateString(),
        'daily_rate_idr' => 1000000,
        'total_room_charge_idr' => 1000000,
        'status' => $status,
    ]);

    try {
        app(CancelReservationAction::class)->execute(
            reservationId: $reservation->id,
            idempotencyKey: 'term_key_'.$status
        );
        $this->fail('Expected HotelRuleViolationException for status: '.$status);
    } catch (HotelRuleViolationException $e) {
        expect($e->getCode())->toBe(422)
            ->and($e->getMessage())->toContain($status);
    }
})->with(['checked_out', 'selesai', 'no_show']);

it('idempotent re-call on dibatalkan returns is_free=true and penalty=0', function () {
    $reservation = HotelReservation::create([
        'id' => (string) Str::uuid(),
        'reservation_number' => 'HTL/RSV/IDEM-BATAL',
        'property_id' => $this->property->id,
        'room_id' => $this->room->id,
        'guest_user_id' => $this->guest->id,
        'check_in_date' => now()->addDays(5)->toDateString(),
        'check_out_date' => now()->addDays(7)->toDateString(),
        'daily_rate_idr' => 1500000,
        'total_room_charge_idr' => 3000000,
        'status' => 'dibatalkan',
    ]);

    $action = app(CancelReservationAction::class);
    $res = $action->execute(
        reservationId: $reservation->id,
        idempotencyKey: 'idem_batal_key_01'
    );

    expect($res['is_free'])->toBeTrue()
        ->and($res['penalty_idr'])->toBe(0)
        ->and($res['deposit_refunded_idr'])->toBe(0)
        ->and($res['reservation']->status)->toBe('dibatalkan');
});

it('idempotent re-call on dibatalkan_denda returns is_free=false and penalty=daily_rate', function () {
    $reservation = HotelReservation::create([
        'id' => (string) Str::uuid(),
        'reservation_number' => 'HTL/RSV/IDEM-DENDA',
        'property_id' => $this->property->id,
        'room_id' => $this->room->id,
        'guest_user_id' => $this->guest->id,
        'check_in_date' => now()->addDays(2)->toDateString(),
        'check_out_date' => now()->addDays(4)->toDateString(),
        'daily_rate_idr' => 2000000,
        'total_room_charge_idr' => 4000000,
        'status' => 'dibatalkan_denda',
    ]);

    $action = app(CancelReservationAction::class);
    $res = $action->execute(
        reservationId: $reservation->id,
        idempotencyKey: 'idem_denda_key_01'
    );

    expect($res['is_free'])->toBeFalse()
        ->and($res['penalty_idr'])->toBe(2000000)
        ->and($res['deposit_refunded_idr'])->toBe(0)
        ->and($res['reservation']->status)->toBe('dibatalkan_denda');
});

it('idempotent re-call on dibatalkan_denda with zero daily_rate returns fallback penalty 1000000', function () {
    $reservation = HotelReservation::create([
        'id' => (string) Str::uuid(),
        'reservation_number' => 'HTL/RSV/IDEM-FALLBACK',
        'property_id' => $this->property->id,
        'room_id' => $this->room->id,
        'guest_user_id' => $this->guest->id,
        'check_in_date' => now()->addDays(1)->toDateString(),
        'check_out_date' => now()->addDays(2)->toDateString(),
        'daily_rate_idr' => 0,
        'total_room_charge_idr' => 0,
        'status' => 'dibatalkan_denda',
    ]);

    $action = app(CancelReservationAction::class);
    $res = $action->execute(
        reservationId: $reservation->id,
        idempotencyKey: 'idem_fallback_key_01'
    );

    expect($res['is_free'])->toBeFalse()
        ->and($res['penalty_idr'])->toBe(1000000);
});

it('non-refundable via free_cancellation_hours=0 triggers penalty even if plan_type is not NON_REFUNDABLE', function () {
    $earlyBirdPlan = HotelRatePlan::firstOrCreate(
        ['property_id' => $this->property->id, 'code' => 'EARLY_BIRD_NO_CANCEL'],
        [
            'id' => (string) Str::uuid(),
            'name' => 'Early Bird No Cancel',
            'plan_type' => 'PROMO',          // bukan NON_REFUNDABLE
            'free_cancellation_hours' => 0,  // trigger isNonRefundable via hours=0
            'penalty_nights' => 1,
            'requires_deposit' => false,
        ]
    );

    $reservation = HotelReservation::create([
        'id' => (string) Str::uuid(),
        'reservation_number' => 'HTL/RSV/PROMO-NOCANCEL',
        'property_id' => $this->property->id,
        'rate_plan_id' => $earlyBirdPlan->id,
        'room_id' => $this->room->id,
        'guest_user_id' => $this->guest->id,
        'check_in_date' => now()->addDays(10)->toDateString(),
        'check_out_date' => now()->addDays(12)->toDateString(),
        'daily_rate_idr' => 1200000,
        'total_room_charge_idr' => 2400000,
        'status' => 'confirmed',
    ]);

    $action = app(CancelReservationAction::class);
    $res = $action->execute(
        reservationId: $reservation->id,
        idempotencyKey: 'promo_nocancel_key_01'
    );

    expect($res['is_free'])->toBeFalse()
        ->and($res['penalty_idr'])->toBe(2400000)
        ->and($res['reservation']->status)->toBe('dibatalkan_denda');
});

it('non-refundable with zero total_room_charge falls back to daily_rate for penalty', function () {
    $nonRefPlan = HotelRatePlan::firstOrCreate(
        ['property_id' => $this->property->id, 'code' => 'NON_REF_FALLBACK'],
        [
            'id' => (string) Str::uuid(),
            'name' => 'Non Refundable Fallback',
            'plan_type' => 'NON_REFUNDABLE',
            'free_cancellation_hours' => 0,
            'penalty_nights' => 1,
            'requires_deposit' => false,
        ]
    );

    $reservation = HotelReservation::create([
        'id' => (string) Str::uuid(),
        'reservation_number' => 'HTL/RSV/NONREF-ZERO',
        'property_id' => $this->property->id,
        'rate_plan_id' => $nonRefPlan->id,
        'room_id' => $this->room->id,
        'guest_user_id' => $this->guest->id,
        'check_in_date' => now()->addDays(5)->toDateString(),
        'check_out_date' => now()->addDays(7)->toDateString(),
        'daily_rate_idr' => 1800000,
        'total_room_charge_idr' => 0,  // nol → fallback ke daily_rate
        'status' => 'confirmed',
    ]);

    $action = app(CancelReservationAction::class);
    $res = $action->execute(
        reservationId: $reservation->id,
        idempotencyKey: 'nonref_zero_key_01'
    );

    expect($res['is_free'])->toBeFalse()
        ->and($res['penalty_idr'])->toBe(1800000)
        ->and($res['reservation']->status)->toBe('dibatalkan_denda');
});

it('checked_in status throws 422 with exact status code assertion', function () {
    $reservation = HotelReservation::create([
        'id' => (string) Str::uuid(),
        'reservation_number' => 'HTL/RSV/CHECKIN-422',
        'property_id' => $this->property->id,
        'room_id' => $this->room->id,
        'guest_user_id' => $this->guest->id,
        'check_in_date' => now()->toDateString(),
        'check_out_date' => now()->addDays(1)->toDateString(),
        'daily_rate_idr' => 1000000,
        'total_room_charge_idr' => 1000000,
        'status' => 'checked_in',
    ]);

    try {
        app(CancelReservationAction::class)->execute(
            reservationId: $reservation->id,
            idempotencyKey: 'checkin_422_key'
        );
        $this->fail('Expected HotelRuleViolationException');
    } catch (HotelRuleViolationException $e) {
        expect($e->getCode())->toBe(422);
    }
});
