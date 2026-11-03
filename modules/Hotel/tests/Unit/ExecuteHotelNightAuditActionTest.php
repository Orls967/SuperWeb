<?php

declare(strict_types=1);

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Banking\Contracts\Ledger;
use Modules\Hotel\Application\Actions\ExecuteHotelNightAuditAction;
use Modules\Hotel\Application\Services\HotelBusinessDateService;
use Modules\Hotel\Application\Services\HotelInventoryService;
use Modules\Hotel\Domain\Models\HotelDailyRevenueSnapshot;
use Modules\Hotel\Domain\Models\HotelDeposit;
use Modules\Hotel\Domain\Models\HotelFolio;
use Modules\Hotel\Domain\Models\HotelFolioLine;
use Modules\Hotel\Domain\Models\HotelRatePlan;
use Modules\Hotel\Domain\Models\HotelReservation;
use Modules\Hotel\Domain\Models\HotelRoomNight;
use Modules\Hotel\tests\Unit\HotelUnitTestHelper;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class, HotelUnitTestHelper::class);

covers(ExecuteHotelNightAuditAction::class);

beforeEach(function () {
    $this->setupHotelFixture();
});

it('executes night audit cycle and advances business date', function () {
    $action = app(ExecuteHotelNightAuditAction::class);
    $snapshot = $action->execute($this->property->id);

    expect($snapshot)->toBeInstanceOf(HotelDailyRevenueSnapshot::class)
        ->and($snapshot->property_id)->toBe($this->property->id)
        ->and($snapshot->total_rooms)->toBeGreaterThan(0);

    // Idempotent execution on same date returns existing
    $snapshot2 = $action->execute($this->property->id, $snapshot->business_date->toDateString());
    expect($snapshot2->id)->toBe($snapshot->id);
});

it('posts room charges with ledger and calculates tax and service charge during checkpoint 1', function () {
    $bDateService = app(HotelBusinessDateService::class);
    $currentDate = $bDateService->getCurrentBusinessDate($this->property->id);

    $this->room->update(['status' => 'occupied']);

    $reservation = HotelReservation::create([
        'id' => (string) Str::uuid(),
        'reservation_number' => 'HTL/RSV/NA-OCC-01',
        'property_id' => $this->property->id,
        'room_id' => $this->room->id,
        'guest_user_id' => $this->guest->id,
        'check_in_date' => $currentDate,
        'check_out_date' => Carbon::parse($currentDate)->addDays(2)->toDateString(),
        'daily_rate_idr' => 1_000_000,
        'total_room_charge_idr' => 2_000_000,
        'status' => 'checked_in',
    ]);

    $folio = HotelFolio::create([
        'id' => (string) Str::uuid(),
        'folio_number' => 'FOL-NA-01',
        'reservation_id' => $reservation->id,
        'guest_user_id' => $this->guest->id,
        'total_room_charges' => 0,
        'total_addon_charges' => 0,
        'outstanding_balance' => 0,
        'status' => 'open',
    ]);

    $ledgerMock = Mockery::mock(Ledger::class);
    $ledgerMock->shouldReceive('post')->atLeast()->once();

    $action = new ExecuteHotelNightAuditAction(
        $bDateService,
        app(HotelInventoryService::class),
        $ledgerMock
    );

    $snapshot = $action->execute($this->property->id, $currentDate);

    $folio->refresh();
    // base: 1.000.000, service 10% = 100.000, tax 10% of 1.100.000 = 110.000, total = 1.210.000
    expect($folio->total_room_charges)->toBe(1_210_000)
        ->and($folio->outstanding_balance)->toBe(1_210_000);

    $line = HotelFolioLine::where('folio_id', $folio->id)->first();
    expect($line)->not->toBeNull()
        ->and($line->line_type)->toBe('ROOM_CHARGE')
        ->and($line->base_amount_idr)->toBe(1_000_000)
        ->and($line->service_charge_idr)->toBe(100_000)
        ->and($line->tax_pbjt_idr)->toBe(110_000)
        ->and($line->amount_idr)->toBe(1_210_000);
});

