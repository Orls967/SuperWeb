<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Banking\Domain\Models\LedgerTransaction;
use Modules\Logistics\Application\Actions\AccrueDemurrageDetentionAction;
use Modules\Logistics\Application\Actions\EndContainerDwellAction;
use Modules\Logistics\Application\Actions\GenerateDdInvoicesAction;
use Modules\Logistics\Application\Actions\GenerateMonthlyInvoicesAction;
use Modules\Logistics\Application\Actions\PayLogisticsInvoiceAction;
use Modules\Logistics\Application\Actions\StartContainerDwellAction;
use Modules\Logistics\Application\Services\LogisticsLedger;
use Modules\Logistics\Domain\Exceptions\DemurrageException;
use Modules\Logistics\Domain\Models\Container;
use Modules\Logistics\Domain\Models\ContainerDwell;
use Modules\Logistics\Domain\Models\DdTariff;
use Modules\Logistics\Domain\Services\DwellChargeCalculator;
use Modules\Logistics\Domain\ValueObjects\Iso6346Validator;
use Modules\Logistics\tests\Support\MoneyFlowWorld;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->world = new MoneyFlowWorld;
    $this->shipper = $this->world->shipper(5_000_000, true);
    $this->shipment = $this->world->bookPostpaid($this->shipper);
    $this->container = Container::create([
        'container_number' => Iso6346Validator::generate('CSQ', 'U', 305438), 'size_type' => '22G1', 'tare_kg' => 2200, 'max_gross_kg' => 30480, 'status' => 'available',
    ]);
    $this->tariff = DdTariff::create([
        'kind' => 'demurrage', 'size_type' => null, 'location_id' => null, 'free_days' => 3, 'rate_per_day_idr' => 100_000,
        'escalation_after_days' => 2, 'escalated_rate_per_day_idr' => 150_000, 'is_active' => true,
    ]);
    // Hub A berzona waktu WITA (UTC+8).
    $this->travelTo(Carbon\Carbon::parse('2026-10-01 02:00:00', 'UTC')); // 10:00 WITA, 1 Okt
});

function ddBal(string $code): int
{
    return (int) (LedgerAccount::where('code', $code)->value('cached_balance') ?? 0);
}

test('calculator counts calendar days in the location timezone, applies free time and escalation', function () {
    $calc = new DwellChargeCalculator;
    $tariff = $this->tariff;

    // 10:00 UTC (18:00 WITA, 1 Okt) -> 17:00 UTC (01:00 WITA, 2 Okt): 1 hari UTC, 2 hari WITA
    $start = Carbon\Carbon::parse('2026-10-01 10:00:00', 'UTC');
    $end = Carbon\Carbon::parse('2026-10-01 17:00:00', 'UTC');
    expect($calc->daysElapsed($start, $end, 'UTC'))->toBe(1)->and($calc->daysElapsed($start, $end, 'Asia/Makassar'))->toBe(2);

    expect($calc->billableDays(3, $tariff))->toBe(0)->and($calc->billableDays(4, $tariff))->toBe(1)
        ->and($calc->amountFor(0, $tariff))->toBe(0)
        ->and($calc->amountFor(1, $tariff))->toBe(100_000)
        ->and($calc->amountFor(2, $tariff))->toBe(200_000)
        ->and($calc->amountFor(4, $tariff))->toBe(500_000);
});

