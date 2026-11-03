<?php

declare(strict_types=1);

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Core\Contracts\AuditTrailInterface;
use Modules\Core\Contracts\ClockInterface;
use Modules\Hotel\Application\Actions\CheckOutAndSettleFolioAction;
use Modules\Hotel\Domain\Models\HotelCityLedgerAccount;
use Modules\Hotel\Domain\Models\HotelDeposit;
use Modules\Hotel\Domain\Models\HotelFolio;
use Modules\Hotel\Domain\Models\HotelFolioLine;
use Modules\Hotel\Domain\Models\HotelHousekeepingLog;
use Modules\Hotel\Domain\Models\HotelReservation;
use Modules\Hotel\Domain\Models\HotelRoomNight;
use Modules\Hotel\Ledger\HotelTransactionTypes;
use Modules\Hotel\tests\Unit\HotelUnitTestHelper;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class, HotelUnitTestHelper::class);

covers(CheckOutAndSettleFolioAction::class);

beforeEach(function () {
    $this->setupHotelFixture();
});

// ─── 1. Idempotency-Key wajib ────────────────────────────────────────────────

it('rejects check-out handle without idempotency key', function () {
    $this->room->update(['status' => 'occupied']);
    $reservation = HotelReservation::create([
        'id' => (string) Str::uuid(),
        'reservation_number' => 'HTL/RSV/CO-NOKEY',
        'property_id' => $this->property->id,
        'room_id' => $this->room->id,
        'guest_user_id' => $this->guest->id,
        'check_in_date' => now()->subDays(2)->toDateString(),
        'check_out_date' => now()->toDateString(),
        'daily_rate_idr' => 1_000_000,
        'total_room_charge_idr' => 1_000_000,
        'status' => 'checked_in',
    ]);
    $folio = HotelFolio::create([
        'id' => (string) Str::uuid(),
        'folio_number' => 'FOL-NOKEY',
        'reservation_id' => $reservation->id,
        'guest_user_id' => $this->guest->id,
        'total_addon_charges' => 1_000_000,
        'outstanding_balance' => 1_000_000,
        'status' => 'open',
    ]);

    $action = app(CheckOutAndSettleFolioAction::class);
    $action->handle(
        folioId: $folio->id,
        paymentAmountIdr: 1_000_000,
        idempotencyKey: '   '
    );
})->throws(InvalidArgumentException::class, 'Idempotency-Key wajib disertakan.');

// ─── 2. Folio outstanding > 0 setelah payment → ditolak ─────────────────────

it('rejects check-out when remaining balance is not zero', function () {
    $this->room->update(['status' => 'occupied']);
    $reservation = HotelReservation::create([
        'id' => (string) Str::uuid(),
        'reservation_number' => 'HTL/RSV/CO-REJECT',
        'property_id' => $this->property->id,
        'room_id' => $this->room->id,
        'guest_user_id' => $this->guest->id,
        'check_in_date' => now()->subDays(2)->toDateString(),
        'check_out_date' => now()->toDateString(),
        'daily_rate_idr' => 1_000_000,
        'total_room_charge_idr' => 2_000_000,
        'status' => 'checked_in',
    ]);
    $folio = HotelFolio::create([
        'id' => (string) Str::uuid(),
        'folio_number' => 'FOL-REJECT',
        'reservation_id' => $reservation->id,
        'guest_user_id' => $this->guest->id,
        'total_addon_charges' => 2_000_000,
        'outstanding_balance' => 2_000_000,
        'status' => 'open',
    ]);

    $action = app(CheckOutAndSettleFolioAction::class);
    $action->handle(
        folioId: $folio->id,
        paymentMethod: 'CASH',
        idempotencyKey: 'key_co_reject_01',
        paymentAmountIdr: 1_000_000  // kurang 1 juta
    );
})->throws(InvalidArgumentException::class, 'Check-out ditolak: folio masih memiliki saldo terutang');

// ─── 3. Checkout sukses: pembayaran tunai penuh ───────────────────────────────

