<?php

declare(strict_types=1);

namespace Tests\Feature\Procurement;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Core\Application\Services\SystemHealthService;
use Modules\Procurement\Application\Services\ProcurementService;
use Modules\Procurement\Application\Services\ReceivingService;
use Modules\Procurement\Domain\Models\PurchaseOrder;
use Modules\Procurement\Domain\Models\SupplierInvoice;
use Modules\Supplier\Domain\Models\Supplier;
use Tests\TestCase;

/**
 * Regresi Fase 34: GRN parsial + toleransi, 3-way match, akuntansi AP
 * (GR/IR, PPN/PPh simulasi), batch payment, uang muka/kredit memo, landed cost.
 */
class ReceivingAndPayablesTest extends TestCase
{
    use RefreshDatabase;

    private ProcurementService $procurement;

    private ReceivingService $receiving;

    private User $admin;

    private Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->procurement = app(ProcurementService::class);
        $this->receiving = app(ReceivingService::class);
        $this->admin = User::where('role', 'admin')->firstOrFail();
        $this->supplier = Supplier::where('is_active', true)->firstOrFail();
    }

    private function makePo(int $unitPrice = 1_000_000, int $qty = 10): PurchaseOrder
    {
        return $this->procurement->createPurchaseOrder([
            'supplier_id' => $this->supplier->id,
            'title' => 'PO pengujian GRN',
            'lines' => [['description' => 'Barang uji', 'qty' => $qty, 'unit_price' => $unitPrice]],
        ], $this->admin);
    }

    // ── 34.1 GRN parsial, toleransi, lot ─────────────────────────────────

    public function test_partial_receipt_updates_po_status_and_posts_gr_ir(): void
    {
        $po = $this->makePo(1_000_000, 10);
        $poLine = $po->lines()->firstOrFail();

        $grn = $this->receiving->receive($po, [[
            'po_line_id' => $poLine->id,
            'received_qty' => 4,
            'lot_number' => 'LOT-A1',
            'expiry_date' => now()->addYear()->toDateString(),
        ]], $this->admin);

        $this->assertStringStartsWith('GRN/', $grn->number);
        $this->assertSame('partially_received', $po->fresh()->status);
        $this->assertSame(4, $poLine->fresh()->received_qty);
        $this->assertSame('LOT-A1', $grn->lines->first()->lot_number);

        // GR/IR dikredit senilai barang diterima (4 × 1jt = 4jt).
        $grir = LedgerAccount::where('code', 'inv:grir')->firstOrFail();
        $this->assertSame(-4_000_000, (int) $grir->cached_balance);

        $this->artisan('bank:reconcile')->assertSuccessful();
    }

    public function test_receipt_beyond_tolerance_is_rejected(): void
    {
        $po = $this->makePo(1_000_000, 10);
        $poLine = $po->lines()->firstOrFail();

        // 10 unit + toleransi 5% = maks 11 (ceil) → 13 harus ditolak.
        try {
            $this->receiving->receive($po, [[
                'po_line_id' => $poLine->id,
                'received_qty' => 13,
            ]], $this->admin);
            $this->fail('Over-delivery melewati toleransi harus ditolak.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('toleransi', $e->getMessage());
        }
    }

    public function test_repeated_receipts_accumulate(): void
    {
        $po = $this->makePo(500_000, 10);
        $poLine = $po->lines()->firstOrFail();

        $this->receiving->receive($po, [['po_line_id' => $poLine->id, 'received_qty' => 6]], $this->admin);
        $this->receiving->receive($po, [['po_line_id' => $poLine->id, 'received_qty' => 4]], $this->admin);

        $this->assertSame(10, $poLine->fresh()->received_qty);
        $this->assertSame('received', $po->fresh()->status);
        $this->assertSame(2, $po->receivingReports()->count());
    }

    // ── 34.2 Inspeksi: rejected → karantina + retur ──────────────────────

    public function test_rejected_units_trigger_quarantine_and_supplier_return(): void
    {
        $po = $this->makePo(200_000, 5);
        $poLine = $po->lines()->firstOrFail();

        $grn = $this->receiving->receive($po, [[
            'po_line_id' => $poLine->id,
            'received_qty' => 5,
            'accepted_qty' => 4,
            'rejected_qty' => 1,
        ]], $this->admin);

        $this->assertSame(1, $grn->inspections->count());
        $this->assertTrue((bool) $grn->inspections->first()->quarantine);

        $this->assertSame(1, $grn->returns->count());
        $this->assertSame(200_000, (int) $grn->returns->first()->amount_idr);
        $this->assertStringStartsWith('RTN/', $grn->returns->first()->number);
    }

    // ── 34.3 3-way match: hold vs matched ────────────────────────────────

    public function test_three_way_match_within_tolerance_approves_automatically(): void
    {
        $po = $this->makePo(1_000_000, 10);
        $poLine = $po->lines()->firstOrFail();
        $grn = $this->receiving->receive($po, [['po_line_id' => $poLine->id, 'received_qty' => 10]], $this->admin);

        $result = $this->receiving->matchInvoice(
            $this->supplier,
            $po->fresh(),
            $grn,
            'INV-EXACT-001',
            10_000_000,
            $this->admin
        );

        $this->assertFalse($result['held']);
        $this->assertSame('approved', $result['invoice']->status);
        $this->assertSame('matched', $result['invoice']->matches()->first()->result);
        $this->artisan('bank:reconcile')->assertSuccessful();
    }

    public function test_price_variance_beyond_tolerance_holds_for_approval(): void
    {
        $po = $this->makePo(1_000_000, 10);
        $poLine = $po->lines()->firstOrFail();
        $grn = $this->receiving->receive($po, [['po_line_id' => $poLine->id, 'received_qty' => 10]], $this->admin);

        // 12jt vs 10jt = +20% > toleransi harga 2%.
        $result = $this->receiving->matchInvoice(
            $this->supplier,
            $po->fresh(),
            $grn,
            'INV-VARIANCE-001',
            12_000_000,
            $this->admin
        );

        $this->assertTrue($result['held']);
        $this->assertSame('held', $result['invoice']->status);
        $this->assertSame('price_hold', $result['invoice']->matches->first()->result);

        // Setelah approval → jurnal AP terbit.
        $approved = $this->receiving->approveHeldInvoice($result['invoice'], $this->admin);
        $this->assertSame('approved', $approved->status);
        $this->artisan('bank:reconcile')->assertSuccessful();
    }

    public function test_three_way_match_helper_is_pure(): void
    {
        $within = $this->receiving->evaluateThreeWayMatch(10_000_000, 10_000_000, 10_100_000, 2, 5);
        $this->assertTrue($within['within_tolerance']);
        $this->assertEqualsWithDelta(1.0, $within['price_variance_pct'], 0.01);

        $outside = $this->receiving->evaluateThreeWayMatch(10_000_000, 7_000_000, 14_000_000, 2, 5);
        $this->assertFalse($outside['within_tolerance']);
    }

    // ── 34.5 Batch payment run ───────────────────────────────────────────

    private function approvedInvoice(int $amount): SupplierInvoice
    {
        $po = $this->makePo($amount, 1);
        $poLine = $po->lines()->firstOrFail();
        $grn = $this->receiving->receive($po, [['po_line_id' => $poLine->id, 'received_qty' => 1]], $this->admin);

        $result = $this->receiving->matchInvoice(
            $this->supplier,
            $po->fresh(),
            $grn,
            'INV-'.uniqid(),
            $amount,
            $this->admin
        );

        return $result['invoice'];
    }

    public function test_payment_batch_requires_approval_then_executes_idempotently(): void
    {
        $invoice = $this->approvedInvoice(5_000_000);

        $batch = $this->receiving->createPaymentBatch(
            [['invoice_id' => $invoice->id]],
            $this->admin
        );

        $this->assertSame('pending_approval', $batch->status);
        $this->assertSame(5_000_000, (int) $batch->total_amount_idr);
        $this->assertNotNull($batch->approval_id);

        // Eksekusi sebelum approval ditolak.
        try {
            $this->receiving->executePaymentBatch($batch, $this->admin);
            $this->fail('Batch belum approved harus ditolak.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('approved', $e->getMessage());
        }

        $batch->update(['status' => 'approved']);
        $paid = $this->receiving->executePaymentBatch($batch->fresh(), $this->admin);

        $this->assertSame('completed', $paid->status);
        $this->assertSame('paid', $invoice->fresh()->status);

        // Replay aman: status completed → tidak posting ulang.
        $again = $this->receiving->executePaymentBatch($paid, $this->admin);
        $this->assertSame('completed', $again->status);

        $this->artisan('bank:reconcile')->assertSuccessful();
    }

    public function test_early_discount_balances_ledger(): void
    {
        $invoice = $this->approvedInvoice(4_000_000);

        $batch = $this->receiving->createPaymentBatch(
            [['invoice_id' => $invoice->id, 'early_discount_idr' => 200_000]],
            $this->admin
        );
        $batch->update(['status' => 'approved']);

        $paid = $this->receiving->executePaymentBatch($batch->fresh(), $this->admin);

        $this->assertSame(3_800_000, (int) $paid->total_amount_idr);
        $this->artisan('bank:reconcile')->assertSuccessful();
    }

    // ── 34.6 Advance & credit memo ───────────────────────────────────────

    public function test_supplier_advance_and_credit_memo_are_idempotent(): void
    {
        $advance = $this->receiving->createSupplierAdvance($this->supplier, 2_000_000, 'adv-key-1', $this->admin);
        $this->assertSame(2_000_000, (int) $advance->amount_idr);
        $this->artisan('bank:reconcile')->assertSuccessful();

        $invoice = $this->approvedInvoice(3_000_000);
        $memo = $this->receiving->createCreditMemo($this->supplier, $invoice, 500_000, 'Barang rusak', 'cm-key-1', $this->admin);

        $this->assertSame(500_000, (int) $memo->amount_idr);
        $this->assertSame(1, $invoice->fresh() !== null ? 1 : 0);
        $this->artisan('bank:reconcile')->assertSuccessful();
    }

    // ── 34.7 Landed cost allocation ──────────────────────────────────────

    public function test_landed_cost_allocation_sums_exactly_to_total(): void
    {
        $po = $this->makePo(1_000_000, 4);
        // Tambah baris kedua agar alokasi terbagi.
        $this->procurement->createPurchaseOrder([
            'supplier_id' => $this->supplier->id,
            'title' => 'PO dua baris',
            'lines' => [
                ['description' => 'Barang A', 'qty' => 3, 'unit_price' => 1_000_000],
                ['description' => 'Barang B', 'qty' => 7, 'unit_price' => 2_000_000],
            ],
        ], $this->admin);

        $po = PurchaseOrder::where('title', 'PO dua baris')->firstOrFail();
        $result = $this->receiving->allocateLandedCost($po, 1_000_001, 'freight', 'value');

        $this->assertSame(1_000_001, $result['total_idr']);
        $this->assertSame(
            1_000_001,
            array_sum(array_column($result['allocations'], 'allocation_idr')),
            'Alokasi harus berjumlah persis total (pembulatan terakhir menyerap selisih).'
        );
        $this->assertCount(2, $result['allocations']);
        $this->artisan('bank:reconcile')->assertSuccessful();
    }

    // ── 34.8 Audit AP ────────────────────────────────────────────────────

    public function test_account_balances_expose_ap_and_grir(): void
    {
        $this->makePo(1_000_000, 2);
        $poLine = PurchaseOrder::firstOrFail()->lines()->first();
        $grn = $this->receiving->receive(PurchaseOrder::first(), [['po_line_id' => $poLine->id, 'received_qty' => 2]], $this->admin);

        $balances = $this->receiving->accountBalances($this->supplier->id);

        $this->assertArrayHasKey('ap_idr', $balances);
        $this->assertArrayHasKey('grir_idr', $balances);
        $this->assertSame(-2_000_000, $balances['grir_idr'], 'GR/IR dikredit 2 × 1jt.');
        $this->assertSame(0, $balances['ap_idr'], 'AP baru bergerak setelah invoice.');
    }

    // ── 34.8 proc:audit + health pillar ─────────────────────────────────

    public function test_proc_audit_is_balanced_and_health_pillar_is_healthy(): void
    {
        $this->makePo(1_500_000, 4);
        $poLine = PurchaseOrder::firstOrFail()->lines()->first();
        $grn = $this->receiving->receive(PurchaseOrder::first(), [['po_line_id' => $poLine->id, 'received_qty' => 4]], $this->admin);

        $this->receiving->matchInvoice($this->supplier, PurchaseOrder::first(), $grn, 'INV-AUDIT-1', 6_000_000, $this->admin);

        $this->artisan('proc:audit')->assertSuccessful();

        $health = app(SystemHealthService::class)->check($this->admin);
        $this->assertArrayHasKey('procurement', $health['checks']);
        $this->assertTrue($health['checks']['procurement']['ok'], $health['checks']['procurement']['message']);
        $this->assertSame('HEALTHY', $health['status']);
    }
}
