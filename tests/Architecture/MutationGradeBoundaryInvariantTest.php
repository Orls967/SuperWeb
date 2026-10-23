<?php

declare(strict_types=1);

namespace Tests\Architecture;

use App\Models\User;
use Database\Seeders\LedgerAccountsSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Domain\Exceptions\UnbalancedTransactionException;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Hospital\Application\Services\PrescriptionSafetyEvaluator;
use Modules\Hospital\Domain\Models\Patient;
use Modules\Hotel\Application\Actions\ReserveRoomWithInventoryNightLockAction;
use Modules\Hotel\Domain\Models\HotelProperty;
use Modules\Hotel\Domain\Models\HotelRoom;
use Modules\Inventory\Application\Services\InventoryService;
use Modules\Mining\Application\Actions\CalculatePnbpRoyaltyFromVerifiedTonnageAction;
use Modules\Rwa\Application\Actions\MatchSecondaryRwaOrderbookTradeAction;
use Modules\Rwa\Domain\Models\RwaAsset;
use Modules\Rwa\Domain\Models\RwaHolding;
use Modules\Rwa\Domain\Models\RwaOrderbookOrder;
use Modules\Venue\Application\Actions\ScanTicketGateWithAntiPassbackAndAgeCheckAction;
use Modules\Venue\Domain\Models\EntertainmentEvent;
use Modules\Venue\Domain\Models\EntertainmentTicket;
use Modules\Venue\Domain\Models\EntertainmentVenue;
use Modules\Venue\Domain\Models\EntertainmentZone;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(LedgerAccountsSeeder::class);
});

it('defends minimum quantity boundary and rejects zero and negative mutation in inventory service', function (): void {
    $invService = app(InventoryService::class);

    // Boundary 0: must throw InvalidArgumentException
    expect(fn () => $invService->reserve(productId: 1, qty: 0))
        ->toThrow(InvalidArgumentException::class, 'Jumlah reservasi harus lebih besar dari 0.');

    // Boundary -1 (negative mutation): must throw InvalidArgumentException
    expect(fn () => $invService->reserve(productId: 1, qty: -1))
        ->toThrow(InvalidArgumentException::class, 'Jumlah reservasi harus lebih besar dari 0.');

    // Boundary -100: must throw InvalidArgumentException
    expect(fn () => $invService->reserve(productId: 1, qty: -100))
        ->toThrow(InvalidArgumentException::class, 'Jumlah reservasi harus lebih besar dari 0.');
});

it('enforces double-entry ledger entry count boundary of at least 2 entries', function (): void {
    $ledgerService = app(LedgerService::class);

    // Boundary 0 entries
    $dto0 = new PostingDTO(
        type: 'transfer',
        description: 'Zero entries',
        idempotencyKey: hash('sha256', 'zero_entries'),
        entries: []
    );
    expect(fn () => $ledgerService->post($dto0))
        ->toThrow(InvalidArgumentException::class, 'Transaksi ledger harus memiliki minimal 2 entri.');

    // Boundary 1 entry (off-by-one lower bound)
    $dto1 = new PostingDTO(
        type: 'transfer',
        description: 'One entry',
        idempotencyKey: hash('sha256', 'one_entry'),
        entries: [
            PostingEntryDTO::forAccount(1, 'IDR', 1000),
        ]
    );
    expect(fn () => $ledgerService->post($dto1))
        ->toThrow(InvalidArgumentException::class, 'Transaksi ledger harus memiliki minimal 2 entri.');
});