it('settles folio and checks out successfully with exact cash payment, audit, and ledger posting', function () {
    $this->room->update(['status' => 'occupied']);
    $reservation = HotelReservation::create([
        'id' => (string) Str::uuid(),
        'reservation_number' => 'HTL/RSV/CO-CASH',
        'property_id' => $this->property->id,
        'room_id' => $this->room->id,
        'guest_user_id' => $this->guest->id,
        'check_in_date' => now()->subDays(1)->toDateString(),
        'check_out_date' => now()->toDateString(),
        'daily_rate_idr' => 1_000_000,
        'total_room_charge_idr' => 1_000_000,
        'status' => 'checked_in',
    ]);
    $folio = HotelFolio::create([
        'id' => (string) Str::uuid(),
        'folio_number' => 'FOL-CASH',
        'reservation_id' => $reservation->id,
        'guest_user_id' => $this->guest->id,
        'total_addon_charges' => 1_000_000,
        'outstanding_balance' => 1_000_000,
        'status' => 'open',
    ]);

    $auditMock = Mockery::mock(AuditTrailInterface::class);
    $auditMock->shouldReceive('record')->once()->withArgs(function (
        string $action,
        ?object $auditable,
        ?array $oldValues,
        ?array $newValues,
        array $context,
        ?string $correlationId,
        ?string $impactType
    ) use ($folio, $reservation) {
        expect($action)->toBe('hotel.folio.checkout_settled')
            ->and($auditable->id)->toBe($folio->id)
            ->and($oldValues['status'])->toBe('open')
            ->and($newValues['reservation_number'])->toBe($reservation->reservation_number)
            ->and($newValues['room_number'])->toBe($this->room->room_number)
            ->and($newValues['total_paid'])->toBe(1_000_000)
            ->and($newValues['outstanding_balance'])->toBe(0)
            ->and($correlationId)->toBe("htl_out_{$folio->id}")
            ->and($impactType)->toBe('hotel_checkout');

        return true;
    });
    $this->app->instance(AuditTrailInterface::class, $auditMock);

    $postedDto = null;
    $ledgerMock = Mockery::mock(Ledger::class);
    $ledgerMock->shouldReceive('post')->once()->withArgs(function (PostingDTO $dto) use (&$postedDto, $folio) {
        $postedDto = $dto;
        expect($dto->type)->toBe(HotelTransactionTypes::HOTEL_FOLIO_SETTLEMENT->value)
            ->and($dto->description)->toContain('CASH')
            ->and($dto->idempotencyKey)->toBe('htl:settle_pay:key_co_cash_01')
            ->and($dto->referenceType)->toBe('htl_folios')
            ->and($dto->referenceId)->toBe($folio->id)
            ->and(count($dto->entries))->toBe(2)
            ->and($dto->entries[0]->accountCode)->toBe('clearing:external:IDR')
            ->and($dto->entries[0]->amount->toInt())->toBe(-1_000_000)
            ->and($dto->entries[1]->accountCode)->toBe('htl:guest_ledger:IDR')
            ->and($dto->entries[1]->amount->toInt())->toBe(1_000_000);

        return true;
    });

    $action = new CheckOutAndSettleFolioAction($ledgerMock);
    $res = $action->handle(
        folioId: $folio->id,
        paymentMethod: 'CASH',
        idempotencyKey: 'key_co_cash_01',
        paymentAmountIdr: 1_000_000
    );

    expect($res['status'])->toBe('CHECKOUT_COMPLETED')
        ->and($res['reservation']->status)->toBe('checked_out')
        ->and($res['deposit_applied_idr'])->toBe(0)
        ->and($res['folio']->id)->toBe($folio->id)
        ->and($res['room']->id)->toBe($this->room->id);

    $folio->refresh();
    expect($folio->status)->toBe('settled')
        ->and((int) $folio->outstanding_balance)->toBe(0)
        ->and($folio->total_paid)->toBe(1_000_000);

    $reservation->refresh();
    expect($reservation->status)->toBe('checked_out');
});

