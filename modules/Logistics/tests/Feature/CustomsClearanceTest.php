<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Banking\Domain\Exceptions\InvalidPinException;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Banking\Domain\Models\LedgerTransaction;
use Modules\Logistics\Application\Actions\ClearCustomsAction;
use Modules\Logistics\Application\Actions\PayCustomsDutyAction;
use Modules\Logistics\Application\Actions\SubmitCustomsDeclarationAction;
use Modules\Logistics\Application\Services\LogisticsLedger;
use Modules\Logistics\Domain\Enums\ExceptionType;
use Modules\Logistics\Domain\Enums\ShipmentStatus;
use Modules\Logistics\Domain\Exceptions\CustomsException;
use Modules\Logistics\Domain\Models\CustomsDeclaration;
use Modules\Logistics\Domain\Models\HsTariff;
use Modules\Logistics\Domain\Models\ShipmentException;
use Modules\Logistics\Domain\Models\TrackingEvent;
use Modules\Logistics\Domain\Services\CustomsDutyCalculator;
use Modules\Logistics\tests\Support\MoneyFlowWorld;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->world = new MoneyFlowWorld;
    $this->shipper = $this->world->shipper(40_000_000);
    $this->shipment = $this->world->bookPrepaid($this->shipper);
    $this->shipment->update(['status' => ShipmentStatus::InTransit]);
    $this->hs = HsTariff::create(['hs_code' => '85171300', 'description' => 'Smartphone', 'bm_bp' => 500, 'ppn_bp' => 1100, 'pph22_api_bp' => 250, 'pph22_non_api_bp' => 750]);
    $this->lartas = HsTariff::create(['hs_code' => '22030000', 'description' => 'Bir (lartas)', 'bm_bp' => 1000, 'ppn_bp' => 1100, 'pph22_api_bp' => 250, 'pph22_non_api_bp' => 750, 'requires_inspection' => true]);
});

function customsBal(string $code): int
{
    return (int) (LedgerAccount::where('code', $code)->value('cached_balance') ?? 0);
}

function submitPib(object $t, array $lines = [['hs_code' => '85171300', 'value_idr' => 100_000_000]], bool $api = true, string $type = 'PIB', $by = null): CustomsDeclaration
{
    return app(SubmitCustomsDeclarationAction::class)->execute($by ?? $t->shipper, $t->shipment->fresh(), $type, $lines, $api);
}

test('duty calculator follows bea masuk, ppn and pph 22 simulation formulas', function () {
    $calc = new CustomsDutyCalculator;

    expect($calc->forLine(100_000_000, $this->hs, true))->toBe(['bm' => 5_000_000, 'ppn' => 11_550_000, 'pph22' => 2_625_000, 'total' => 19_175_000])
        ->and($calc->forLine(100_000_000, $this->hs, false)['pph22'])->toBe(7_875_000)
        ->and($calc->forLine(333, $this->hs, true))->toBe(['bm' => 17, 'ppn' => 39, 'pph22' => 9, 'total' => 65]);
});

test('(a) green lane: submit, pay duty from the wallet, then clear', function () {
    $declaration = submitPib($this);

    expect($declaration->lane)->toBe('green')->and($declaration->status)->toBe('submitted')
        ->and($declaration->total_duty_idr)->toBe(19_175_000)->and($declaration->declaration_number)->toStartWith('PIB-')
        ->and($this->shipment->fresh()->status)->toBe(ShipmentStatus::InTransit);

    $walletBefore = (int) $this->shipper->walletAccount('IDR')->fresh()->cached_balance;
    app(PayCustomsDutyAction::class)->execute($this->shipper, $declaration, '123456');

    expect((int) $this->shipper->walletAccount('IDR')->fresh()->cached_balance)->toBe($walletBefore - 19_175_000)
        ->and(customsBal(LogisticsLedger::CUSTOMS_DUTY_PAYABLE))->toBe(19_175_000);

    $cleared = app(ClearCustomsAction::class)->execute($this->world->admin, $declaration->fresh());
    expect($cleared->status)->toBe('cleared')->and($cleared->cleared_by)->toBe($this->world->admin->id);
});

test('(a) red lane holds the shipment and clearance resumes it and closes the exception', function () {
    $declaration = submitPib($this, [['hs_code' => '22030000', 'value_idr' => 10_000_000]], false);

    expect($declaration->lane)->toBe('red')->and($declaration->status)->toBe('on_hold')->and($declaration->hold_reason)->toContain('lartas')
        ->and($this->shipment->fresh()->status)->toBe(ShipmentStatus::CustomsHold)
        ->and(TrackingEvent::where('shipment_id', $this->shipment->id)->where('event_type', 'CUSTOMS_HOLD')->exists())->toBeTrue();

    $exception = ShipmentException::where('type', ExceptionType::CustomsHold->value)->firstOrFail();
    expect($exception->status)->toBe('open');

    app(PayCustomsDutyAction::class)->execute($this->shipper, $declaration, '123456');
    app(ClearCustomsAction::class)->execute($this->world->admin, $declaration->fresh());

    expect($this->shipment->fresh()->status)->toBe(ShipmentStatus::InTransit)
        ->and($exception->fresh()->status)->toBe('resolved')
        ->and(TrackingEvent::where('shipment_id', $this->shipment->id)->orderByDesc('sequence')->value('event_type'))->toBe('CUSTOMS_CLEARED');
});

test('a very high customs value goes to the red lane even without restricted goods', function () {
    $declaration = submitPib($this, [['hs_code' => '85171300', 'value_idr' => 500_000_000]]);

    expect($declaration->lane)->toBe('red')->and($declaration->hold_reason)->toContain('ambang')->and($this->shipment->fresh()->status)->toBe(ShipmentStatus::CustomsHold);
});