test('(a) accrual books receivable against dd revenue and the invoice is payable through the ar account', function () {
    $dwell = app(StartContainerDwellAction::class)->execute($this->container, $this->shipment, $this->world->hubA, 'demurrage');
    expect($dwell->tariff_id)->toBe($this->tariff->id);

    $this->travelTo(Carbon\Carbon::parse('2026-10-06 02:00:00', 'UTC')); // hari ke-6 => 3 hari berbayar
    $this->artisan('lgx:accrue-dd')->expectsOutputToContain('Rp 350.000')->assertSuccessful();

    $ar = LogisticsLedger::arCode($this->shipper->id);
    $arBeforeInvoice = ddBal($ar);
    expect($dwell->fresh()->billable_days)->toBe(3)->and($dwell->fresh()->accrued_amount_idr)->toBe(350_000)
        ->and(ddBal(LogisticsLedger::DD_REVENUE))->toBe(350_000);

    app(EndContainerDwellAction::class)->execute($dwell->fresh());
    $invoice = app(GenerateDdInvoicesAction::class)->execute()->first();

    expect($invoice->kind)->toBe('dd')->and($invoice->total_amount_idr)->toBe(350_000)->and($dwell->fresh()->invoice_id)->toBe($invoice->id);

    app(PayLogisticsInvoiceAction::class)->execute($this->shipper, $invoice, '123456');

    expect($invoice->fresh()->status)->toBe('paid')->and(ddBal($ar))->toBe($arBeforeInvoice + 350_000);
});

test('accrual is incremental per day and zero while inside free time', function () {
    $dwell = app(StartContainerDwellAction::class)->execute($this->container, $this->shipment, $this->world->hubA, 'demurrage');
    $accrue = app(AccrueDemurrageDetentionAction::class);

    $this->travelTo(Carbon\Carbon::parse('2026-10-03 02:00:00', 'UTC')); // hari ke-3, masih free
    expect($accrue->execute($dwell))->toBe(0)->and(ddBal(LogisticsLedger::DD_REVENUE))->toBe(0);

    $this->travelTo(Carbon\Carbon::parse('2026-10-04 02:00:00', 'UTC')); // hari ke-4
    expect($accrue->execute($dwell->fresh()))->toBe(100_000);
    $this->travelTo(Carbon\Carbon::parse('2026-10-05 02:00:00', 'UTC'));
    expect($accrue->execute($dwell->fresh()))->toBe(100_000);
    $this->travelTo(Carbon\Carbon::parse('2026-10-06 02:00:00', 'UTC'));
    expect($accrue->execute($dwell->fresh()))->toBe(150_000)->and(ddBal(LogisticsLedger::DD_REVENUE))->toBe(350_000);
});

test('(b) running the accrual again on the same day posts nothing new', function () {
    $dwell = app(StartContainerDwellAction::class)->execute($this->container, $this->shipment, $this->world->hubA, 'demurrage');
    $this->travelTo(Carbon\Carbon::parse('2026-10-06 02:00:00', 'UTC'));

    $this->artisan('lgx:accrue-dd')->assertSuccessful();
    $count = LedgerTransaction::where('type', 'lgx_dd_accrual')->count();
    $this->artisan('lgx:accrue-dd')->expectsOutputToContain('tambahan Rp 0')->assertSuccessful();

    expect(LedgerTransaction::where('type', 'lgx_dd_accrual')->count())->toBe($count)->and($dwell->fresh()->accrued_amount_idr)->toBe(350_000);
});

test('(c) ledger reconciles after accrual, closing and invoice payment', function () {
    $dwell = app(StartContainerDwellAction::class)->execute($this->container, $this->shipment, $this->world->hubA, 'demurrage');
    $this->travelTo(Carbon\Carbon::parse('2026-10-08 02:00:00', 'UTC'));
    app(EndContainerDwellAction::class)->execute($dwell);
    $invoice = app(GenerateDdInvoicesAction::class)->execute()->first();
    app(PayLogisticsInvoiceAction::class)->execute($this->shipper, $invoice, '123456');

    $this->artisan('bank:reconcile')->assertSuccessful();
});