// ─── 4. Kamar berubah menjadi dirty setelah checkout ─────────────────────────

it('marks room as dirty and creates housekeeping log on checkout', function () {
    $this->room->update(['status' => 'occupied']);
    $reservation = HotelReservation::create([
        'id' => (string) Str::uuid(),
        'reservation_number' => 'HTL/RSV/CO-DIRTY',
        'property_id' => $this->property->id,
        'room_id' => $this->room->id,
        'guest_user_id' => $this->guest->id,
        'check_in_date' => now()->subDays(1)->toDateString(),
        'check_out_date' => now()->toDateString(),
        'daily_rate_idr' => 500_000,
        'total_room_charge_idr' => 500_000,
        'status' => 'checked_in',
    ]);
    $folio = HotelFolio::create([
        'id' => (string) Str::uuid(),
        'folio_number' => 'FOL-DIRTY',
        'reservation_id' => $reservation->id,
        'guest_user_id' => $this->guest->id,
        'total_addon_charges' => 500_000,
        'outstanding_balance' => 500_000,
        'status' => 'open',
    ]);

    $action = app(CheckOutAndSettleFolioAction::class);
    $res = $action->handle(
        folioId: $folio->id,
        paymentMethod: 'CASH',
        idempotencyKey: 'key_dirty_01',
        paymentAmountIdr: 500_000
    );

    expect($res['room']->status)->toBe('dirty');

    $this->room->refresh();
    expect($this->room->status)->toBe('dirty');

    $log = HotelHousekeepingLog::where('room_id', $this->room->id)
        ->where('to_status', 'dirty')
        ->first();
    expect($log)->not->toBeNull()
        ->and($log->from_status)->toBe('occupied')
        ->and($log->to_status)->toBe('dirty')
        ->and($log->changed_by_user_id)->toBe((int) $reservation->guest_user_id)
        ->and($log->notes)->toContain($reservation->reservation_number);
});

// ─── 5. Deposit HELD diterapkan sebelum pembayaran kasir ─────────────────────

