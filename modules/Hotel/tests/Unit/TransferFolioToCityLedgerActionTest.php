<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Banking\Contracts\Ledger;
use Modules\Hotel\Application\Actions\TransferFolioToCityLedgerAction;
use Modules\Hotel\Application\Services\HotelBusinessDateService;
use Modules\Hotel\Domain\Models\HotelCityLedgerAccount;
use Modules\Hotel\Domain\Models\HotelCityLedgerTransfer;
use Modules\Hotel\Domain\Models\HotelFolio;
use Modules\Hotel\Domain\Models\HotelFolioLine;
use Modules\Hotel\Domain\Models\HotelReservation;
use Modules\Hotel\tests\Unit\HotelUnitTestHelper;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class, HotelUnitTestHelper::class);

covers(TransferFolioToCityLedgerAction::class);

beforeEach(function () {
    $this->setupHotelFixture();
});

it('rejects empty idempotency key on city ledger transfer', function () {
    $action = app(TransferFolioToCityLedgerAction::class);
    $action->execute(
        folioId: (string) Str::uuid(),
        cityLedgerAccountIdOrCode: 'CORP-01',
        amountIdr: 500_000,
        notes: 'Test note',
        idempotencyKey: '   '
    );
})->throws(InvalidArgumentException::class, 'Idempotency-Key diperlukan untuk pengalihan ke city ledger.');

it('rejects non-positive amount on city ledger transfer', function () {
    $action = app(TransferFolioToCityLedgerAction::class);
    $action->execute(
        folioId: (string) Str::uuid(),
        cityLedgerAccountIdOrCode: 'CORP-01',
        amountIdr: 0,
        notes: 'Test note',
        idempotencyKey: 'key_non_pos'
    );
})->throws(InvalidArgumentException::class, 'Nominal pengalihan city ledger harus lebih besar dari 0.');

it('rejects transfer when account not found or inactive', function () {
    $account = HotelCityLedgerAccount::create([
        'id' => (string) Str::uuid(),
        'property_id' => $this->property->id,
        'company_name' => 'PT Non Aktif',
        'company_code' => 'CORP-INACTIVE',
        'credit_limit_idr' => 10_000_000,
        'outstanding_balance_idr' => 0,
        'is_active' => false,
    ]);

    $reservation = HotelReservation::create([
        'id' => (string) Str::uuid(),
        'reservation_number' => 'HTL/RSV/CL-INACT',
        'property_id' => $this->property->id,
        'room_id' => $this->room->id,
        'guest_user_id' => $this->guest->id,
        'check_in_date' => now()->toDateString(),
        'check_out_date' => now()->addDays(2)->toDateString(),
        'daily_rate_idr' => 1_000_000,
        'total_room_charge_idr' => 2_000_000,
        'status' => 'checked_in',
    ]);
    $folio = HotelFolio::create([
        'id' => (string) Str::uuid(),
        'folio_number' => 'FOL-CL-INACT',
        'reservation_id' => $reservation->id,
        'guest_user_id' => $this->guest->id,
        'total_addon_charges' => 500_000,
        'outstanding_balance' => 500_000,
        'status' => 'open',
    ]);

    $action = app(TransferFolioToCityLedgerAction::class);

    // Unknown account
    expect(fn () => $action->execute(
        folioId: $folio->id,
        cityLedgerAccountIdOrCode: 'NON-EXISTENT',
        amountIdr: 100_000,
        notes: null,
        idempotencyKey: 'key_unknown_corp'
    ))->toThrow(DomainException::class, 'Akun City Ledger korporat tidak ditemukan');

    // Inactive account
    expect(fn () => $action->execute(
        folioId: $folio->id,
        cityLedgerAccountIdOrCode: $account->id,
        amountIdr: 100_000,
        notes: null,
        idempotencyKey: 'key_inactive_corp'
    ))->toThrow(DomainException::class, 'sedang dinonaktifkan');
});