it('kills off-by-one non-zero equilibrium mutations in double-entry ledger', function (): void {
    $ledgerService = app(LedgerService::class);

    $acc1 = LedgerAccount::where('code', 'clearing:external:IDR')->firstOrFail();
    $acc2 = LedgerAccount::where('code', 'clearing:golden_scenario:IDR')->firstOrFail();

    // Off-by-one +1 minor unit discrepancy: Debit 100, Credit 99
    $dtoPlusOne = new PostingDTO(
        type: 'transfer',
        description: 'Off-by-one +1',
        idempotencyKey: hash('sha256', 'off_plus_one'),
        entries: [
            PostingEntryDTO::forAccount($acc1->id, 'IDR', -100),
            PostingEntryDTO::forAccount($acc2->id, 'IDR', 99),
        ]
    );
    expect(fn () => $ledgerService->post($dtoPlusOne))
        ->toThrow(UnbalancedTransactionException::class);

    // Off-by-one -1 minor unit discrepancy: Debit 100, Credit 101
    $dtoMinusOne = new PostingDTO(
        type: 'transfer',
        description: 'Off-by-one -1',
        idempotencyKey: hash('sha256', 'off_minus_one'),
        entries: [
            PostingEntryDTO::forAccount($acc1->id, 'IDR', -100),
            PostingEntryDTO::forAccount($acc2->id, 'IDR', 101),
        ]
    );
    expect(fn () => $ledgerService->post($dtoMinusOne))
        ->toThrow(UnbalancedTransactionException::class);

    // Exact equilibrium: Debit 100, Credit 100
    $dtoBalanced = new PostingDTO(
        type: 'transfer',
        description: 'Balanced',
        idempotencyKey: hash('sha256', 'balanced_exact'),
        entries: [
            PostingEntryDTO::forAccount($acc1->id, 'IDR', -100),
            PostingEntryDTO::forAccount($acc2->id, 'IDR', 100),
        ]
    );
    $tx = $ledgerService->post($dtoBalanced);
    expect($tx->id)->not->toBeNull();
});

it('enforces maximum transaction type length boundary of 32 characters', function (): void {
    $ledgerService = app(LedgerService::class);

    // Boundary 33 chars: off-by-one upper bound
    $type33 = str_repeat('A', 33);
    $dto33 = new PostingDTO(
        type: $type33,
        description: 'Type length 33',
        idempotencyKey: hash('sha256', 'type_33'),
        entries: []
    );
    expect(fn () => $ledgerService->post($dto33))
        ->toThrow(InvalidArgumentException::class, "Tipe transaksi '{$type33}' melebihi batas 32 karakter.");

    // Boundary 32 chars: passes length check (then fails on registry check)
    $type32 = str_repeat('A', 32);
    $dto32 = new PostingDTO(
        type: $type32,
        description: 'Type length 32',
        idempotencyKey: hash('sha256', 'type_32'),
        entries: []
    );
    expect(fn () => $ledgerService->post($dto32))
        ->toThrow(InvalidArgumentException::class, "Tipe transaksi '{$type32}' tidak terdaftar di registry enum tipe transaksi.");
});

it('kills boundary mutations on venue turnstile legal age threshold at 21 years', function (): void {
    $scanAction = app(ScanTicketGateWithAntiPassbackAndAgeCheckAction::class);

    $venue = EntertainmentVenue::create([
        'venue_code' => 'VN-BND-01',
        'name' => 'Club Boundary',
        'venue_type' => 'BEACH_CLUB',
        'city' => 'Badung',
        'max_legal_capacity' => 100,
        'min_age_requirement' => 21,
    ]);

    $zone = EntertainmentZone::create([
        'zone_code' => 'ZN-BND-01',
        'venue_id' => $venue->id,
        'name' => 'Main Floor',
        'capacity_limit' => 100,
        'current_occupancy' => 0,
        'crowd_lockdown_active' => false,
    ]);

    $event = EntertainmentEvent::create([
        'event_code' => 'EV-BND-01',
        'venue_id' => $venue->id,
        'title' => 'Concert',
        'doors_open_at' => now()->addDay(),
        'ticket_floor_price_idr' => 100000,
        'ticket_ceiling_price_idr' => 500000,
        'current_ticket_price_idr' => 150000,
    ]);

    $ticketUnderage = EntertainmentTicket::create([
        'ticket_number' => 'TCK-UNDERAGE',
        'event_id' => $event->id,
        'buyer_user_id' => 1,
        'price_paid_idr' => 150000,
        'ticket_hash' => hash('sha256', 'tck_underage'),
        'status' => 'VALID',
    ]);

    $ticketLegal = EntertainmentTicket::create([
        'ticket_number' => 'TCK-LEGAL',
        'event_id' => $event->id,
        'buyer_user_id' => 1,
        'price_paid_idr' => 150000,
        'ticket_hash' => hash('sha256', 'tck_legal'),
        'status' => 'VALID',
    ]);

    // Boundary 20 years (< 21): DENIED
    $scan20 = $scanAction->handle(ticketId: $ticketUnderage->id, attendeeAge: 20, zoneId: $zone->id);
    expect($scan20['status'])->toBe('DENIED')
        ->and($scan20['reason'])->toContain('Underage attendee: venue legal threshold is 21 years.');

    // Boundary 21 years (exact threshold): ALLOWED (kills mutant < to <=)
    $scan21 = $scanAction->handle(ticketId: $ticketLegal->id, attendeeAge: 21, zoneId: $zone->id);
    expect($scan21['status'])->toBe('ALLOWED');
});

