<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Banking\Contracts\Ledger;
use Modules\Hotel\Application\Actions\ProcessGuestDepositAction;
use Modules\Hotel\Domain\Models\HotelReservation;
use Modules\Hotel\tests\Unit\HotelUnitTestHelper;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class, HotelUnitTestHelper::class);

covers(ProcessGuestDepositAction::class);

beforeEach(function () {
    $this->setupHotelFixture();
});

it('rejects empty idempotency key on process deposit', function () {
    $action = app(ProcessGuestDepositAction::class);
    $action->handle(
        propertyId: $this->property->id,
        reservationId: (string) Str::uuid(),
        guestUserId: (string) $this->guest->id,
        amountIdr: 500000,
        paymentMethod: 'CASH',
        idempotencyKey: '   '
    );
})->throws(InvalidArgumentException::class, 'Idempotency-Key wajib disertakan.');

it('rejects non-positive deposit amount', function () {
    $action = app(ProcessGuestDepositAction::class);
    $action->handle(
        propertyId: $this->property->id,
        reservationId: (string) Str::uuid(),
        guestUserId: (string) $this->guest->id,
        amountIdr: 0,
        paymentMethod: 'CASH',
        idempotencyKey: 'key_dep_0'
    );
})->throws(InvalidArgumentException::class, 'Nominal deposit harus lebih besar dari 0.');

it('processes deposit with ledger posting and transitions tentative reservation to confirmed', function () {
    $reservation = HotelReservation::create([
        'id' => (string) Str::uuid(),
        'reservation_number' => 'HTL/RSV/DEP-TENT-01',
        'property_id' => $this->property->id,
        'room_id' => $this->room->id,
        'guest_user_id' => $this->guest->id,
        'check_in_date' => now()->toDateString(),
        'check_out_date' => now()->addDays(2)->toDateString(),
        'daily_rate_idr' => 1_000_000,
        'total_room_charge_idr' => 2_000_000,
        'status' => 'tentatif',
    ]);

    $ledgerMock = Mockery::mock(Ledger::class);
    $ledgerMock->shouldReceive('post')->once();

    $action = new ProcessGuestDepositAction($ledgerMock);
    $res = $action->handle(
        propertyId: $this->property->id,
        reservationId: $reservation->id,
        guestUserId: (string) $this->guest->id,
        amountIdr: 1_000_000,
        paymentMethod: 'transfer',
        idempotencyKey: 'key_dep_ledger_01'
    );

    expect($res['status'])->toBe('DEPOSIT_HELD')
        ->and($res['deposit']->amount_idr)->toBe(1_000_000)
        ->and($res['deposit']->payment_method)->toBe('TRANSFER')
        ->and($res['deposit']->status)->toBe('HELD');

    $reservation->refresh();
    expect($reservation->status)->toBe('confirmed');

    // Idempotency check: duplicate call returns same deposit and ALREADY_PROCESSED status
    $resDup = $action->handle(
        propertyId: $this->property->id,
        reservationId: $reservation->id,
        guestUserId: (string) $this->guest->id,
        amountIdr: 1_000_000,
        paymentMethod: 'transfer',
        idempotencyKey: 'key_dep_ledger_01'
    );
    expect($resDup['status'])->toBe('ALREADY_PROCESSED')
        ->and($resDup['deposit']->id)->toBe($res['deposit']->id);

    // Execute wrapper returns the deposit model directly
    $dep = $action->execute(
        propertyId: $this->property->id,
        reservationId: $reservation->id,
        guestUserId: (string) $this->guest->id,
        amountIdr: 1_000_000,
        paymentMethod: 'transfer',
        idempotencyKey: 'key_dep_ledger_01'
    );
    expect($dep->id)->toBe($res['deposit']->id);
});