it('applies held deposit to reduce outstanding before cashier payment with two ledger postings', function () {
    $this->room->update(['status' => 'occupied']);
    $reservation = HotelReservation::create([
        'id' => (string) Str::uuid(),
        'reservation_number' => 'HTL/RSV/CO-DEP',
        'property_id' => $this->property->id,
        'room_id' => $this->room->id,
        'guest_user_id' => $this->guest->id,
        'check_in_date' => now()->subDays(2)->toDateString(),
        'check_out_date' => now()->toDateString(),
        'daily_rate_idr' => 1_000_000,
        'total_room_charge_idr' => 2_000_000,
        'status' => 'checked_in',
    ]);
    $folio = HotelFolio::create([
        'id' => (string) Str::uuid(),
        'folio_number' => 'FOL-DEP',
        'reservation_id' => $reservation->id,
        'guest_user_id' => $this->guest->id,
        'total_addon_charges' => 2_000_000,
        'outstanding_balance' => 2_000_000,
        'status' => 'open',
    ]);

    $deposit = HotelDeposit::create([
        'id' => (string) Str::uuid(),
        'property_id' => $this->property->id,
        'reservation_id' => $reservation->id,
        'guest_user_id' => $this->guest->id,
        'amount_idr' => 500_000,
        'applied_amount_idr' => 0,
        'refunded_amount_idr' => 0,
        'payment_method' => 'CASH',
        'status' => 'HELD',
        'idempotency_key' => 'dep_co_'.Str::random(8),
    ]);

    $postCount = 0;
    $ledgerMock = Mockery::mock(Ledger::class);
    $ledgerMock->shouldReceive('post')->twice()->withArgs(function (PostingDTO $dto) use (&$postCount, $folio) {
        $postCount++;
        if ($postCount === 1) {
            expect($dto->type)->toBe(HotelTransactionTypes::HOTEL_FOLIO_SETTLEMENT->value)
                ->and($dto->idempotencyKey)->toBe('htl:dep_apply:key_dep_co_01')
                ->and($dto->referenceType)->toBe('htl_folios')
                ->and($dto->referenceId)->toBe($folio->id)
                ->and(count($dto->entries))->toBe(2)
                ->and($dto->entries[0]->accountCode)->toBe('htl:guest_deposit:IDR')
                ->and($dto->entries[0]->amount->toInt())->toBe(-500_000)
                ->and($dto->entries[1]->accountCode)->toBe('htl:guest_ledger:IDR')
                ->and($dto->entries[1]->amount->toInt())->toBe(500_000);
        } else {
            expect($dto->type)->toBe(HotelTransactionTypes::HOTEL_FOLIO_SETTLEMENT->value)
                ->and($dto->idempotencyKey)->toBe('htl:settle_pay:key_dep_co_01')
                ->and($dto->referenceType)->toBe('htl_folios')
                ->and($dto->referenceId)->toBe($folio->id)
                ->and(count($dto->entries))->toBe(2)
                ->and($dto->entries[0]->accountCode)->toBe('clearing:external:IDR')
                ->and($dto->entries[0]->amount->toInt())->toBe(-1_500_000)
                ->and($dto->entries[1]->accountCode)->toBe('htl:guest_ledger:IDR')
                ->and($dto->entries[1]->amount->toInt())->toBe(1_500_000);
        }

        return true;
    });

    $action = new CheckOutAndSettleFolioAction($ledgerMock);
    $res = $action->handle(
        folioId: $folio->id,
        paymentMethod: 'CASH',
        idempotencyKey: 'key_dep_co_01',
        paymentAmountIdr: 1_500_000  // sisa setelah deposit 500k
    );

    expect($res['deposit_applied_idr'])->toBe(500_000)
        ->and($res['status'])->toBe('CHECKOUT_COMPLETED');

    $deposit->refresh();
    expect($deposit->applied_amount_idr)->toBe(500_000)
        ->and($deposit->status)->toBe('APPLIED');

    $folio->refresh();
    expect((int) $folio->outstanding_balance)->toBe(0)
        ->and($folio->total_paid)->toBe(2_000_000);
});

// ─── 6. Execute wrapper: reservationId sebagai fallback folioId ───────────────

it('resolves folio from reservationId when folioId not provided via execute', function () {
    $this->room->update(['status' => 'occupied']);
    $reservation = HotelReservation::create([
        'id' => (string) Str::uuid(),
        'reservation_number' => 'HTL/RSV/CO-RES',
        'property_id' => $this->property->id,
        'room_id' => $this->room->id,
        'guest_user_id' => $this->guest->id,
        'check_in_date' => now()->subDays(1)->toDateString(),
        'check_out_date' => now()->toDateString(),
        'daily_rate_idr' => 800_000,
        'total_room_charge_idr' => 800_000,
        'status' => 'checked_in',
    ]);
    HotelFolio::create([
        'id' => (string) Str::uuid(),
        'folio_number' => 'FOL-RES',
        'reservation_id' => $reservation->id,
        'guest_user_id' => $this->guest->id,
        'total_addon_charges' => 800_000,
        'outstanding_balance' => 800_000,
        'status' => 'open',
    ]);

    $action = app(CheckOutAndSettleFolioAction::class);
    $res = $action->execute(
        reservationId: $reservation->id,
        paymentAmountIdr: 800_000,
        idempotencyKey: 'key_co_res_01'
    );

    expect($res['status'])->toBe('CHECKOUT_COMPLETED')
        ->and($res['reservation']->status)->toBe('checked_out');
});

// ─── 7. Early checkout → hapus sisa room nights setelah hari ini ─────────────