it('rejects transfer when exceeding credit limit', function () {
    $account = HotelCityLedgerAccount::create([
        'id' => (string) Str::uuid(),
        'property_id' => $this->property->id,
        'company_name' => 'PT Mitra Sejahtera',
        'company_code' => 'CORP-SEJAHTERA',
        'credit_limit_idr' => 1_000_000,
        'outstanding_balance_idr' => 800_000,
        'is_active' => true,
    ]);

    $reservation = HotelReservation::create([
        'id' => (string) Str::uuid(),
        'reservation_number' => 'HTL/RSV/CL-001',
        'property_id' => $this->property->id,
        'room_id' => $this->room->id,
        'guest_user_id' => $this->guest->id,
        'check_in_date' => now()->toDateString(),
        'check_out_date' => now()->addDays(2)->toDateString(),
        'daily_rate_idr' => 1_000_000,
        'total_room_charge_idr' => 2_000_000,
        'status' => 'checked_in',
    ]);

    $folio = HotelFolio::create([
        'id' => (string) Str::uuid(),
        'folio_number' => 'FOL-CL-001',
        'reservation_id' => $reservation->id,
        'guest_user_id' => $this->guest->id,
        'total_addon_charges' => 500_000,
        'outstanding_balance' => 500_000,
        'status' => 'open',
    ]);

    $action = app(TransferFolioToCityLedgerAction::class);
    $action->execute(
        folioId: $folio->id,
        cityLedgerAccountIdOrCode: $account->company_code,
        amountIdr: 500_000, // 800k + 500k = 1.3jt > limit 1jt
        notes: 'Transfer corporate',
        idempotencyKey: 'key_cl_exceed_01'
    );
})->throws(DomainException::class, 'Batas kredit korporat tidak mencukupi');

it('transfers folio balance to city ledger with ledger posting and idempotent replay', function () {
    $account = HotelCityLedgerAccount::create([
        'id' => (string) Str::uuid(),
        'property_id' => $this->property->id,
        'company_name' => 'PT Sukses Makmur',
        'company_code' => 'CORP-MAKMUR',
        'credit_limit_idr' => 10_000_000,
        'outstanding_balance_idr' => 1_000_000,
        'is_active' => true,
    ]);

    $reservation = HotelReservation::create([
        'id' => (string) Str::uuid(),
        'reservation_number' => 'HTL/RSV/CL-OK',
        'property_id' => $this->property->id,
        'room_id' => $this->room->id,
        'guest_user_id' => $this->guest->id,
        'check_in_date' => now()->toDateString(),
        'check_out_date' => now()->addDays(2)->toDateString(),
        'daily_rate_idr' => 1_000_000,
        'total_room_charge_idr' => 2_000_000,
        'status' => 'checked_in',
    ]);

    $folio = HotelFolio::create([
        'id' => (string) Str::uuid(),
        'folio_number' => 'FOL-CL-OK',
        'reservation_id' => $reservation->id,
        'guest_user_id' => $this->guest->id,
        'total_addon_charges' => 1_000_000,
        'outstanding_balance' => 1_000_000,
        'status' => 'open',
    ]);

    $ledgerMock = Mockery::mock(Ledger::class);
    $ledgerMock->shouldReceive('post')->once();

    $action = new TransferFolioToCityLedgerAction(
        businessDateService: app(HotelBusinessDateService::class),
        ledger: $ledgerMock
    );

    $transfer = $action->execute(
        folioId: $folio->id,
        cityLedgerAccountIdOrCode: $account->company_code,
        amountIdr: 600_000,
        notes: 'Corporate billing sponsorship',
        idempotencyKey: 'key_cl_transfer_ok_01'
    );

    expect($transfer)->toBeInstanceOf(HotelCityLedgerTransfer::class)
        ->and($transfer->transferred_amount_idr)->toBe(600_000)
        ->and($transfer->city_ledger_account_id)->toBe($account->id);

    $folio->refresh();
    expect($folio->outstanding_balance)->toBe(400_000);

    $account->refresh();
    expect($account->outstanding_balance_idr)->toBe(1_600_000);

    $line = HotelFolioLine::where('folio_id', $folio->id)->where('line_type', 'CITY_LEDGER_TRANSFER')->first();
    expect($line)->not->toBeNull()
        ->and($line->amount_idr)->toBe(-600_000);

    // Idempotency: replay returns same transfer
    $replay = $action->execute(
        folioId: $folio->id,
        cityLedgerAccountIdOrCode: $account->company_code,
        amountIdr: 600_000,
        notes: 'Corporate billing sponsorship',
        idempotencyKey: 'key_cl_transfer_ok_01'
    );
    expect($replay->id)->toBe($transfer->id);
});
