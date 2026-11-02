<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Banking\Contracts\Ledger;
use Modules\Banking\Contracts\LedgerAccount;
use Modules\Core\Contracts\AuditTrailInterface;
use Modules\Core\Contracts\OutboxBusInterface;
use Modules\Hotel\Application\Actions\ExecuteOverbookingWalkCompensationAction;
use Modules\Hotel\Domain\Models\HotelReservation;
use Modules\Hotel\tests\Unit\HotelUnitTestHelper;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class, HotelUnitTestHelper::class);

covers(ExecuteOverbookingWalkCompensationAction::class);

beforeEach(function () {
    $this->setupHotelFixture();
});

it('handles overbooking walk compensation and posts to ledger with custom amounts, audit and outbox', function () {
    $ledgerMock = Mockery::mock(Ledger::class);
    $ledgerMock->shouldReceive('post')->once();

    $auditMock = Mockery::mock(AuditTrailInterface::class);
    $auditMock->shouldReceive('record')
        ->withArgs(function ($action, $auditable, $oldValues, $newValues, $context, $correlationId, $impactType) {
            return $action === 'hotel.overbooking.walk_compensated'
                && $auditable instanceof HotelReservation
                && $newValues['transport_allowance_idr'] === 400000
                && $newValues['goodwill_credit_idr'] === 800000
                && $newValues['total_compensation_idr'] === 2400000;
        })
        ->once();
    $this->app->instance(AuditTrailInterface::class, $auditMock);

    $outboxMock = Mockery::mock(OutboxBusInterface::class);
    $outboxMock->shouldReceive('record')
        ->withArgs(function ($eventType, $payload, $headers, $idempotencyKey) {
            return $eventType === 'GuestOverbookingWalkCompensated'
                && $payload['alternate_property'] === 'Mitra Nusantara'
                && $payload['total_compensation_idr'] === 2400000;
        })
        ->once();
    $this->app->instance(OutboxBusInterface::class, $outboxMock);

    LedgerAccount::firstOrCreate(['code' => 'htl:overbooking_expense:IDR'], [
        'name' => 'Beban Overbooking Hotel',
        'type' => 'EXPENSE',
        'normal_balance' => 'DEBIT',
    ]);

    $reservation = HotelReservation::create([
        'id' => (string) Str::uuid(),
        'reservation_number' => 'HTL/RSV/WALK-001',
        'property_id' => $this->property->id,
        'room_id' => null,
        'guest_user_id' => $this->guest->id,
        'check_in_date' => now()->toDateString(),
        'check_out_date' => now()->addDays(2)->toDateString(),
        'daily_rate_idr' => 1200000,
        'total_room_charge_idr' => 2400000,
        'status' => 'confirmed',
    ]);

    $action = new ExecuteOverbookingWalkCompensationAction($ledgerMock);
    $result = $action->handle(
        reservationId: $reservation->id,
        alternatePropertyName: 'Mitra Nusantara',
        transportAllowanceIdr: 400000,
        goodwillCreditIdr: 800000
    );

    expect($result['reservation']->status)->toBe('RELOCATED_WALK')
        ->and($result['alternate_property_name'])->toBe('Mitra Nusantara')
        ->and($result['transport_allowance_idr'])->toBe(400000)
        ->and($result['goodwill_credit_idr'])->toBe(800000)
        ->and($result['total_compensation_idr'])->toBe(1200000 + 400000 + 800000);

    $reservation->refresh();
    expect($reservation->status)->toBe('RELOCATED_WALK');
});

it('handles overbooking walk compensation using default allowance and goodwill credit without ledger', function () {
    LedgerAccount::firstOrCreate(['code' => 'htl:overbooking_expense:IDR'], [
        'name' => 'Beban Overbooking Hotel',
        'type' => 'EXPENSE',
        'normal_balance' => 'DEBIT',
    ]);

    $reservation = HotelReservation::create([
        'id' => (string) Str::uuid(),
        'reservation_number' => 'HTL/RSV/WALK-002',
        'property_id' => $this->property->id,
        'room_id' => null,
        'guest_user_id' => $this->guest->id,
        'check_in_date' => now()->toDateString(),
        'check_out_date' => now()->addDays(1)->toDateString(),
        'daily_rate_idr' => 800000,
        'total_room_charge_idr' => 800000,
        'status' => 'confirmed',
    ]);

    // Action tanpa ledger
    $action = new ExecuteOverbookingWalkCompensationAction(null);
    $result = $action->handle(
        reservationId: $reservation->id,
        alternatePropertyName: 'Hotel Rekanan Sebelah'
    );

    expect($result['reservation']->status)->toBe('RELOCATED_WALK')
        ->and($result['alternate_property_name'])->toBe('Hotel Rekanan Sebelah')
        ->and($result['transport_allowance_idr'])->toBe(350000)
        ->and($result['goodwill_credit_idr'])->toBe(1000000)
        ->and($result['total_compensation_idr'])->toBe(800000 + 350000 + 1000000);
});