it('deletes future room nights on early checkout', function () {
    $today = now()->toDateString();
    $tomorrow = now()->addDay()->toDateString();
    $dayAfter = now()->addDays(2)->toDateString();

    $this->room->update(['status' => 'occupied']);
    $reservation = HotelReservation::create([
        'id' => (string) Str::uuid(),
        'reservation_number' => 'HTL/RSV/EARLY-01',
        'property_id' => $this->property->id,
        'room_id' => $this->room->id,
        'guest_user_id' => $this->guest->id,
        'check_in_date' => $today,
        'check_out_date' => $dayAfter,
        'daily_rate_idr' => 1_000_000,
        'total_room_charge_idr' => 3_000_000,
        'status' => 'checked_in',
    ]);

    foreach ([$today, $tomorrow, $dayAfter] as $date) {
        HotelRoomNight::create([
            'id' => (string) Str::uuid(),
            'property_id' => $this->property->id,
            'room_type_id' => $this->roomType->id,
            'reservation_id' => $reservation->id,
            'room_id' => $this->room->id,
            'night_date' => $date,
            'rate_idr' => 1_000_000,
            'status' => 'reserved',
        ]);
    }

    $folio = HotelFolio::create([
        'id' => (string) Str::uuid(),
        'folio_number' => 'FOL-EARLY',
        'reservation_id' => $reservation->id,
        'guest_user_id' => $this->guest->id,
        'total_addon_charges' => 0,
        'outstanding_balance' => 0,
        'status' => 'open',
    ]);

    $action = app(CheckOutAndSettleFolioAction::class);
    $action->handle(
        folioId: $folio->id,
        paymentAmountIdr: 0,
        idempotencyKey: 'key_early_co_01'
    );

    // Hanya malam ini tetap ada (night_date = today tidak dihapus karena kondisi > today)
    $remaining = HotelRoomNight::where('reservation_id', $reservation->id)->count();
    expect($remaining)->toBe(1);

    $futureDeleted = HotelRoomNight::where('reservation_id', $reservation->id)
        ->whereDate('night_date', '>', $today)
        ->count();
    expect($futureDeleted)->toBe(0);
});

// ─── 8. Execute wrapper: split payment (cash + card) ─────────────────────────

it('resolves split payment method correctly via execute wrapper', function () {
    $this->room->update(['status' => 'occupied']);
    $reservation = HotelReservation::create([
        'id' => (string) Str::uuid(),
        'reservation_number' => 'HTL/RSV/CO-SPLIT',
        'property_id' => $this->property->id,
        'room_id' => $this->room->id,
        'guest_user_id' => $this->guest->id,
        'check_in_date' => now()->subDays(1)->toDateString(),
        'check_out_date' => now()->toDateString(),
        'daily_rate_idr' => 1_000_000,
        'total_room_charge_idr' => 1_500_000,
        'status' => 'checked_in',
    ]);
    $folio = HotelFolio::create([
        'id' => (string) Str::uuid(),
        'folio_number' => 'FOL-SPLIT',
        'reservation_id' => $reservation->id,
        'guest_user_id' => $this->guest->id,
        'total_addon_charges' => 1_500_000,
        'outstanding_balance' => 1_500_000,
        'status' => 'open',
    ]);

    $ledgerMock = Mockery::mock(Ledger::class);
    $ledgerMock->shouldReceive('post')->once();

    $action = new CheckOutAndSettleFolioAction($ledgerMock);
    $res = $action->execute(
        folioId: $folio->id,
        idempotencyKey: 'key_split_01',
        cashReceivedIdr: 700_000,
        cardReceivedIdr: 800_000
    );

    expect($res['status'])->toBe('CHECKOUT_COMPLETED');
    $folio->refresh();
    expect((int) $folio->outstanding_balance)->toBe(0)
        ->and($folio->total_paid)->toBe(1_500_000);
});

// ─── 9. Deposit PARTIALLY_REFUNDED sisa tetap diterapkan ─────────────────────