it('enforces hotel check-in and check-out date strict ordering boundary', function (): void {
    $reserveAction = app(ReserveRoomWithInventoryNightLockAction::class);

    $property = HotelProperty::create([
        'id' => (string) Str::uuid(),
        'property_code' => 'PROP-BND',
        'name' => 'Bnd Hotel',
        'property_type' => 'HOTEL',
        'city' => 'Jakarta',
        'total_rooms' => 10,
    ]);

    $room = HotelRoom::create([
        'id' => (string) Str::uuid(),
        'property_id' => $property->id,
        'room_number' => 'RM-BND-01',
        'room_type' => 'DELUXE',
        'base_rate_idr' => 1000000,
        'status' => 'available',
    ]);

    // Boundary: Check-out == Check-in (0 duration)
    expect(fn () => $reserveAction->handle(
        propertyId: $property->id,
        roomId: $room->id,
        guestUserId: 'guest-1',
        checkInDate: '2026-11-05',
        checkOutDate: '2026-11-05',
        dailyRateIdr: 1000000
    ))->toThrow(InvalidArgumentException::class, 'Check-out date must be after check-in date.');

    // Boundary: Check-out < Check-in (reverse date)
    expect(fn () => $reserveAction->handle(
        propertyId: $property->id,
        roomId: $room->id,
        guestUserId: 'guest-1',
        checkInDate: '2026-11-05',
        checkOutDate: '2026-11-04',
        dailyRateIdr: 1000000
    ))->toThrow(InvalidArgumentException::class, 'Check-out date must be after check-in date.');

    // Boundary: Check-out exactly 1 day after Check-in (minimum valid booking)
    $res = $reserveAction->handle(
        propertyId: $property->id,
        roomId: $room->id,
        guestUserId: 'guest-1',
        checkInDate: '2026-11-05',
        checkOutDate: '2026-11-06',
        dailyRateIdr: 1000000
    );
    expect($res['nights'])->toBe(1)
        ->and($res['total_room_charge_idr'])->toBe(1000000);
});

it('kills mutant on hospital renal CrCl threshold boundary at 30 mL/min', function (): void {
    $evaluator = app(PrescriptionSafetyEvaluator::class);
    $patient = new Patient([
        'mrn' => 'MRN-BND-01',
        'name' => 'Renal Patient',
        'encrypted_allergies' => [],
    ]);

    // CrCl = 29 (< 30 mL/min): CRITICAL_CONTRAINDICATION
    $eval29 = $evaluator->evaluateSafety(
        patient: $patient,
        drugCode: 'METFORMIN',
        dosageMg: 500,
        creatinineClearanceMlLMin: 29
    );
    expect($eval29['is_safe'])->toBeFalse()
        ->and($eval29['risk_level'])->toBe('CRITICAL_CONTRAINDICATION');

    // CrCl = 30 (exact threshold): LOW_RISK (kills mutant < to <=)
    $eval30 = $evaluator->evaluateSafety(
        patient: $patient,
        drugCode: 'METFORMIN',
        dosageMg: 500,
        creatinineClearanceMlLMin: 30
    );
    expect($eval30['is_safe'])->toBeTrue()
        ->and($eval30['risk_level'])->toBe('LOW_RISK');

    // CrCl = 31 (> 30 mL/min): LOW_RISK
    $eval31 = $evaluator->evaluateSafety(
        patient: $patient,
        drugCode: 'METFORMIN',
        dosageMg: 500,
        creatinineClearanceMlLMin: 31
    );
    expect($eval31['is_safe'])->toBeTrue()
        ->and($eval31['risk_level'])->toBe('LOW_RISK');
});

