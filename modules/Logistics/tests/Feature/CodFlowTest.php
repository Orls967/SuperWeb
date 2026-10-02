<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Banking\Domain\Models\LedgerTransaction;
use Modules\Logistics\Application\Actions\DepositCodCashAction;
use Modules\Logistics\Application\Actions\SettleCodAction;
use Modules\Logistics\Application\Services\LogisticsLedger;
use Modules\Logistics\Domain\Enums\ShipmentStatus;
use Modules\Logistics\Domain\Exceptions\CodException;
use Modules\Logistics\Domain\Models\CodCollection;
use Modules\Logistics\Domain\Models\HubOperator;
use Modules\Logistics\Domain\Services\CodFeeCalculator;
use Modules\Logistics\tests\Support\MoneyFlowWorld;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->world = new MoneyFlowWorld;
    $this->shipper = $this->world->shipper();
    HubOperator::create(['user_id' => $this->world->hubOperator->id, 'hub_id' => $this->world->hubA->id]);
});

function codBal(string $code): int
{
    return (int) (LedgerAccount::where('code', $code)->value('cached_balance') ?? 0);
}

test('(a) cod cash flows from driver to hub to shipper minus the cod fee', function () {
    $driver = $this->world->driver();
    $shipment = $this->world->bookPrepaid($this->shipper, cod: 250_000);
    $walletBefore = (int) $this->shipper->walletAccount('IDR')->fresh()->cached_balance;

    $this->world->deliver($shipment, $driver);

    $collection = CodCollection::firstOrFail();
    expect($collection->status)->toBe('collected')
        ->and(codBal(LogisticsLedger::driverCashCode($driver->id)))->toBe(-250_000)
        ->and(codBal(LogisticsLedger::codPayableCode($this->shipper->id)))->toBe(250_000);

    $result = app(DepositCodCashAction::class)->execute($this->world->hubOperator, $this->world->hubA, $driver, 250_000);
    expect($result)->toBe(['collections' => 1, 'amount' => 250_000])
        ->and(codBal(LogisticsLedger::driverCashCode($driver->id)))->toBe(0)
        ->and(codBal(LogisticsLedger::hubCashCode($this->world->hubA->id)))->toBe(-250_000)
        ->and($collection->fresh()->status)->toBe('deposited');

    expect(app(SettleCodAction::class)->execute($collection))->toBeTrue();

    expect($collection->fresh()->fee_idr)->toBe(7_500)
        ->and($collection->fresh()->net_amount_idr)->toBe(242_500)
        ->and(codBal(LogisticsLedger::codPayableCode($this->shipper->id)))->toBe(0)
        ->and(codBal(LogisticsLedger::COD_FEE_REVENUE))->toBe(7_500)
        ->and((int) $this->shipper->walletAccount('IDR')->fresh()->cached_balance)->toBe($walletBefore + 242_500);
});

test('fee calculator applies rate, minimum and never exceeds the cod amount', function () {
    $calc = new CodFeeCalculator;

    expect($calc->feeFor(1_000_000))->toBe(30_000)
        ->and($calc->feeFor(100_000))->toBe(5_000)
        ->and($calc->feeFor(3_000))->toBe(3_000)
        ->and($calc->feeFor(0))->toBe(0);
});

test('(b) settlement, deposit and collection are idempotent', function () {
    $driver = $this->world->driver();
    $this->world->deliver($this->world->bookPrepaid($this->shipper, cod: 100_000), $driver);
    $collection = CodCollection::firstOrFail();
    app(DepositCodCashAction::class)->execute($this->world->hubOperator, $this->world->hubA, $driver, 100_000);

    expect(app(SettleCodAction::class)->execute($collection))->toBeTrue()
        ->and(app(SettleCodAction::class)->execute($collection->fresh()))->toBeFalse()
        ->and(codBal(LogisticsLedger::COD_FEE_REVENUE))->toBe(5_000);

    expect(fn () => app(DepositCodCashAction::class)->execute($this->world->hubOperator, $this->world->hubA, $driver, 100_000))
        ->toThrow(CodException::class, 'tidak memiliki uang COD');
});