test('(d) missing tariff, duplicate open dwell, closed dwell and bad end time are rejected', function () {
    $start = app(StartContainerDwellAction::class);
    $end = app(EndContainerDwellAction::class);

    expect(fn () => $start->execute($this->container, $this->shipment, $this->world->hubA, 'detention'))->toThrow(DemurrageException::class, 'belum dikonfigurasi');

    $dwell = $start->execute($this->container, $this->shipment, $this->world->hubA, 'demurrage');
    expect(fn () => $start->execute($this->container, $this->shipment, $this->world->hubA, 'demurrage'))->toThrow(DemurrageException::class, 'sudah memiliki');
    expect(fn () => $end->execute($dwell, now()->subDay()))->toThrow(DemurrageException::class, 'lebih awal');

    $end->execute($dwell);
    expect(fn () => $end->execute($dwell->fresh()))->toThrow(DemurrageException::class, 'sudah ditutup');
    expect(app(GenerateDdInvoicesAction::class)->execute())->toHaveCount(0); // dalam free time: tidak ada tagihan
});

test('the most specific tariff wins', function () {
    $generic = $this->tariff;
    $port = DdTariff::create(['kind' => 'demurrage', 'location_id' => $this->world->hubA->id, 'free_days' => 1, 'rate_per_day_idr' => 300_000, 'is_active' => true]);
    $portSize = DdTariff::create(['kind' => 'demurrage', 'location_id' => $this->world->hubA->id, 'size_type' => '22G1', 'free_days' => 2, 'rate_per_day_idr' => 250_000, 'is_active' => true]);

    $resolved = app(StartContainerDwellAction::class)->resolveTariff('demurrage', $this->world->hubA, '22G1');
    expect($resolved->id)->toBe($portSize->id)
        ->and(app(StartContainerDwellAction::class)->resolveTariff('demurrage', $this->world->hubA, '45G1')->id)->toBe($port->id)
        ->and(app(StartContainerDwellAction::class)->resolveTariff('demurrage', $this->world->hubB, '45G1')->id)->toBe($generic->id);
});

test('a dd invoice does not block the monthly freight invoice of the same period', function () {
    $dwell = app(StartContainerDwellAction::class)->execute($this->container, $this->shipment, $this->world->hubA, 'demurrage');
    $this->travelTo(Carbon\Carbon::parse('2026-10-08 02:00:00', 'UTC'));
    app(EndContainerDwellAction::class)->execute($dwell);
    app(GenerateDdInvoicesAction::class)->execute();

    $freight = app(GenerateMonthlyInvoicesAction::class)->execute('2026-10');

    expect($freight)->toHaveCount(1)->and($freight->first()->fresh()->kind)->toBe('freight');
});

test('(e) dispatcher can view dd data but only admins manage tariffs, dwells and invoicing', function () {
    $this->actingAs($this->world->dispatcher)->get(route('logistics.dd.index'))->assertOk();
    $this->actingAs($this->world->dispatcher)->post(route('logistics.dd.tariffs.store'), ['kind' => 'demurrage', 'free_days' => 1, 'rate_per_day_idr' => 1000])->assertForbidden();
    $this->actingAs($this->world->dispatcher)->post(route('logistics.dd.invoice'))->assertForbidden();

    foreach (['shipper', 'driver', 'hub_operator'] as $role) {
        $this->actingAs($this->world->user($role))->get(route('logistics.dd.index'))->assertForbidden();
    }

    $this->actingAs($this->world->admin)->post(route('logistics.dd.tariffs.store'), ['kind' => 'detention', 'free_days' => 5, 'rate_per_day_idr' => 75_000])->assertSessionHas('success');
    $this->actingAs($this->world->admin)->post(route('logistics.dd.start'), [
        'container_number' => $this->container->container_number, 'tracking_number' => $this->shipment->tracking_number,
        'location_id' => $this->world->hubA->id, 'kind' => 'demurrage',
    ])->assertSessionHas('success');
    $dwell = ContainerDwell::firstOrFail();
    $this->actingAs($this->world->admin)->post(route('logistics.dd.end', $dwell->id))->assertSessionHas('success');
    expect($dwell->fresh()->status)->toBe('closed');
});