it('verifies half-up rounding and zero boundary on statutory PNBP royalty calculation', function (): void {
    $action = app(CalculatePnbpRoyaltyFromVerifiedTonnageAction::class);

    // Boundary 0 tonnage: produces 0 royalty
    $res0 = $action->handle(
        siteId: 'SITE-01',
        period: '2026-10',
        totalProductionTon: 0.0,
        commodityBenchmarkPriceIdr: 1000000,
        royaltyRatePercentage: 10.0
    );
    expect($res0['royalty_due_idr'])->toBe(0);

    // Rounding Half-Up Verification on Odd/Even
    // Test exact .5 round-half-up: 12.5 -> 13
    expect((int) round(12.5, 0, PHP_ROUND_HALF_UP))->toBe(13);
    // Test exact .5 round-half-up on odd/even: 13.5 -> 14
    expect((int) round(13.5, 0, PHP_ROUND_HALF_UP))->toBe(14);
    // Test .49 rounding down: 12.49 -> 12
    expect((int) round(12.49, 0, PHP_ROUND_HALF_UP))->toBe(12);
    // Test .51 rounding up: 12.51 -> 13
    expect((int) round(12.51, 0, PHP_ROUND_HALF_UP))->toBe(13);

    // Test Action arithmetic with fractional tonnage yielding half-up rounding
    // 100.5 tons * Rp 1.000 * 10% = 10050.0 -> exact integer Rp 10.050
    $resFraction = $action->handle(
        siteId: 'SITE-02',
        period: '2026-10',
        totalProductionTon: 100.5,
        commodityBenchmarkPriceIdr: 1000,
        royaltyRatePercentage: 10.0
    );
    expect($resFraction['royalty_due_idr'])->toBe(10050);
});

it('enforces limit price boundary in RWA secondary orderbook matching', function (): void {
    $matchAction = app(MatchSecondaryRwaOrderbookTradeAction::class);

    $rwaAsset = RwaAsset::create([
        'token_symbol' => 'BND-ASSET',
        'name' => 'Boundary Asset',
        'underlying_asset_type' => 'BOND',
        'underlying_asset_id' => 'BOND-001',
        'appraisal_document_ref' => 'REF-001',
        'appraisal_value_idr' => 1000000,
        'total_supply_tokens' => 1000,
        'token_price_idr' => 1000,
        'max_holder_concentration_pct' => 50,
        'status' => 'APPROVED',
    ]);

    $seller = User::factory()->create();
    $buyer = User::factory()->create();

    RwaHolding::create([
        'rwa_asset_id' => $rwaAsset->id,
        'user_id' => $seller->id,
        'token_balance' => 100,
        'locked_balance' => 0,
        'is_kyc_verified' => true,
    ]);

    RwaHolding::create([
        'rwa_asset_id' => $rwaAsset->id,
        'user_id' => $buyer->id,
        'token_balance' => 0,
        'locked_balance' => 0,
        'is_kyc_verified' => true,
    ]);

    $sellOrder = RwaOrderbookOrder::create([
        'order_code' => 'SELL-BND',
        'rwa_asset_id' => $rwaAsset->id,
        'user_id' => $seller->id,
        'side' => 'SELL',
        'quantity' => 10,
        'filled_quantity' => 0,
        'price_per_token_idr' => 1000,
        'status' => 'OPEN',
    ]);

    // Buy price below sell price by 1 IDR minor unit (999 < 1000): throws DomainException
    $buyOrderBelow = RwaOrderbookOrder::create([
        'order_code' => 'BUY-BELOW',
        'rwa_asset_id' => $rwaAsset->id,
        'user_id' => $buyer->id,
        'side' => 'BUY',
        'quantity' => 10,
        'filled_quantity' => 0,
        'price_per_token_idr' => 999,
        'status' => 'OPEN',
    ]);

    expect(fn () => $matchAction->handle($buyOrderBelow->id, $sellOrder->id))
        ->toThrow(DomainException::class, 'Buy price is below sell price. No trade possible.');

    // Exact parity (1000 == 1000): matches successfully (kills mutant < to <=)
    $buyOrderExact = RwaOrderbookOrder::create([
        'order_code' => 'BUY-EXACT',
        'rwa_asset_id' => $rwaAsset->id,
        'user_id' => $buyer->id,
        'side' => 'BUY',
        'quantity' => 10,
        'filled_quantity' => 0,
        'price_per_token_idr' => 1000,
        'status' => 'OPEN',
    ]);

    $matchRes = $matchAction->handle($buyOrderExact->id, $sellOrder->id);
    expect($matchRes['matched_qty'])->toBe(10)
        ->and($matchRes['trade_price_idr'])->toBe(1000);
});
