<?php

declare(strict_types=1);

namespace Tests\Feature\Distribution;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Distribution\Application\Services\DistributionFulfilmentService;
use Modules\Distribution\Application\Services\DistributionService;
use Modules\Distribution\Domain\Models\DistOrder;
use Modules\Distribution\Domain\Models\Distributor;
use Modules\Distribution\Domain\Models\HetPrice;
use Modules\Distribution\Domain\Models\RebateAccrual;
use Modules\Logistics\Domain\Models\Shipment;
use Modules\Store\Domain\Models\Category;
use Modules\Store\Domain\Models\Product;
use Tests\TestCase;

/**
 * Regresi Fase 43: ATP/backorder/fair-share, limit, fulfillment+shipment+POD
 * + tax invoice, sell-out anomali, consignment, return/credit-note,
 * rebate accrual/settlement, HET, VMI, dist:audit.
 */
class DistributionFulfilmentTest extends TestCase
{
    use RefreshDatabase;

    private DistributionService $distribution;

    private DistributionFulfilmentService $fulfilment;

    private User $admin;

    private Distributor $distributor;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->distribution = app(DistributionService::class);
        $this->fulfilment = app(DistributionFulfilmentService::class);
        $this->admin = User::where('role', 'admin')->firstOrFail();

        $this->distributor = $this->makeApprovedDistributor(100_000_000);