test('settle command only pays collections deposited at least N days ago and is idempotent', function () {
    $driver = $this->world->driver();
    $this->world->deliver($this->world->bookPrepaid($this->shipper, cod: 200_000), $driver);
    app(DepositCodCashAction::class)->execute($this->world->hubOperator, $this->world->hubA, $driver, 200_000);

    $this->artisan('lgx:settle-cod')->expectsOutputToContain('0 resi')->assertSuccessful();
    expect(CodCollection::first()->status)->toBe('deposited');

    $this->travel(2)->days();
    $this->artisan('lgx:settle-cod')->expectsOutputToContain('1 resi')->assertSuccessful();
    $this->artisan('lgx:settle-cod')->expectsOutputToContain('0 resi')->assertSuccessful();

    expect(CodCollection::first()->status)->toBe('settled')->and(CodCollection::first()->fee_idr)->toBe(6_000);
});

test('(c) ledger reconciles to zero across the whole cod cycle', function () {
    $driver = $this->world->driver();
    $this->world->deliver($this->world->bookPrepaid($this->shipper, cod: 321_000), $driver);
    $this->world->deliver($this->world->bookPrepaid($this->shipper, cod: 87_500), $driver);
    app(DepositCodCashAction::class)->execute($this->world->hubOperator, $this->world->hubA, $driver, 408_500);
    $this->travel(3)->days();
    $this->artisan('lgx:settle-cod')->assertSuccessful();

    $this->artisan('bank:reconcile')->assertSuccessful();
    expect(codBal(LogisticsLedger::codPayableCode($this->shipper->id)))->toBe(0);
});

test('(d) delivery of a cod shipment requires cash confirmation and mismatched deposits post nothing', function () {
    $driver = $this->world->driver();
    $shipment = $this->world->bookPrepaid($this->shipper, cod: 150_000);

    expect(fn () => $this->world->deliver($shipment, $driver, codCollected: false))->toThrow(CodException::class, 'konfirmasi');
    expect($shipment->fresh()->status)->toBe(ShipmentStatus::OutForDelivery)->and(CodCollection::count())->toBe(0);

    $this->world->deliver($shipment->fresh(), $driver);
    $txBefore = LedgerTransaction::count();

    expect(fn () => app(DepositCodCashAction::class)->execute($this->world->hubOperator, $this->world->hubA, $driver, 140_000))
        ->toThrow(CodException::class, 'tidak sesuai');
    expect(LedgerTransaction::count())->toBe($txBefore)
        ->and(CodCollection::first()->status)->toBe('collected');
});

test('(e) only the receiving hub operator or admins may take a deposit, and the dashboard is staff only', function () {
    $driver = $this->world->driver();
    $this->world->deliver($this->world->bookPrepaid($this->shipper, cod: 100_000), $driver);
    $otherHubOperator = $this->world->user('hub_operator');
    HubOperator::create(['user_id' => $otherHubOperator->id, 'hub_id' => $this->world->hubB->id]);

    expect(fn () => app(DepositCodCashAction::class)->execute($otherHubOperator, $this->world->hubA, $driver, 100_000))
        ->toThrow(CodException::class, 'hub penugasan');

    $this->actingAs($this->shipper)->get(route('logistics.cod.index'))->assertForbidden();
    $this->actingAs($driver->user)->get(route('logistics.cod.index'))->assertForbidden();
    $this->actingAs($this->world->dispatcher)->post(route('logistics.cod.deposit'), ['driver_id' => $driver->id, 'hub_id' => $this->world->hubA->id, 'amount_idr' => 100_000])->assertForbidden();

    $this->actingAs($this->world->hubOperator)->get(route('logistics.cod.index'))->assertOk()->assertSee('Rp 100.000');
    $this->actingAs($this->world->hubOperator)
        ->post(route('logistics.cod.deposit'), ['driver_id' => $driver->id, 'hub_id' => $this->world->hubA->id, 'amount_idr' => 100_000])
        ->assertSessionHas('success');
    expect(CodCollection::first()->status)->toBe('deposited');
});