it('applies remaining balance of PARTIALLY_REFUNDED deposit', function () {
    $this->room->update(['status' => 'occupied']);
    $reservation = HotelReservation::create([
        'id' => (string) Str::uuid(),
        'reservation_number' => 'HTL/RSV/CO-PARTIAL',
        'property_id' => $this->property->id,
        'room_id' => $this->room->id,
        'guest_user_id' => $this->guest->id,
        'check_in_date' => now()->subDays(1)->toDateString(),
        'check_out_date' => now()->toDateString(),
        'daily_rate_idr' => 300_000,
        'total_room_charge_idr' => 300_000,
        'status' => 'checked_in',
    ]);
    $folio = HotelFolio::create([
        'id' => (string) Str::uuid(),
        'folio_number' => 'FOL-PARTIAL',
        'reservation_id' => $reservation->id,
        'guest_user_id' => $this->guest->id,
        'total_addon_charges' => 300_000,
        'outstanding_balance' => 300_000,
        'status' => 'open',
    ]);

    $deposit = HotelDeposit::create([
        'id' => (string) Str::uuid(),
        'property_id' => $this->property->id,
        'reservation_id' => $reservation->id,
        'guest_user_id' => $this->guest->id,
        'amount_idr' => 1_000_000,
        'applied_amount_idr' => 0,
        'refunded_amount_idr' => 700_000, // sudah direfund 700k, sisa 300k
        'payment_method' => 'CASH',
        'status' => 'PARTIALLY_REFUNDED',
        'idempotency_key' => 'dep_partial_'.Str::random(6),
    ]);

    $ledgerMock = Mockery::mock(Ledger::class);
    $ledgerMock->shouldReceive('post')->once(); // 1 post untuk deposit apply saja

    $action = new CheckOutAndSettleFolioAction($ledgerMock);
    $res = $action->handle(
        folioId: $folio->id,
        idempotencyKey: 'key_partial_dep_01',
        paymentAmountIdr: 0  // deposit sisa 300k menutup outstanding 300k
    );

    expect($res['deposit_applied_idr'])->toBe(300_000)
        ->and($res['status'])->toBe('CHECKOUT_COMPLETED');

    $deposit->refresh();
    expect($deposit->status)->toBe('APPLIED')
        ->and($deposit->applied_amount_idr)->toBe(300_000);
});

// ─── 10. Settle folio entirely using deposit without cashier payment ─────────

it('settles folio entirely using deposit without cashier payment and posts ledger correctly', function () {
    $this->room->update(['status' => 'occupied']);
    $reservation = HotelReservation::create([
        'id' => (string) Str::uuid(),
        'reservation_number' => 'HTL/RSV/CO-FULL-DEP',
        'property_id' => $this->property->id,
        'room_id' => $this->room->id,
        'guest_user_id' => $this->guest->id,
        'check_in_date' => now()->subDays(1)->toDateString(),
        'check_out_date' => now()->toDateString(),
        'daily_rate_idr' => 500_000,
        'total_room_charge_idr' => 500_000,
        'status' => 'checked_in',
    ]);
    $folio = HotelFolio::create([
        'id' => (string) Str::uuid(),
        'folio_number' => 'FOL-FULL-DEP',
        'reservation_id' => $reservation->id,
        'guest_user_id' => $this->guest->id,
        'total_addon_charges' => 500_000,
        'outstanding_balance' => 500_000,
        'status' => 'open',
    ]);

    $deposit = HotelDeposit::create([
        'id' => (string) Str::uuid(),
        'property_id' => $this->property->id,
        'reservation_id' => $reservation->id,
        'guest_user_id' => $this->guest->id,
        'amount_idr' => 500_000,
        'applied_amount_idr' => 0,
        'refunded_amount_idr' => 0,
        'payment_method' => 'CASH',
        'status' => 'HELD',
        'idempotency_key' => 'dep_full_'.Str::random(6),
    ]);

    $ledgerMock = Mockery::mock(Ledger::class);
    $ledgerMock->shouldReceive('post')->once(); // 1 deposit apply post only

    $action = new CheckOutAndSettleFolioAction($ledgerMock);
    $res = $action->handle(
        folioId: $folio->id,
        idempotencyKey: 'key_full_dep_01',
        paymentAmountIdr: 0
    );

    expect($res['deposit_applied_idr'])->toBe(500_000)
        ->and($res['status'])->toBe('CHECKOUT_COMPLETED');

    $deposit->refresh();
    expect($deposit->status)->toBe('APPLIED')
        ->and($deposit->applied_amount_idr)->toBe(500_000);

    $folio->refresh();
    expect((int) $folio->outstanding_balance)->toBe(0)
        ->and($folio->total_paid)->toBe(500_000);
});

