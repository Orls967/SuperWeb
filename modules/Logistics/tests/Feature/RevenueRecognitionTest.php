<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Banking\Domain\Models\LedgerTransaction;
use Modules\Logistics\Application\Actions\CancelShipmentAction;
use Modules\Logistics\Application\Actions\CompleteDeliveryAction;
use Modules\Logistics\Application\Actions\GenerateMonthlyInvoicesAction;
use Modules\Logistics\Application\Actions\PayLogisticsInvoiceAction;
use Modules\Logistics\Application\Actions\RecognizeFreightRevenueAction;
use Modules\Logistics\Application\Actions\StartDeliveryAction;
use Modules\Logistics\Application\Services\LogisticsLedger;
use Modules\Logistics\Domain\Enums\ShipmentStatus;
use Modules\Logistics\Domain\Events\ShipmentDelivered;
use Modules\Logistics\Domain\Exceptions\InvalidDeliveryOperationException;
use Modules\Logistics\tests\Support\MoneyFlowWorld;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->world = new MoneyFlowWorld;
    $this->ledger = app(LogisticsLedger::class);
});

function lgxBal(string $code): int
{
    return (int) (LedgerAccount::where('code', $code)->value('cached_balance') ?? 0);
}

test('(a) prepaid delivery moves the booking liability into freight revenue', function () {
    $shipper = $this->world->shipper();
    $shipment = $this->world->bookPrepaid($shipper);
    $total = $shipment->total_amount_idr;

    expect(lgxBal(LogisticsLedger::UNEARNED_FREIGHT))->toBe($total)->and(lgxBal(LogisticsLedger::FREIGHT_REVENUE))->toBe(0);

    $this->world->deliver($shipment);

    expect(lgxBal(LogisticsLedger::UNEARNED_FREIGHT))->toBe(0)
        ->and(lgxBal(LogisticsLedger::FREIGHT_REVENUE))->toBe($total)
        ->and($shipment->fresh()->revenue_recognized_at)->not->toBeNull()
        ->and($shipment->fresh()->status)->toBe(ShipmentStatus::Delivered);
});

test('(a) postpaid delivery books receivable against revenue and invoice payment clears it', function () {
    $shipper = $this->world->shipper(5_000_000, true);
    $shipment = $this->world->bookPostpaid($shipper);
    $total = $shipment->total_amount_idr;
    $ar = LogisticsLedger::arCode($shipper->id);

    $this->world->deliver($shipment);

    expect(lgxBal($ar))->toBe(-$total)->and(lgxBal(LogisticsLedger::FREIGHT_REVENUE))->toBe($total);

    $invoice = app(GenerateMonthlyInvoicesAction::class)->execute(now()->format('Y-m'))->first();
    app(PayLogisticsInvoiceAction::class)->execute($shipper, $invoice, '123456');

    expect(lgxBal($ar))->toBe(0)->and($invoice->fresh()->status)->toBe('paid');
});

test('(b) recognition is idempotent per shipment', function () {
    $shipment = $this->world->bookPrepaid($this->world->shipper());
    $this->world->deliver($shipment);
    $action = app(RecognizeFreightRevenueAction::class);

    expect($action->execute($shipment->fresh()))->toBeFalse()->and($action->execute($shipment->fresh()))->toBeFalse();
    event(new ShipmentDelivered($shipment->fresh()));

    expect(LedgerTransaction::where('idempotency_key', "lgx:revenue:{$shipment->id}")->count())->toBe(1)
        ->and(lgxBal(LogisticsLedger::FREIGHT_REVENUE))->toBe($shipment->total_amount_idr);
});

test('(c) ledger stays balanced after recognition', function () {
    $this->world->deliver($this->world->bookPrepaid($this->world->shipper()));
    $this->world->deliver($this->world->bookPostpaid($this->world->shipper(5_000_000, true)));

    $this->artisan('bank:reconcile')->assertSuccessful();
});

test('(d) revenue cannot be recognized before delivery or for cancelled shipments', function () {
    $shipper = $this->world->shipper();
    $booked = $this->world->bookPrepaid($shipper);

    expect(fn () => app(RecognizeFreightRevenueAction::class)->execute($booked))->toThrow(InvalidDeliveryOperationException::class);

    app(CancelShipmentAction::class)->execute($shipper, $booked);

    expect(fn () => app(RecognizeFreightRevenueAction::class)->execute($booked->fresh()))->toThrow(InvalidDeliveryOperationException::class)
        ->and(lgxBal(LogisticsLedger::FREIGHT_REVENUE))->toBe(min($booked->total_amount_idr, config('logistics.cancellation_fee_idr')));
});

test('(e) a driver that is not assigned cannot deliver, so no revenue is recognized', function () {
    $shipment = $this->world->bookPrepaid($this->world->shipper());
    $assigned = $this->world->driver();
    $intruder = $this->world->driver();
    $shipment->update(['status' => ShipmentStatus::AtHub, 'driver_id' => $assigned->id]);
    app(StartDeliveryAction::class)->execute($assigned, $shipment->fresh());

    expect(fn () => app(CompleteDeliveryAction::class)->execute(
        $intruder, $shipment->fresh(), 'X', '123456', UploadedFile::fake()->image('p.jpg'), 'data:image/png;base64,AAAA'
    ))->toThrow(InvalidDeliveryOperationException::class);

    expect($shipment->fresh()->revenue_recognized_at)->toBeNull()->and(lgxBal(LogisticsLedger::FREIGHT_REVENUE))->toBe(0);
});

test('quote now carries cod, declared value and insurance into the shipment', function () {
    $shipment = $this->world->bookPrepaid($this->world->shipper(), cod: 250_000, insured: true, declared: 1_000_000);

    expect($shipment->cod_amount_idr)->toBe(250_000)->and($shipment->insured)->toBeTrue()->and($shipment->declared_value_idr)->toBe(1_000_000);
});