it('handles no show reservations, charges penalty to deposit with ledger, and releases remaining room nights', function () {
    $bDateService = app(HotelBusinessDateService::class);
    $currentDate = $bDateService->getCurrentBusinessDate($this->property->id);

    $noShowReservation = HotelReservation::create([
        'id' => (string) Str::uuid(),
        'reservation_number' => 'HTL/RSV/NA-NOSHOW-01',
        'property_id' => $this->property->id,
        'room_id' => $this->room->id,
        'guest_user_id' => $this->guest->id,
        'check_in_date' => $currentDate,
        'check_out_date' => Carbon::parse($currentDate)->addDays(2)->toDateString(),
        'daily_rate_idr' => 500_000,
        'total_room_charge_idr' => 1_000_000,
        'status' => 'confirmed', // not checked in
    ]);

    // Create room nights: tonight and tomorrow
    HotelRoomNight::create([
        'id' => (string) Str::uuid(),
        'property_id' => $this->property->id,
        'room_type_id' => $this->roomType->id,
        'reservation_id' => $noShowReservation->id,
        'room_id' => $this->room->id,
        'night_date' => $currentDate,
        'rate_idr' => 500_000,
        'status' => 'reserved',
    ]);
    HotelRoomNight::create([
        'id' => (string) Str::uuid(),
        'property_id' => $this->property->id,
        'room_type_id' => $this->roomType->id,
        'reservation_id' => $noShowReservation->id,
        'room_id' => $this->room->id,
        'night_date' => Carbon::parse($currentDate)->addDay()->toDateString(),
        'rate_idr' => 500_000,
        'status' => 'reserved',
    ]);

    $deposit = HotelDeposit::create([
        'id' => (string) Str::uuid(),
        'property_id' => $this->property->id,
        'reservation_id' => $noShowReservation->id,
        'guest_user_id' => $this->guest->id,
        'amount_idr' => 1_000_000,
        'applied_amount_idr' => 0,
        'refunded_amount_idr' => 0,
        'payment_method' => 'CASH',
        'status' => 'HELD',
        'idempotency_key' => 'dep_noshow_'.Str::random(6),
    ]);

    $ledgerMock = Mockery::mock(Ledger::class);
    $ledgerMock->shouldReceive('post')->atLeast()->once();

    $action = new ExecuteHotelNightAuditAction(
        $bDateService,
        app(HotelInventoryService::class),
        $ledgerMock
    );

    $action->execute($this->property->id, $currentDate);

    $noShowReservation->refresh();
    expect($noShowReservation->status)->toBe('no_show');

    // Future night (tomorrow) should be deleted
    $tomorrowNight = HotelRoomNight::where('reservation_id', $noShowReservation->id)
        ->whereDate('night_date', '>', $currentDate)
        ->count();
    expect($tomorrowNight)->toBe(0);

    // Deposit penalty applied
    $deposit->refresh();
    expect($deposit->applied_amount_idr)->toBe(500_000)
        ->and($deposit->status)->toBe('PARTIALLY_REFUNDED');
});

it('calculates daily snapshot metrics accurately: occupancy, ADR, RevPAR, and F&B revenue', function () {
    $bDateService = app(HotelBusinessDateService::class);
    $currentDate = $bDateService->getCurrentBusinessDate($this->property->id);

    $this->room->update(['status' => 'occupied']);

    $reservation = HotelReservation::create([
        'id' => (string) Str::uuid(),
        'reservation_number' => 'HTL/RSV/NA-REV-01',
        'property_id' => $this->property->id,
        'room_id' => $this->room->id,
        'guest_user_id' => $this->guest->id,
        'check_in_date' => $currentDate,
        'check_out_date' => Carbon::parse($currentDate)->addDays(1)->toDateString(),
        'daily_rate_idr' => 1_000_000,
        'total_room_charge_idr' => 1_000_000,
        'status' => 'checked_in',
    ]);

    $folio = HotelFolio::create([
        'id' => (string) Str::uuid(),
        'folio_number' => 'FOL-NA-REV',
        'reservation_id' => $reservation->id,
        'guest_user_id' => $this->guest->id,
        'total_room_charges' => 0,
        'total_addon_charges' => 200_000,
        'outstanding_balance' => 0,
        'status' => 'open',
    ]);

    // Tambah line FNB
    HotelFolioLine::create([
        'id' => (string) Str::uuid(),
        'folio_id' => $folio->id,
        'business_date' => $currentDate,
        'line_type' => 'FNB_OUTLET',
        'description' => 'Dinner',
        'amount_idr' => 242_000,
        'base_amount_idr' => 200_000,
        'service_charge_idr' => 20_000,
        'tax_pbjt_idr' => 22_000,
        'idempotency_key' => 'fnb_snap_'.Str::random(6),
    ]);

    $action = app(ExecuteHotelNightAuditAction::class);
    $snapshot = $action->execute($this->property->id, $currentDate);

    expect($snapshot->occupied_rooms)->toBeGreaterThanOrEqual(1)
        ->and($snapshot->total_fnb_revenue_idr)->toBeGreaterThanOrEqual(200_000)
        ->and($snapshot->total_room_revenue_idr)->toBeGreaterThanOrEqual(1_000_000)
        ->and($snapshot->adr_idr)->toBeGreaterThan(0)
        ->and($snapshot->revpar_idr)->toBeGreaterThan(0);
});

