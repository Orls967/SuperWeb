<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Banking\Domain\Models\LedgerTransaction;
use Modules\Logistics\Application\Actions\AccrueLegCostAction;
use Modules\Logistics\Application\Actions\AssignCarrierToLegAction;
use Modules\Logistics\Application\Actions\CompleteShipmentLegAction;
use Modules\Logistics\Application\Actions\PayCarriersAction;
use Modules\Logistics\Application\Services\LogisticsLedger;
use Modules\Logistics\Domain\Exceptions\CarrierException;
use Modules\Logistics\Domain\Models\Carrier;
use Modules\Logistics\Domain\Models\CarrierPayment;
use Modules\Logistics\Domain\Models\Shipment;
use Modules\Logistics\Domain\Models\ShipmentLeg;
use Modules\Logistics\Domain\Services\ShipmentMarginReport;
use Modules\Logistics\tests\Support\MoneyFlowWorld;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->world = new MoneyFlowWorld;
    $this->carrier = Carrier::create(['code' => 'CRR-ALFA', 'name' => 'PT Alfa Trans', 'mode' => 'road', 'payment_terms_days' => 7, 'is_active' => true]);
});

function cbal(string $code): int
{
    return (int) (LedgerAccount::where('code', $code)->value('cached_balance') ?? 0);
}

function makeLeg(Shipment $shipment, object $world, int $seq = 1): ShipmentLeg
{
    return ShipmentLeg::create([
        'shipment_id' => $shipment->id, 'leg_sequence' => $seq, 'mode' => 'road',
        'origin_location_id' => $world->hubA->id, 'destination_location_id' => $world->hubB->id,
        'estimated_departure' => now(), 'estimated_arrival' => now()->addHours(4), 'status' => 'pending',
    ]);
}

test('(a) completing a subcontracted leg accrues carrier cost and payment clears the payable', function () {
    $shipment = $this->world->bookPrepaid($this->world->shipper());
    $leg = makeLeg($shipment, $this->world);

    app(AssignCarrierToLegAction::class)->execute($leg, $this->carrier, 8_000);
    expect(cbal(LogisticsLedger::CARRIER_COST))->toBe(0);

    app(CompleteShipmentLegAction::class)->execute($leg);

    expect(cbal(LogisticsLedger::CARRIER_COST))->toBe(-8_000)
        ->and(cbal(LogisticsLedger::carrierPayableCode($this->carrier->id)))->toBe(8_000)
        ->and($leg->fresh()->status)->toBe('completed')
        ->and($leg->fresh()->cost_accrued_at)->not->toBeNull();

    $clearingBefore = cbal(LogisticsLedger::BANK_CLEARING);
    $this->travel(7)->days();
    $payment = app(PayCarriersAction::class)->execute($this->carrier, $this->world->admin->id);

    expect($payment->amount_idr)->toBe(8_000)
        ->and($payment->leg_count)->toBe(1)
        ->and(cbal(LogisticsLedger::carrierPayableCode($this->carrier->id)))->toBe(0)
        ->and(cbal(LogisticsLedger::BANK_CLEARING))->toBe($clearingBefore + 8_000)
        ->and($leg->fresh()->carrier_payment_id)->toBe($payment->id);
});

test('(b) accrual, completion and payment runs are idempotent', function () {
    $leg = makeLeg($this->world->bookPrepaid($this->world->shipper()), $this->world);
    app(AssignCarrierToLegAction::class)->execute($leg, $this->carrier, 5_000);

    app(CompleteShipmentLegAction::class)->execute($leg);
    app(CompleteShipmentLegAction::class)->execute($leg);

    expect(app(AccrueLegCostAction::class)->execute($leg->fresh()))->toBeFalse()
        ->and(LedgerTransaction::where('idempotency_key', "lgx:leg_accrual:{$leg->id}")->count())->toBe(1)
        ->and(cbal(LogisticsLedger::carrierPayableCode($this->carrier->id)))->toBe(5_000);

    $this->travel(8)->days();
    $this->artisan('lgx:pay-carriers')->expectsOutputToContain('1 carrier dibayar')->assertSuccessful();
    $this->artisan('lgx:pay-carriers')->expectsOutputToContain('0 carrier dibayar')->assertSuccessful();

    expect(CarrierPayment::count())->toBe(1);
});

