<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Banking\Contracts\Ledger;
use Modules\Hotel\Application\Actions\ExecuteWalkCompensationAction;
use Modules\Hotel\Domain\Models\HotelReservation;
use Modules\Hotel\Domain\Models\HotelRoomNight;
use Modules\Hotel\tests\Unit\HotelUnitTestHelper;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class, HotelUnitTestHelper::class);

covers(ExecuteWalkCompensationAction::class);

beforeEach(function () {
    $this->setupHotelFixture();
});

it('rejects walk compensation without idempotency key', function () {
    $action = app(ExecuteWalkCompensationAction::class);
    $action->execute(
        reservationId: (string) Str::uuid(),
        alternatePropertyName: 'Mitra Hotel',
        idempotencyKey: '   '
    );
})->throws(HttpException::class);

it('rejects handle method without idempotency key or null', function () {
    $action = app(ExecuteWalkCompensationAction::class);
    try {
        $action->handle(
            reservationId: (string) Str::uuid(),
            alternatePropertyName: 'Mitra Hotel',
            idempotencyKey: null
        );
        $this->fail('Harus abort 422');
    } catch (HttpException $e) {
        expect($e->getStatusCode())->toBe(422);
    }

    try {
        $action->handle(
            reservationId: (string) Str::uuid(),
            alternatePropertyName: 'Mitra Hotel',
            idempotencyKey: '   '
        );
        $this->fail('Harus abort 422');
    } catch (HttpException $e) {
        expect($e->getStatusCode())->toBe(422);
    }
});

it('executes walk compensation successfully and relocates reservation', function () {
    $ledgerMock = Mockery::mock(Ledger::class);
    $ledgerMock->shouldReceive('post')->once();

    $reservation = HotelReservation::create([
        'id' => (string) Str::uuid(),
        'reservation_number' => 'HTL/RSV/TEST-001',
        'property_id' => $this->property->id,
        'room_id' => null,
        'guest_user_id' => $this->guest->id,
        'check_in_date' => now()->toDateString(),
        'check_out_date' => now()->addDays(2)->toDateString(),
        'daily_rate_idr' => 750000,
        'total_room_charge_idr' => 1500000,
        'status' => 'confirmed',
    ]);

    HotelRoomNight::create([
        'id' => (string) Str::uuid(),
        'property_id' => $this->property->id,
        'room_type_id' => $this->roomType->id,
        'reservation_id' => $reservation->id,
        'room_id' => $this->room->id,
        'night_date' => now()->toDateString(),
        'rate_idr' => 750000,
        'status' => 'reserved',
    ]);

    $action = new ExecuteWalkCompensationAction($ledgerMock);
    $result = $action->execute(
        reservationId: $reservation->id,
        alternatePropertyName: 'Grand Mitra Hotel',
        idempotencyKey: 'walk_key_test_01',
        compensationAmountIdr: null,
        transportAllowanceIdr: 300000,
        goodwillCreditIdr: 500000
    );

    expect($result['reservation']->status)->toBe('relocated_walk')
        ->and($result['alternate_property_name'])->toBe('Grand Mitra Hotel')
        ->and($result['total_compensation_idr'])->toBe(750000 + 300000 + 500000);

    expect(HotelRoomNight::where('reservation_id', $reservation->id)->count())->toBe(0);
});

it('executes walk compensation using default allowance and goodwill credit', function () {
    $reservation = HotelReservation::create([
        'id' => (string) Str::uuid(),
        'reservation_number' => 'HTL/RSV/TEST-003',
        'property_id' => $this->property->id,
        'room_id' => null,
        'guest_user_id' => $this->guest->id,
        'check_in_date' => now()->toDateString(),
        'check_out_date' => now()->addDays(1)->toDateString(),
        'daily_rate_idr' => 600000,
        'total_room_charge_idr' => 600000,
        'status' => 'confirmed',
    ]);

    $action = app(ExecuteWalkCompensationAction::class);
    $result = $action->execute(
        reservationId: $reservation->id,
        alternatePropertyName: 'Mitra Bintang Tiga',
        idempotencyKey: 'walk_key_default_01'
    );

    expect($result['total_compensation_idr'])->toBe(600000 + 350000 + 1000000)
        ->and($result['alternate_property_name'])->toBe('Mitra Bintang Tiga')
        ->and($result['reservation']->status)->toBe('relocated_walk');
});

it('executes walk compensation with explicit compensation amount overriding calculation', function () {
    $reservation = HotelReservation::create([
        'id' => (string) Str::uuid(),
        'reservation_number' => 'HTL/RSV/TEST-004',
        'property_id' => $this->property->id,
        'room_id' => null,
        'guest_user_id' => $this->guest->id,
        'check_in_date' => now()->toDateString(),
        'check_out_date' => now()->addDays(1)->toDateString(),
        'daily_rate_idr' => 500000,
        'total_room_charge_idr' => 500000,
        'status' => 'confirmed',
    ]);

    $action = app(ExecuteWalkCompensationAction::class);
    $result = $action->execute(
        reservationId: $reservation->id,
        alternatePropertyName: 'Mitra Khusus',
        idempotencyKey: 'walk_key_explicit_01',
        compensationAmountIdr: 2500000
    );

    expect($result['total_compensation_idr'])->toBe(2500000);
});

it('executes handle method delegation successfully with zero rate fallback', function () {
    $reservation = HotelReservation::create([
        'id' => (string) Str::uuid(),
        'reservation_number' => 'HTL/RSV/TEST-002',
        'property_id' => $this->property->id,
        'room_id' => null,
        'guest_user_id' => $this->guest->id,
        'check_in_date' => now()->toDateString(),
        'check_out_date' => now()->addDays(1)->toDateString(),
        'daily_rate_idr' => 0,
        'total_room_charge_idr' => 0,
        'status' => 'confirmed',
    ]);

    $action = app(ExecuteWalkCompensationAction::class);
    $result = $action->handle(
        reservationId: $reservation->id,
        alternatePropertyName: 'Mitra 2',
        transportAllowanceIdr: 200000,
        goodwillCreditIdr: 300000,
        idempotencyKey: 'walk_key_test_02'
    );

    expect($result['total_compensation_idr'])->toBe(1000000 + 200000 + 300000);
});