// ─── 11. Credit card only via execute ────────────────────────────────────────

it('resolves credit card payment method when only card is received', function () {
    $this->room->update(['status' => 'occupied']);
    $reservation = HotelReservation::create([
        'id' => (string) Str::uuid(),
        'reservation_number' => 'HTL/RSV/CO-CARD',
        'property_id' => $this->property->id,
        'room_id' => $this->room->id,
        'guest_user_id' => $this->guest->id,
        'check_in_date' => now()->subDays(1)->toDateString(),
        'check_out_date' => now()->toDateString(),
        'daily_rate_idr' => 800_000,
        'total_room_charge_idr' => 800_000,
        'status' => 'checked_in',
    ]);
    $folio = HotelFolio::create([
        'id' => (string) Str::uuid(),
        'folio_number' => 'FOL-CARD',
        'reservation_id' => $reservation->id,
        'guest_user_id' => $this->guest->id,
        'total_addon_charges' => 800_000,
        'outstanding_balance' => 800_000,
        'status' => 'open',
    ]);

    $ledgerMock = Mockery::mock(Ledger::class);
    $ledgerMock->shouldReceive('post')->once()->withArgs(function (PostingDTO $dto) {
        expect($dto->description)->toContain('CREDIT_CARD');

        return true;
    });

    $action = new CheckOutAndSettleFolioAction($ledgerMock);
    $res = $action->execute(
        folioId: $folio->id,
        idempotencyKey: 'key_card_only_01',
        cardReceivedIdr: 800_000,
        cashReceivedIdr: 0
    );

    expect($res['status'])->toBe('CHECKOUT_COMPLETED');
    $folio->refresh();
    expect((int) $folio->outstanding_balance)->toBe(0)
        ->and($folio->total_paid)->toBe(800_000);
});

// ─── 12. Cash only via execute ───────────────────────────────────────────────

it('resolves cash payment method when only cash is received via execute', function () {
    $this->room->update(['status' => 'occupied']);
    $reservation = HotelReservation::create([
        'id' => (string) Str::uuid(),
        'reservation_number' => 'HTL/RSV/CO-CASHONLY',
        'property_id' => $this->property->id,
        'room_id' => $this->room->id,
        'guest_user_id' => $this->guest->id,
        'check_in_date' => now()->subDays(1)->toDateString(),
        'check_out_date' => now()->toDateString(),
        'daily_rate_idr' => 600_000,
        'total_room_charge_idr' => 600_000,
        'status' => 'checked_in',
    ]);
    $folio = HotelFolio::create([
        'id' => (string) Str::uuid(),
        'folio_number' => 'FOL-CASHONLY',
        'reservation_id' => $reservation->id,
        'guest_user_id' => $this->guest->id,
        'total_addon_charges' => 600_000,
        'outstanding_balance' => 600_000,
        'status' => 'open',
    ]);

    $ledgerMock = Mockery::mock(Ledger::class);
    $ledgerMock->shouldReceive('post')->once()->withArgs(function (PostingDTO $dto) {
        expect($dto->description)->toContain('CASH');

        return true;
    });

    $action = new CheckOutAndSettleFolioAction($ledgerMock);
    $res = $action->execute(
        folioId: $folio->id,
        idempotencyKey: 'key_cash_only_exec_01',
        cashReceivedIdr: 600_000,
        cardReceivedIdr: 0
    );

    expect($res['status'])->toBe('CHECKOUT_COMPLETED');
    $folio->refresh();
    expect((int) $folio->outstanding_balance)->toBe(0)
        ->and($folio->total_paid)->toBe(600_000);
});