test('(c) ledger reconciles after accruals and payments', function () {
    $shipment = $this->world->bookPrepaid($this->world->shipper());
    foreach ([1 => 4_000, 2 => 6_500] as $seq => $cost) {
        $leg = makeLeg($shipment, $this->world, $seq);
        app(AssignCarrierToLegAction::class)->execute($leg, $this->carrier, $cost);
        app(CompleteShipmentLegAction::class)->execute($leg);
    }
    $this->travel(7)->days();
    app(PayCarriersAction::class)->execute($this->carrier);

    $this->artisan('bank:reconcile')->assertSuccessful();
    expect(CarrierPayment::first()->amount_idr)->toBe(10_500);
});

test('(d) invalid costs, inactive carriers, closed legs and early payment are rejected', function () {
    $leg = makeLeg($this->world->bookPrepaid($this->world->shipper()), $this->world);
    $assign = app(AssignCarrierToLegAction::class);

    expect(fn () => $assign->execute($leg, $this->carrier, 0))->toThrow(CarrierException::class, 'lebih dari nol');

    $this->carrier->update(['is_active' => false]);
    expect(fn () => $assign->execute($leg, $this->carrier, 1_000))->toThrow(CarrierException::class, 'tidak aktif');
    $this->carrier->update(['is_active' => true]);

    $assign->execute($leg, $this->carrier, 1_000);
    app(CompleteShipmentLegAction::class)->execute($leg);
    expect(fn () => $assign->execute($leg->fresh(), $this->carrier, 2_000))->toThrow(CarrierException::class, 'sudah selesai');

    // belum jatuh tempo (termin 7 hari)
    expect(app(PayCarriersAction::class)->execute($this->carrier))->toBeNull()
        ->and(cbal(LogisticsLedger::carrierPayableCode($this->carrier->id)))->toBe(1_000);
});

test('a leg without carrier completes without any accrual', function () {
    $leg = makeLeg($this->world->bookPrepaid($this->world->shipper()), $this->world);

    $done = app(CompleteShipmentLegAction::class)->execute($leg);

    expect($done->status)->toBe('completed')->and($done->cost_accrued_at)->toBeNull()->and(cbal(LogisticsLedger::CARRIER_COST))->toBe(0);
});

test('margin report subtracts accrued carrier cost from recognized revenue', function () {
    $shipment = $this->world->bookPrepaid($this->world->shipper());
    $leg = makeLeg($shipment, $this->world);
    app(AssignCarrierToLegAction::class)->execute($leg, $this->carrier, 6_000);

    $report = app(ShipmentMarginReport::class);
    expect($report->forShipment($shipment->fresh())['revenue_idr'])->toBe(0);

    app(CompleteShipmentLegAction::class)->execute($leg);
    $this->world->deliver($shipment->fresh());
    $row = $report->forShipment($shipment->fresh());

    expect($row['revenue_idr'])->toBe($shipment->total_amount_idr)
        ->and($row['carrier_cost_idr'])->toBe(6_000)
        ->and($row['margin_idr'])->toBe($shipment->total_amount_idr - 6_000)
        ->and($row['margin_pct'])->toBeFloat();
});

test('(e) only admins manage carriers while dispatchers may complete legs and nobody else gets in', function () {
    $leg = makeLeg($this->world->bookPrepaid($this->world->shipper()), $this->world);
    $payload = ['code' => 'CRR-BETA', 'name' => 'Beta', 'payment_terms_days' => 3];

    $this->actingAs($this->world->dispatcher)->get(route('logistics.carriers.index'))->assertOk()->assertSee('PT Alfa Trans');
    $this->actingAs($this->world->dispatcher)->post(route('logistics.carriers.store'), $payload)->assertForbidden();
    $this->actingAs($this->world->dispatcher)->post(route('logistics.carriers.pay', $this->carrier))->assertForbidden();
    $this->actingAs($this->world->dispatcher)->post(route('logistics.legs.assign-carrier', $leg->id), ['carrier_id' => $this->carrier->id, 'cost_idr' => 100])->assertForbidden();
    $this->actingAs($this->world->dispatcher)->post(route('logistics.legs.complete', $leg->id))->assertSessionHas('success');

    $this->actingAs($this->world->admin)->post(route('logistics.carriers.store'), $payload)->assertSessionHas('success');
    expect(Carrier::where('code', 'CRR-BETA')->exists())->toBeTrue();
    $this->actingAs($this->world->admin)->get(route('logistics.margins'))->assertOk();

    foreach (['shipper', 'driver', 'hub_operator'] as $role) {
        $this->actingAs($this->world->user($role))->get(route('logistics.carriers.index'))->assertForbidden();
        $this->actingAs($this->world->user($role))->get(route('logistics.margins'))->assertForbidden();
    }
});