it('resumes from simulated checkpoint failure', function () {
    $action = app(ExecuteHotelNightAuditAction::class);
    $bDateService = app(HotelBusinessDateService::class);
    $currentDate = $bDateService->getCurrentBusinessDate($this->property->id);

    $action->simulateCheckpointFailure($this->property->id, 'POST_ROOM_CHARGES');

    $record = $bDateService->getBusinessDateRecord($this->property->id);
    expect($record->status)->toBe('auditing')
        ->and($record->last_checkpoint)->toBe('POST_ROOM_CHARGES');

    // Lanjutkan eksekusi harus selesai sukses
    $snapshot = $action->execute($this->property->id, $currentDate);
    expect($snapshot)->toBeInstanceOf(HotelDailyRevenueSnapshot::class);

    $record->refresh();
    expect($record->status)->toBe('open')
        ->and($record->last_checkpoint)->toBe('COMPLETED');
});

it('charges full stay penalty on no-show for non-refundable rate plan and checks date closed', function () {
    $bDateService = app(HotelBusinessDateService::class);
    $currentDate = $bDateService->getCurrentBusinessDate($this->property->id);

    $nonRefPlan = HotelRatePlan::firstOrCreate(
        ['property_id' => $this->property->id, 'code' => 'NONREF_NOSHOW'],
        [
            'id' => (string) Str::uuid(),
            'name' => 'Non-refundable No Show',
            'plan_type' => 'non_refundable',
            'free_cancellation_hours' => 0,
            'penalty_nights' => 1,
            'requires_deposit' => false,
        ]
    );

    $reservation = HotelReservation::create([
        'id' => (string) Str::uuid(),
        'reservation_number' => 'HTL/RSV/NOSHOW-NONREF',
        'property_id' => $this->property->id,
        'rate_plan_id' => $nonRefPlan->id,
        'room_id' => $this->room->id,
        'guest_user_id' => $this->guest->id,
        'check_in_date' => $currentDate,
        'check_out_date' => Carbon::parse($currentDate)->addDays(3)->toDateString(),
        'daily_rate_idr' => 1_000_000,
        'total_room_charge_idr' => 3_000_000,
        'status' => 'confirmed',
    ]);

    $deposit = HotelDeposit::create([
        'id' => (string) Str::uuid(),
        'property_id' => $this->property->id,
        'reservation_id' => $reservation->id,
        'guest_user_id' => $this->guest->id,
        'amount_idr' => 3_000_000,
        'applied_amount_idr' => 0,
        'refunded_amount_idr' => 0,
        'payment_method' => 'CASH',
        'status' => 'HELD',
        'idempotency_key' => 'dep_nonref_noshow',
    ]);

    $ledgerMock = Mockery::mock(Ledger::class);
    $ledgerMock->shouldReceive('post')->atLeast()->once();

    $action = new ExecuteHotelNightAuditAction(
        businessDateService: $bDateService,
        inventoryService: app(HotelInventoryService::class),
        ledger: $ledgerMock
    );

    expect($bDateService->isDateClosed($this->property->id, $currentDate))->toBeFalse();

    $action->execute($this->property->id);

    $reservation->refresh();
    expect($reservation->status)->toBe('no_show');

    $deposit->refresh();
    // Non-refundable: full penalty 3,000,000 applied to deposit
    expect($deposit->applied_amount_idr)->toBe(3_000_000)
        ->and($deposit->status)->toBe('APPLIED');

    // Date is now closed
    expect($bDateService->isDateClosed($this->property->id, $currentDate))->toBeTrue();
});