test('export declarations carry no duty and clear without payment ledger entries', function () {
    $declaration = submitPib($this, [['hs_code' => '85171300', 'value_idr' => 50_000_000]], true, 'PEB');
    $txBefore = LedgerTransaction::count();

    app(PayCustomsDutyAction::class)->execute($this->shipper, $declaration, '123456');
    app(ClearCustomsAction::class)->execute($this->world->admin, $declaration->fresh());

    expect($declaration->total_duty_idr)->toBe(0)->and(LedgerTransaction::count())->toBe($txBefore)->and($declaration->fresh()->status)->toBe('cleared');
});

test('(b) paying twice debits once and a second open declaration for the same shipment is rejected', function () {
    $declaration = submitPib($this);
    $pay = app(PayCustomsDutyAction::class);
    $pay->execute($this->shipper, $declaration, '123456');
    $pay->execute($this->shipper, $declaration->fresh(), '123456');

    expect(LedgerTransaction::where('idempotency_key', "lgx:customs_pay:{$declaration->id}")->count())->toBe(1)
        ->and(customsBal(LogisticsLedger::CUSTOMS_DUTY_PAYABLE))->toBe(19_175_000);
    expect(fn () => submitPib($this))->toThrow(CustomsException::class, 'sudah memiliki dokumen');
});

test('(c) ledger reconciles after duty payments', function () {
    $declaration = submitPib($this);
    app(PayCustomsDutyAction::class)->execute($this->shipper, $declaration, '123456');

    $this->artisan('bank:reconcile')->assertSuccessful();
});

test('(d) unknown hs codes, bad lines, unpaid clearance, wrong pin and ineligible shipments are rejected', function () {
    expect(fn () => submitPib($this, [['hs_code' => '99999999', 'value_idr' => 1_000]]))->toThrow(CustomsException::class, 'tidak ada dalam tabel')
        ->and(fn () => submitPib($this, [['hs_code' => '123', 'value_idr' => 1_000]]))->toThrow(CustomsException::class, 'Minimal satu baris')
        ->and(fn () => submitPib($this, [['hs_code' => '85171300', 'value_idr' => 0]]))->toThrow(CustomsException::class, 'Minimal satu baris')
        ->and(fn () => submitPib($this, []))->toThrow(CustomsException::class);

    $declaration = submitPib($this);
    expect(fn () => app(ClearCustomsAction::class)->execute($this->world->admin, $declaration))->toThrow(CustomsException::class, 'belum dibayar');
    expect(fn () => app(PayCustomsDutyAction::class)->execute($this->shipper, $declaration, '000000'))->toThrow(InvalidPinException::class);
    expect($declaration->fresh()->paid_at)->toBeNull();

    app(PayCustomsDutyAction::class)->execute($this->shipper, $declaration, '123456');
    app(ClearCustomsAction::class)->execute($this->world->admin, $declaration->fresh());
    expect(fn () => app(ClearCustomsAction::class)->execute($this->world->admin, $declaration->fresh()))->toThrow(CustomsException::class, 'membutuhkan status');

    $this->shipment->update(['status' => ShipmentStatus::Delivered]);
    expect(fn () => submitPib($this))->toThrow(CustomsException::class, 'tidak dapat diproses');
});

test('(e) only the owner pays, only officers clear and the page is scoped per role', function () {
    $declaration = submitPib($this);
    $stranger = $this->world->shipper(40_000_000);

    expect(fn () => app(PayCustomsDutyAction::class)->execute($stranger, $declaration, '123456'))->toThrow(CustomsException::class, 'berwenang');
    expect(fn () => submitPib($this, by: $stranger))->toThrow(CustomsException::class, 'berwenang');

    app(PayCustomsDutyAction::class)->execute($this->shipper, $declaration, '123456');
    foreach ([$this->world->dispatcher, $this->shipper, $this->world->hubOperator] as $nonOfficer) {
        expect(fn () => app(ClearCustomsAction::class)->execute($nonOfficer, $declaration->fresh()))->toThrow(CustomsException::class, 'berwenang');
    }

    $this->actingAs($this->shipper)->get(route('logistics.customs.index'))->assertOk()->assertSee($declaration->declaration_number);
    $this->actingAs($stranger)->get(route('logistics.customs.index'))->assertOk()->assertDontSee($declaration->declaration_number);
    $this->actingAs($this->world->user('driver'))->get(route('logistics.customs.index'))->assertForbidden();
    $this->actingAs($this->shipper)->post(route('logistics.customs.clear', $declaration->id))->assertSessionHas('error');
    $this->actingAs($this->shipper)->post(route('logistics.customs.tariffs.store'), ['hs_code' => '11111111'])->assertForbidden();

    $this->actingAs($this->world->admin)->post(route('logistics.customs.clear', $declaration->id))->assertSessionHas('success');
    expect($declaration->fresh()->status)->toBe('cleared');
});

test('http: shipper submits a declaration and pays it with a pin', function () {
    $this->actingAs($this->shipper)->post(route('logistics.customs.store'), [
        'tracking_number' => $this->shipment->tracking_number, 'type' => 'PIB', 'has_api' => 1,
        'lines' => [['hs_code' => '85171300', 'value_idr' => 10_000_000]],
    ])->assertSessionHas('success');
    $declaration = CustomsDeclaration::firstOrFail();

    $this->actingAs($this->shipper)->post(route('logistics.customs.pay', $declaration->id), ['pin' => '000000'])->assertSessionHas('error');
    $this->actingAs($this->shipper)->post(route('logistics.customs.pay', $declaration->id), ['pin' => '123456'])->assertSessionHas('success');

    expect($declaration->fresh()->paid_at)->not->toBeNull();
});
