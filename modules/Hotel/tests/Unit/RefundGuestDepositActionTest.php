<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Banking\Contracts\Ledger;
use Modules\Hotel\Application\Actions\RefundGuestDepositAction;
use Modules\Hotel\Domain\Exceptions\HotelRuleViolationException;
use Modules\Hotel\Domain\Models\HotelDeposit;
use Modules\Hotel\Domain\Models\HotelReservation;
use Modules\Hotel\tests\Unit\HotelUnitTestHelper;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class, HotelUnitTestHelper::class);

covers(RefundGuestDepositAction::class);

beforeEach(function () {
    $this->setupHotelFixture();
});

it('rejects empty idempotency key on refund deposit', function () {
    $action = app(RefundGuestDepositAction::class);
    $action->handle(
        depositId: (string) Str::uuid(),
        refundAmountIdr: 100000,
        idempotencyKey: '   '
    );
})->throws(InvalidArgumentException::class, 'Idempotency-Key wajib disertakan.');

it('rejects non-positive refund amount', function () {
    $action = app(RefundGuestDepositAction::class);
    $action->handle(
        depositId: (string) Str::uuid(),
        refundAmountIdr: 0,
        idempotencyKey: 'key_ref_0'
    );
})->throws(InvalidArgumentException::class, 'Nominal refund harus lebih besar dari 0.');

it('rejects refund amount greater than remaining deposit', function () {
    $reservation = HotelReservation::create([
        'id' => (string) Str::uuid(),
        'reservation_number' => 'HTL/RSV/REF-001',
        'property_id' => $this->property->id,
        'room_id' => $this->room->id,
        'guest_user_id' => $this->guest->id,
        'check_in_date' => now()->toDateString(),
        'check_out_date' => now()->addDays(2)->toDateString(),
        'daily_rate_idr' => 1000000,
        'total_room_charge_idr' => 2000000,
        'status' => 'confirmed',
    ]);

    $deposit = HotelDeposit::create([
        'id' => (string) Str::uuid(),
        'property_id' => $this->property->id,
        'reservation_id' => $reservation->id,
        'guest_user_id' => $this->guest->id,
        'amount_idr' => 300000,
        'applied_amount_idr' => 0,
        'refunded_amount_idr' => 0,
        'payment_method' => 'CASH',
        'status' => 'HELD',
        'idempotency_key' => 'dep_ref_'.Str::random(8),
    ]);

    $action = app(RefundGuestDepositAction::class);
    $action->handle(
        depositId: $deposit->id,
        refundAmountIdr: 500000,
        idempotencyKey: 'key_ref_exceed'
    );
})->throws(InvalidArgumentException::class, 'melebihi sisa deposit yang tersedia');

it('refunds deposit partially and fully with ledger postings', function () {
    $reservation = HotelReservation::create([
        'id' => (string) Str::uuid(),
        'reservation_number' => 'HTL/RSV/REF-002',
        'property_id' => $this->property->id,
        'room_id' => $this->room->id,
        'guest_user_id' => $this->guest->id,
        'check_in_date' => now()->toDateString(),
        'check_out_date' => now()->addDays(2)->toDateString(),
        'daily_rate_idr' => 1000000,
        'total_room_charge_idr' => 2000000,
        'status' => 'confirmed',
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
        'idempotency_key' => 'dep_ref_'.Str::random(8),
    ]);

    $ledgerMock = Mockery::mock(Ledger::class);
    $ledgerMock->shouldReceive('post')->twice();

    $action = new RefundGuestDepositAction($ledgerMock);
    $res1 = $action->execute(
        depositId: $deposit->id,
        refundAmountIdr: 200000,
        idempotencyKey: 'ref_key_part',
        reason: 'Refund sebagian'
    );

    expect($res1['refunded_amount_idr'])->toBe(200000)
        ->and($res1['remaining_amount_idr'])->toBe(300000)
        ->and($res1['deposit']->status)->toBe('PARTIALLY_REFUNDED');

    // Refund remaining fully
    $res2 = $action->handle(
        depositId: $deposit->id,
        refundAmountIdr: 300000,
        idempotencyKey: 'ref_key_full'
    );

    expect($res2['refunded_amount_idr'])->toBe(300000)
        ->and($res2['remaining_amount_idr'])->toBe(0)
        ->and($res2['deposit']->status)->toBe('REFUNDED');

    // Idempotent retry with same key returns identical result
    $resDup = $action->handle(
        depositId: $deposit->id,
        refundAmountIdr: 300000,
        idempotencyKey: 'ref_key_full'
    );
    expect($resDup['refunded_amount_idr'])->toBe(500000)
        ->and($resDup['remaining_amount_idr'])->toBe(0);

    // Second refund attempt with different key on fully refunded deposit is rejected
    expect(fn () => $action->handle(
        depositId: $deposit->id,
        refundAmountIdr: 100000,
        idempotencyKey: 'ref_key_second_attempt'
    ))->toThrow(HotelRuleViolationException::class, 'Refund kedua untuk sumber deposit yang sama ditolak');
});