        $category = Category::create(['name' => 'Distributor', 'slug' => 'dist-'.uniqid()]);
        $this->product = Product::create([
            'category_id' => $category->id, 'name' => 'Produk distribusi',
            'slug' => 'prod-'.uniqid(), 'sku' => 'SKU-DIST-01', 'price' => 50_000,
            'cached_stock' => 100, 'is_car' => false,
        ]);
    }

    private function makeApprovedDistributor(int $limit): Distributor
    {
        $d = $this->distribution->registerDistributor([
            'code' => 'D43-'.uniqid(), 'name' => 'Distributor F43', 'credit_limit_idr' => $limit,
        ]);
        $this->distribution->recordSecurity($d, ['kind' => 'deposit', 'amount_idr' => 1_000_000]);
        $this->distribution->submitOnboarding($d, $this->admin);

        return $this->distribution->approveOnboarding(
            $d,
            User::factory()->create(['role' => 'admin']),
            $limit
        );
    }

    private function makeOrder(float $qty = 5, array $options = []): DistOrder
    {
        return $this->fulfilment->placeOrder(
            $this->distributor,
            [['product_id' => $this->product->id, 'qty' => $qty, 'unit_price_idr' => 50_000]],
            $this->admin,
            $options,
        );
    }

    private function ledgerBalance(string $code): int
    {
        return (int) (LedgerAccount::where('code', $code)->value('cached_balance') ?? 0);
    }

    // ── 43.1 ATP, credit limit, backorder ────────────────────────────────

    public function test_order_rejects_credit_over_limit_and_allocates_backorder(): void
    {
        $d = $this->makeApprovedDistributor(2_000_000);
        $order = $this->fulfilment->placeOrder($d, [
            ['product_id' => $this->product->id, 'qty' => 20, 'unit_price_idr' => 50_000],
        ], $this->admin);
        $this->assertSame(1_110_000, (int) $order->total_idr);

        $this->product->update(['cached_stock' => 7]);
        $allocated = $this->fulfilment->allocate($order);
        $this->assertSame(1, $allocated['allocated'], 'Sebagian dialokasikan.');
        $this->assertSame(1, $allocated['backordered'], 'Sisa jadi backorder per baris.');
        $line = $order->lines()->firstOrFail()->fresh();
        $this->assertEqualsWithDelta(7.0, (float) $line->allocated_qty, 0.0001);
        $this->assertEqualsWithDelta(13.0, (float) $line->backorderQty(), 0.0001);
        $this->assertSame('partial', $order->fresh()->status);

        $this->distribution->issueInvoice($d, 1_500_000, now()->toDateString(), now()->addDays(30)->toDateString(), $this->admin);
        try {
            $this->fulfilment->placeOrder($d->fresh(), [
                ['product_id' => $this->product->id, 'qty' => 10, 'unit_price_idr' => 50_000],
            ], $this->admin);
            $this->fail('Melebihi limit harus ditolak.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('limit kredit', mb_strtolower($e->getMessage()));
        }
    }

    public function test_fair_share_allocation_splits_scarce_stock(): void
    {
        $this->product->update(['cached_stock' => 10]);
        $orderA = $this->makeOrder(10, ['allocation_strategy' => 'fair_share']);
        $orderB = $this->makeOrder(10, ['allocation_strategy' => 'fair_share']);

        $this->fulfilment->allocate($orderA);
        $this->fulfilment->allocate($orderB);

        $lineA = $orderA->lines()->firstOrFail()->fresh();
        $lineB = $orderB->lines()->firstOrFail()->fresh();
        $this->assertEqualsWithDelta(5.0, (float) $lineA->allocated_qty, 0.0001);
        $this->assertEqualsWithDelta(5.0, (float) $lineB->allocated_qty, 0.0001);
    }

    // ── 43.2 Fulfillment / Shipment / POD / invoice ──────────────────────

    public function test_fulfilment_books_shipment_and_pod_issues_tax_invoice(): void
    {
        $this->product->update(['cached_stock' => 10]);
        $order = $this->makeOrder(3);
        $this->fulfilment->allocate($order);
        $this->assertSame('allocated', $order->fresh()->status);

        $shipment = $this->fulfilment->fulfil($order->fresh(), $this->admin, ['mode' => 'ltl']);
        $this->assertSame('picked', $shipment->status);
        $this->assertStringStartsWith('DSP/', $shipment->number);
        $this->assertNotNull($shipment->tracking_number, 'ShipmentBooking menautkan Logistics.');
        $this->assertSame(1, Shipment::where('source_type', 'dist_shipment')->where('source_id', $shipment->id)->count());
        $this->assertSame('shipped', $order->fresh()->status);

        $again = $this->fulfilment->fulfil($order->fresh(), $this->admin);
        $this->assertSame($shipment->id, $again->id);
        $this->assertSame(1, $order->shipments()->count());

        $pod = $this->fulfilment->recordPod($shipment, 'Penerima Toko', $this->admin, 'Diterima lengkap');
        $this->assertSame('pod', $pod['shipment']->status);
        $this->assertSame('delivered', $order->fresh()->status);
        $this->assertNotNull($pod['invoice']);
        $invoice = $pod['invoice'];
        $this->assertStringStartsWith('FTR/', $invoice->number);
        $this->assertStringStartsWith('010.', $invoice->tax_serial);
        $this->assertSame(166_500, (int) $invoice->total_idr, '3×50rb + PPN 11% = 150rb+16.5rb.');

        $this->artisan('bank:reconcile')->assertSuccessful();
    }

    // ── 43.3 Sell-out + anomaly ──────────────────────────────────────────

    public function test_sellout_report_flags_price_violation_and_updates_target(): void
    {
        $territory = $this->distribution->createTerritory(['code' => 'SOUT-'.uniqid(), 'name' => 'Kota Sellout', 'level' => 'province']);
        $this->distribution->coverTerritory($this->distributor, $territory, false, now()->subMonth()->toDateString());
        $outlet = $this->distribution->addOutlet($this->distributor, [
            'code' => 'OUT-1', 'name' => 'Toko Sellout', 'territory_id' => $territory->id,
        ]);

        HetPrice::create([
            'sku' => $this->product->sku, 'het_idr' => 50_000,
            'valid_from' => now()->subMonth()->toDateString(), 'valid_until' => now()->addMonth()->toDateString(),
        ]);
        $target = $this->distribution->setTarget($this->distributor, [
            'product_sku' => $this->product->sku, 'period' => now()->format('Y'), 'target_qty' => 10, 'basis' => 'sell_out',
        ]);

        $result = $this->fulfilment->submitSellout(
            $this->distributor, $outlet->id, now()->toDateString(),
            [['product_id' => $this->product->id, 'qty' => 3, 'unit_price_idr' => 20_000]],
            $this->admin,
        );

        $this->assertContains("price_violation:{$this->product->sku}", $result['anomalies']);
        $this->assertSame('flagged', $result['report']->status);
        $this->assertEqualsWithDelta(3.0, (float) $target->fresh()->achieved_qty, 0.0001);
    }

    // ── 43.4 Konsinyasi ─────────────────────────────────────────────────

    public function test_consignment_sale_and_reconciliation_moves_ownership(): void
    {
        $stock = $this->fulfilment->receiveConsignment($this->distributor, $this->product->id, $this->product->sku, 10, 300_000);
        $this->assertEqualsWithDelta(10.0, (float) $stock->qty, 0.0001);

        $sale = $this->fulfilment->reportConsignmentSale($stock, 4, 50_000, now()->toDateString(), $this->admin);
        $this->assertEqualsWithDelta(6.0, (float) $sale['stock']->qty, 0.0001);
        $this->assertEqualsWithDelta(4.0, (float) $sale['stock']->qty_sold_unbilled, 0.0001);

        $result = $this->fulfilment->invoiceConsignment($this->distributor, $this->admin);
        $this->assertSame(1, $result['billed']);
        $this->assertSame('issued', $result['invoice']->status);
        $this->assertSame(0, (int) $stock->fresh()->qty_sold_unbilled);
        $this->assertSame('invoiced', $sale['sale']->fresh()->status);
        $this->artisan('bank:reconcile')->assertSuccessful();

        $again = $this->fulfilment->invoiceConsignment($this->distributor, $this->admin);
        $this->assertSame(0, $again['billed']);
    }

    // ── 43.5 Retur & credit note ────────────────────────────────────────

    public function test_return_requires_reason_and_approval_credits_ar(): void
    {
        $this->distribution->issueInvoice(
            $this->distributor, 1_000_000, now()->toDateString(), now()->addDays(30)->toDateString(), $this->admin
        );
        $return = $this->fulfilment->requestReturn($this->distributor, [
            'reason' => 'damaged', 'disposition' => 'quarantine', 'qty' => 2,
            'amount_idr' => 200_000,
        ], now()->toDateString(), $this->admin);

        $this->assertStringStartsWith('RDN/', $return->number);
        $this->assertSame('requested', $return->status);

        $credited = $this->fulfilment->approveReturn($return, $this->admin);
        $this->assertSame('credited', $credited->status);
        $this->assertSame(800_000, (int) $this->distributor->fresh()->credit_exposure_idr);
        $this->assertSame(200_000, $this->ledgerBalance(DistributionFulfilmentService::ACCT_REVENUE));
        $this->artisan('bank:reconcile')->assertSuccessful();
    }

    // ── 43.6 Rebate ─────────────────────────────────────────────────────

    public function test_rebate_accrues_by_volume_and_settles_via_approval(): void
    {
        $order = $this->makeOrder(10);
        $program = $this->fulfilment->createRebateProgram([
            'code' => 'VOL-43', 'name' => 'Volume 2%', 'kind' => 'volume',
            'valid_from' => now()->subMonth()->toDateString(), 'valid_until' => now()->addMonth()->toDateString(),
            'rate_percent' => 2.0,
        ]);
        $accruals = $this->fulfilment->accrueRebate($order, $this->admin);
        $this->assertCount(1, $accruals);
        $this->assertSame(10_000, (int) $accruals[0]->rebate_amount_idr, '2% × subtotal 500rb.');
        $this->assertSame(10_000, $this->ledgerBalance(DistributionFulfilmentService::ACCT_REBATE_EXPENSE));
        $this->assertSame(-10_000, $this->ledgerBalance(DistributionFulfilmentService::ACCT_REBATE_PAYABLE));

        $again = $this->fulfilment->accrueRebate($order, $this->admin);
        $this->assertCount(1, $again);
        $this->assertSame(1, RebateAccrual::where('order_id', $order->id)->where('program_id', $program->id)->count());

        $period = now()->toDateString();
        $submission = $this->fulfilment->settleRebate($period, $this->admin);
        $this->assertSame(1, $submission['accruals']);
        $reviewer = User::factory()->create(['role' => 'admin']);
        $settled = $this->fulfilment->approveSettlement($period, $reviewer);
        $this->assertSame(1, $settled);
        $this->assertSame('settled', $accruals[0]->fresh()->status);
        $this->artisan('bank:reconcile')->assertSuccessful();
    }

    // ── 43.7 HET margin ─────────────────────────────────────────────────

    public function test_margin_report_compares_sell_price_to_het(): void
    {
        $order = $this->makeOrder(1);
        HetPrice::create([
            'sku' => $this->product->sku, 'het_idr' => 60_000,
            'valid_from' => now()->subDay()->toDateString(), 'valid_until' => now()->addDay()->toDateString(),
        ]);

        $rows = $this->fulfilment->marginReport($order);
        $this->assertCount(1, $rows);
        $this->assertSame(60_000, $rows[0]['het']);
        $this->assertLessThan(0, $rows[0]['margin_percent']);
    }

    // ── 43.8 VMI ────────────────────────────────────────────────────────

    public function test_vmi_critical_stock_suggests_order_with_buffer(): void
    {
        $level = $this->fulfilment->setStockLevel(
            $this->distributor, $this->product->id, $this->product->sku,
            onHand: 2, minQty: 5, maxQty: 10, avgDailySales: 2,
        );

        $this->assertSame('critical', $level->status);
        $this->assertTrue($level->isCritical());
        $this->assertEqualsWithDelta(22.0, (float) $level->suggested_order_qty, 0.0001);

        $suggestions = $this->fulfilment->computeVmiSuggestions();
        $this->assertCount(1, $suggestions);
    }
}