it('charges late check-out fee when checking out after property cutoff time', function () {
    $this->property->update([
        'check_out_time' => '12:00:00',
        'late_checkout_fee_idr' => 250_000,
    ]);

    $reservation = HotelReservation::create([
        'id' => (string) Str::uuid(),
        'reservation_number' => 'HTL/RSV/LATE-CO-01',
        'property_id' => $this->property->id,
        'room_id' => $this->room->id,
        'guest_user_id' => $this->guest->id,
        'check_in_date' => Carbon::now()->subDays(2)->toDateString(),
        'check_out_date' => Carbon::now()->toDateString(),
        'daily_rate_idr' => 1_000_000,
        'total_room_charge_idr' => 2_000_000,
        'status' => 'checked_in',
    ]);

    // Folio starting at 0 balance, but late fee will add 250,000
    $folio = HotelFolio::create([
        'id' => (string) Str::uuid(),
        'reservation_id' => $reservation->id,
        'guest_user_id' => $this->guest->id,
        'total_room_charges' => 2_000_000,
        'total_paid' => 2_000_000,
        'outstanding_balance' => 0,
        'status' => 'open',
    ]);

    $clockMock = Mockery::mock(ClockInterface::class);
    // 14:30:00 (after 12:00:00)
    $clockMock->shouldReceive('now')->andReturn(Carbon::parse(Carbon::now()->toDateString().' 14:30:00'));

    $action = new CheckOutAndSettleFolioAction(
        ledger: null,
        clock: $clockMock
    );

    // Pay 250,000 to cover the late check-out fee
    $res = $action->execute(
        folioId: $folio->id,
        paymentMethod: 'CASH',
        idempotencyKey: 'late_fee_pay_01',
        paymentAmountIdr: 250_000
    );

    expect($res['status'])->toBe('CHECKOUT_COMPLETED');

    $folioLine = HotelFolioLine::where('folio_id', $folio->id)
        ->where('line_type', 'LATE_CHECKOUT')
        ->first();
    expect($folioLine)->not->toBeNull()
        ->and($folioLine->amount_idr)->toBe(250_000);
});

it('settles folio balance via corporate city ledger transfer', function () {
    $corporateAccount = HotelCityLedgerAccount::create([
        'id' => (string) Str::uuid(),
        'property_id' => $this->property->id,
        'company_code' => 'CORP_SETTLE_TEST',
        'company_name' => 'PT Megah Prima Jaya',
        'credit_limit_idr' => 10_000_000,
        'outstanding_balance_idr' => 1_000_000,
        'is_active' => true,
    ]);

    $reservation = HotelReservation::create([
        'id' => (string) Str::uuid(),
        'reservation_number' => 'HTL/RSV/CITY-SETTLE-01',
        'property_id' => $this->property->id,
        'room_id' => $this->room->id,
        'guest_user_id' => $this->guest->id,
        'check_in_date' => Carbon::now()->subDays(2)->toDateString(),
        'check_out_date' => Carbon::now()->toDateString(),
        'daily_rate_idr' => 1_000_000,
        'total_room_charge_idr' => 2_000_000,
        'status' => 'checked_in',
    ]);

    $folio = HotelFolio::create([
        'id' => (string) Str::uuid(),
        'reservation_id' => $reservation->id,
        'guest_user_id' => $this->guest->id,
        'total_room_charges' => 2_000_000,
        'total_paid' => 1_000_000,
        'outstanding_balance' => 1_000_000,
        'status' => 'open',
    ]);

    $action = app(CheckOutAndSettleFolioAction::class);
    $res = $action->execute(
        folioId: $folio->id,
        idempotencyKey: 'co_city_ledger_key_01',
        corporateAccountId: $corporateAccount->id
    );

    expect($res['status'])->toBe('CHECKOUT_COMPLETED');
    $folio->refresh();
    expect((int) $folio->outstanding_balance)->toBe(0)
        ->and($folio->status)->toBe('settled');

    $corporateAccount->refresh();
    expect((int) $corporateAccount->outstanding_balance_idr)->toBe(2_000_000);
});
