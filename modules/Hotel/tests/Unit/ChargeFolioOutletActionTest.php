<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Banking\Contracts\Ledger;
use Modules\Hotel\Application\Actions\ChargeFolioOutletAction;
use Modules\Hotel\Application\Services\HotelBusinessDateService;
use Modules\Hotel\Domain\Models\HotelFolio;
use Modules\Hotel\Domain\Models\HotelFolioLine;
use Modules\Hotel\Domain\Models\HotelReservation;
use Modules\Hotel\tests\Unit\HotelUnitTestHelper;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class, HotelUnitTestHelper::class);

covers(ChargeFolioOutletAction::class);

beforeEach(function () {
    $this->setupHotelFixture();
});

it('rejects charging outlet without idempotency key', function () {
    $action = app(ChargeFolioOutletAction::class);
    $action->execute(
        reservationOrFolioId: (string) Str::uuid(),
        baseAmountIdr: 100000,
        description: 'Mocktail',
        idempotencyKey: '   '
    );
})->throws(InvalidArgumentException::class, 'Idempotency-Key diperlukan untuk pembebanan folio.');

it('rejects charging outlet when folio not found or guest not checked in', function () {
    $reservation = HotelReservation::create([
        'id' => (string) Str::uuid(),
        'reservation_number' => 'HTL/RSV/CONF-01',
        'property_id' => $this->property->id,
        'room_id' => $this->room->id,
        'guest_user_id' => $this->guest->id,
        'check_in_date' => now()->toDateString(),
        'check_out_date' => now()->addDays(2)->toDateString(),
        'daily_rate_idr' => 1000000,
        'total_room_charge_idr' => 2000000,
        'status' => 'confirmed',
    ]);

    $action = app(ChargeFolioOutletAction::class);
    $action->execute(
        reservationOrFolioId: $reservation->id,
        baseAmountIdr: 150000,
        description: 'Dinner',
        idempotencyKey: 'charge_fail_key'
    );
})->throws(DomainException::class);

it('charges outlet to in-house guest folio with tax and service charge and ledger posting', function () {
    $reservation = HotelReservation::create([
        'id' => (string) Str::uuid(),
        'reservation_number' => 'HTL/RSV/INHOUSE-01',
        'property_id' => $this->property->id,
        'room_id' => $this->room->id,
        'guest_user_id' => $this->guest->id,
        'check_in_date' => now()->toDateString(),
        'check_out_date' => now()->addDays(2)->toDateString(),
        'daily_rate_idr' => 1000000,
        'total_room_charge_idr' => 2000000,
        'status' => 'checked_in',
    ]);

    $folio = HotelFolio::create([
        'id' => (string) Str::uuid(),
        'property_id' => $this->property->id,
        'reservation_id' => $reservation->id,
        'guest_user_id' => $this->guest->id,
        'status' => 'OPEN',
        'total_room_charges' => 2000000,
        'total_addon_charges' => 0,
        'total_payments' => 0,
        'outstanding_balance' => 2000000,
    ]);

    $ledgerMock = Mockery::mock(Ledger::class);
    $ledgerMock->shouldReceive('post')->once();

    $bDateService = app(HotelBusinessDateService::class);
    $action = new ChargeFolioOutletAction($bDateService, $ledgerMock);

    // base: 100.000, service: 10% = 10.000, tax: 10% of 110.000 = 11.000, total: 121.000
    $line = $action->execute(
        reservationOrFolioId: $folio->id,
        baseAmountIdr: 100000,
        description: 'Resto Makan Siang',
        idempotencyKey: 'outlet_key_01'
    );

    expect($line->base_amount_idr)->toBe(100000)
        ->and($line->service_charge_idr)->toBe(10000)
        ->and($line->tax_pbjt_idr)->toBe(11000)
        ->and($line->amount_idr)->toBe(121000);

    $folio->refresh();
    expect($folio->total_addon_charges)->toBe(121000)
        ->and($folio->outstanding_balance)->toBe(2121000);

    // Test idempotency: pemanggilan kedua dengan key sama harus mengembalikan line yang sama
    $lineAgain = $action->execute(
        reservationOrFolioId: $folio->id,
        baseAmountIdr: 100000,
        description: 'Resto Makan Siang',
        idempotencyKey: 'outlet_key_01'
    );
    expect($lineAgain->id)->toBe($line->id);
});

it('rejects reversal without idempotency key', function () {
    $action = app(ChargeFolioOutletAction::class);
    $action->reverseLine(
        folioLineId: (string) Str::uuid(),
        reason: 'Salah kamar',
        idempotencyKey: '   '
    );
})->throws(InvalidArgumentException::class, 'Idempotency-Key diperlukan untuk reversal folio.');

it('reverses folio outlet line append-only with negative amounts and ledger posting', function () {
    $reservation = HotelReservation::create([
        'id' => (string) Str::uuid(),
        'reservation_number' => 'HTL/RSV/REV-01',
        'property_id' => $this->property->id,
        'room_id' => $this->room->id,
        'guest_user_id' => $this->guest->id,
        'check_in_date' => now()->toDateString(),
        'check_out_date' => now()->addDays(2)->toDateString(),
        'daily_rate_idr' => 1000000,
        'total_room_charge_idr' => 2000000,
        'status' => 'checked_in',
    ]);

    $folio = HotelFolio::create([
        'id' => (string) Str::uuid(),
        'property_id' => $this->property->id,
        'reservation_id' => $reservation->id,
        'guest_user_id' => $this->guest->id,
        'status' => 'OPEN',
        'total_room_charges' => 2000000,
        'total_addon_charges' => 121000,
        'total_payments' => 0,
        'outstanding_balance' => 2121000,
    ]);

    $originalLine = HotelFolioLine::create([
        'id' => (string) Str::uuid(),
        'folio_id' => $folio->id,
        'business_date' => now()->toDateString(),
        'line_type' => 'FNB_OUTLET',
        'description' => 'Salah tagih',
        'amount_idr' => 121000,
        'base_amount_idr' => 100000,
        'service_charge_idr' => 10000,
        'tax_pbjt_idr' => 11000,
        'reversal_of_id' => null,
        'idempotency_key' => 'orig_charge_key',
    ]);

    $ledgerMock = Mockery::mock(Ledger::class);
    $ledgerMock->shouldReceive('post')->once();

    $bDateService = app(HotelBusinessDateService::class);
    $action = new ChargeFolioOutletAction($bDateService, $ledgerMock);

    $revLine = $action->reverseLine(
        folioLineId: $originalLine->id,
        reason: 'Salah kamar tamu',
        idempotencyKey: 'rev_key_01'
    );

    expect($revLine->line_type)->toBe('REVERSAL')
        ->and($revLine->amount_idr)->toBe(-121000)
        ->and($revLine->base_amount_idr)->toBe(-100000)
        ->and($revLine->service_charge_idr)->toBe(-10000)
        ->and($revLine->tax_pbjt_idr)->toBe(-11000)
        ->and($revLine->reversal_of_id)->toBe($originalLine->id);

    $folio->refresh();
    expect($folio->total_addon_charges)->toBe(0)
        ->and($folio->outstanding_balance)->toBe(2000000);

    // Test idempotency reversal
    $revAgain = $action->reverseLine(
        folioLineId: $originalLine->id,
        reason: 'Salah kamar tamu',
        idempotencyKey: 'rev_key_01'
    );
    expect($revAgain->id)->toBe($revLine->id);
});
