<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Logistics\Application\Actions\DepositCodCashAction;
use Modules\Logistics\Application\Actions\GenerateMonthlyInvoicesAction;
use Modules\Logistics\Application\Actions\SettleCodAction;
use Modules\Logistics\Application\Services\BillingAuditor;
use Modules\Logistics\database\seeders\LogisticsSeeder;
use Modules\Logistics\Domain\Models\CodCollection;
use Modules\Logistics\Domain\Models\HubOperator;
use Modules\Logistics\tests\Support\MoneyFlowWorld;

uses(RefreshDatabase::class);

function auditFailures(): array
{
    $auditor = app(BillingAuditor::class);

    return array_values(array_map(fn ($r) => $r['key'], array_filter($auditor->run(), fn ($r) => ! $r['ok'])));
}

test('default seed produces a non-zero audit across every money flow and passes', function () {
    $this->seed(LogisticsSeeder::class);

    $results = app(BillingAuditor::class)->run();

    expect($results)->toHaveCount(15);
    foreach ($results as $result) {
        expect($result['ok'])->toBeTrue("{$result['label']}: {$result['detail']}")
            ->and($result['items'])->toBeGreaterThan(0, "{$result['label']} tidak memeriksa dokumen apa pun");
    }

    $this->artisan('lgx:audit-billing')->expectsOutputToContain('0 selisih')->assertSuccessful();
    $this->artisan('bank:reconcile')->assertSuccessful();
    $this->artisan('lgx:verify-custody')->assertSuccessful();
    $this->artisan('lgx:capacity-check')->assertSuccessful();
});

test('an empty database audits clean', function () {
    $this->artisan('lgx:audit-billing')->assertSuccessful();
});

test('(a) a full prepaid, cod and postpaid cycle audits clean', function () {
    $world = new MoneyFlowWorld;
    $shipper = $world->shipper(5_000_000, true);
    HubOperator::create(['user_id' => $world->hubOperator->id, 'hub_id' => $world->hubA->id]);
    $driver = $world->driver();

    $world->deliver($world->bookPrepaid($shipper), $driver);
    $world->deliver($world->bookPrepaid($shipper, cod: 200_000), $driver);
    $world->bookPrepaid($shipper); // masih berjalan => unearned
    $world->deliver($world->bookPostpaid($shipper), $driver);
    app(DepositCodCashAction::class)->execute($world->hubOperator, $world->hubA, $driver, 200_000);
    app(SettleCodAction::class)->execute(CodCollection::firstOrFail());

    expect(auditFailures())->toBe([]);
    $this->artisan('lgx:audit-billing')->assertSuccessful();
});

test('(b) tampering with recognition markers or document amounts is detected and fails the command', function () {
    $world = new MoneyFlowWorld;
    $shipper = $world->shipper();
    $delivered = $world->bookPrepaid($shipper);
    $world->deliver($delivered);
    expect(auditFailures())->toBe([]);

    // Delivered tetapi pendapatan tidak diakui
    DB::table('lgx_shipments')->where('id', $delivered->id)->update(['revenue_recognized_at' => null]);
    expect(auditFailures())->toContain('delivered_revenue')->toContain('freight_revenue');
    $this->artisan('lgx:audit-billing')->expectsOutputToContain('belum diakui')->assertFailed();

    DB::table('lgx_shipments')->where('id', $delivered->id)->update(['revenue_recognized_at' => now()]);
    expect(auditFailures())->toBe([]);

    // Jumlah resi diubah di tabel tanpa jurnal
    DB::table('lgx_shipments')->where('id', $delivered->id)->update(['total_amount_idr' => $delivered->total_amount_idr + 1_000]);
    expect(auditFailures())->toContain('freight_revenue');
});

test('(b) tampering with cod documents is detected', function () {
    $world = new MoneyFlowWorld;
    $shipper = $world->shipper();
    $world->deliver($world->bookPrepaid($shipper, cod: 150_000));

    expect(auditFailures())->toBe([]);

    DB::table('lgx_cod_collections')->update(['amount_idr' => 140_000]);
    expect(auditFailures())->toContain('cod_payable')->toContain('cod_cash');
});

test('(c) an unposted manual change to an invoice total is caught by the invoice cross check', function () {
    $world = new MoneyFlowWorld;
    $shipper = $world->shipper(5_000_000, true);
    $world->deliver($world->bookPostpaid($shipper));
    app(GenerateMonthlyInvoicesAction::class)->execute(now()->format('Y-m'));
    expect(auditFailures())->toBe([]);

    DB::table('lgx_invoices')->update(['total_amount_idr' => DB::raw('total_amount_idr + 500')]);

    expect(auditFailures())->toContain('freight_invoices');
});

test('(e) the audit is a console command only and exposes no http route', function () {
    $names = collect(app('router')->getRoutes())->map(fn ($r) => $r->getName())->filter()->implode(',');

    expect($names)->not->toContain('audit-billing');
    expect(array_keys(Artisan::all()))->toContain('lgx:audit-billing');
});
