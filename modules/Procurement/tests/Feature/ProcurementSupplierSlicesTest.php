<?php

declare(strict_types=1);

namespace Modules\Procurement\tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Procurement\Application\Actions\AwardBlindTenderToPurchaseOrderAction;
use Modules\Procurement\Application\Actions\ConvertRequisitionToRfqAction;
use Modules\Procurement\Application\Actions\ExecuteApVendorPaymentRunAction;
use Modules\Procurement\Application\Actions\PerformThreeWayGrnInvoiceMatchAction;
use Modules\Procurement\Domain\Models\PurchaseOrder;
use Modules\Procurement\Domain\Models\ReceivingReport;
use Modules\Procurement\Domain\Models\Requisition;
use Modules\Procurement\Domain\Models\RequisitionLine;
use Modules\Procurement\Domain\Models\SupplierInvoice;
use Modules\Procurement\Domain\Models\Tender;
use Modules\Procurement\Domain\Models\TenderBid;
use Modules\Supplier\Application\Actions\EvaluateSupplierSlaPenaltyAction;
use Modules\Supplier\Application\Actions\QualifySupplierAvlScorecardAction;
use Modules\Supplier\Domain\Enums\SupplierStatus;
use Modules\Supplier\Domain\Models\Supplier;
use Tests\TestCase;

class ProcurementSupplierSlicesTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->user = User::first() ?? User::factory()->create(['role' => 'admin']);

        $this->supplier = Supplier::first() ?? Supplier::create([
            'code' => 'SUP-TEST-001',
            'name' => 'PT Sumber Pangan Sejahtera',
            'kind' => 'supplier',
            'status' => 'candidate',
            'lead_time_days' => 5,
            'payment_terms_days' => 30,
        ]);
    }

    public function test_qualify_supplier_avl_scorecard_approves_when_score_meets_threshold(): void
    {
        $action = app(QualifySupplierAvlScorecardAction::class);

        $scorecard = $action->execute(
            supplier: $this->supplier,
            scores: [
                'period' => '2026-10',
                'otd_score' => 90,
                'quality_score' => 95,
                'price_score' => 85,
                'responsiveness_score' => 90,
                'notes' => 'Supplier audit lulus dengan nilai amat baik',
            ]
        );

        $this->assertGreaterThanOrEqual(85, $scorecard->overall_score);
        $this->assertSame(SupplierStatus::Preferred, $this->supplier->fresh()->status);
    }

    public function test_qualify_supplier_avl_scorecard_rejects_when_score_below_threshold(): void
    {
        $action = app(QualifySupplierAvlScorecardAction::class);

        $scorecard = $action->execute(
            supplier: $this->supplier,
            scores: [
                'period' => '2026-10',
                'otd_score' => 40,
                'quality_score' => 50,
                'price_score' => 50,
                'responsiveness_score' => 40,
                'notes' => 'Skor di bawah standar mutu',
            ]
        );

        $this->assertLessThan(60, $scorecard->overall_score);
        $this->assertSame(SupplierStatus::Disqualified, $this->supplier->fresh()->status);
    }

    public function test_convert_requisition_to_rfq_action_creates_rfq_and_invitations(): void
    {
        $req = Requisition::create([
            'number' => 'REQ-2026-TEST-01',
            'title' => 'Pengadaan Bahan Baku Tepung',
            'status' => 'approved',
            'notes' => 'Bulk raw material purchase',
            'requested_by_user_id' => (int) $this->user->id,
        ]);

        RequisitionLine::create([
            'requisition_id' => (string) $req->id,
            'description' => 'Tepung Terigu Premium 25kg',
            'qty' => 500,
            'unit' => 'sak',
            'estimated_unit_price_idr' => 12000,
        ]);

        $action = app(ConvertRequisitionToRfqAction::class);
        $rfq = $action->execute(
            requisition: $req,
            title: 'RFQ Pengadaan Bahan Baku',
            opensAt: now(),
            closesAt: now()->addDays(5),
            invitedSupplierIds: [(string) $this->supplier->id]
        );

        $this->assertStringStartsWith('RFQ-', $rfq->number);
        $this->assertCount(1, $rfq->invitations);
        $this->assertSame('issued', $rfq->status);
    }

    public function test_convert_requisition_to_rfq_requires_lines(): void
    {
        $req = Requisition::create([
            'number' => 'REQ-2026-EMPTY-01',
            'title' => 'Empty Requisition',
            'status' => 'approved',
            'requested_by_user_id' => (int) $this->user->id,
        ]);

        $this->expectException(\InvalidArgumentException::class);

        $action = app(ConvertRequisitionToRfqAction::class);
        $action->execute(
            requisition: $req,
            title: 'RFQ Invalid Dates',
            opensAt: now()->addDays(5),
            closesAt: now(),
            invitedSupplierIds: []
        );
    }

    public function test_award_blind_tender_to_purchase_order_action_awards_winner(): void
    {
        $tender = Tender::create([
            'number' => 'TND-2026-TEST-01',
            'title' => 'Tender Pengadaan Kemasan Kertas',
            'status' => 'bidding',
            'bids_open_at' => now(),
            'bids_close_at' => now()->addDays(3),
            'criteria' => ['price' => 50, 'quality' => 50],
            'created_by_user_id' => (int) $this->user->id,
        ]);

        $bid = TenderBid::create([
            'tender_id' => (string) $tender->id,
            'supplier_id' => (string) $this->supplier->id,
            'seal_hash' => hash('sha256', 'offer_123'),
            'sealed_at' => now(),
            'opened_at' => now(),
            'total_score' => 92.5,
        ]);

        $action = app(AwardBlindTenderToPurchaseOrderAction::class);
        $po = $action->execute(
            tender: $tender,
            winningBid: $bid,
            poTitle: 'PO Tender Award 001',
            expectedDate: now()->addDays(14)->toDateString()
        );

        $this->assertInstanceOf(PurchaseOrder::class, $po);
        $this->assertSame('awarded', $tender->fresh()->status);
        $this->assertTrue((bool) $bid->fresh()->is_winner);
        $this->assertStringStartsWith('PO-TND-', $po->number);
    }

    public function test_perform_three_way_grn_invoice_match_passes_within_tolerance(): void
    {
        $po = PurchaseOrder::create([
            'number' => 'PO-MATCH-001',
            'title' => 'PO Pengadaan Jagung',
            'supplier_id' => (string) $this->supplier->id,
            'status' => 'received',
            'total_amount' => 10000000,
        ]);

        $grn = ReceivingReport::create([
            'number' => 'GRN-MATCH-001',
            'po_id' => (string) $po->id,
            'supplier_id' => (string) $this->supplier->id,
            'status' => 'accepted',
            'received_amount' => 10000000,
        ]);

        $invoice = SupplierInvoice::create([
            'number' => 'INV-SUP-001',
            'supplier_id' => (string) $this->supplier->id,
            'po_id' => (string) $po->id,
            'status' => 'pending',
            'invoice_amount_idr' => 10100000, // 1% variance, within 2% tolerance
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
        ]);

        $action = app(PerformThreeWayGrnInvoiceMatchAction::class);
        $res = $action->execute($po, $grn, $invoice, tolerancePct: 2);

        $this->assertTrue($res['is_matched']);
        $this->assertSame('passed', $res['match']->result);
        $this->assertSame('matched', $invoice->fresh()->status);
    }

    public function test_perform_three_way_grn_invoice_match_flags_variance_when_exceeding_tolerance(): void
    {
        $po = PurchaseOrder::create([
            'number' => 'PO-MATCH-002',
            'title' => 'PO Pengadaan Kedelai',
            'supplier_id' => (string) $this->supplier->id,
            'status' => 'received',
            'total_amount' => 10000000,
        ]);

        $grn = ReceivingReport::create([
            'number' => 'GRN-MATCH-002',
            'po_id' => (string) $po->id,
            'supplier_id' => (string) $this->supplier->id,
            'status' => 'accepted',
            'received_amount' => 10000000,
        ]);

        $invoice = SupplierInvoice::create([
            'number' => 'INV-SUP-002',
            'supplier_id' => (string) $this->supplier->id,
            'po_id' => (string) $po->id,
            'status' => 'pending',
            'invoice_amount_idr' => 10500000, // 5% variance exceeds 2% tolerance
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
        ]);

        $action = app(PerformThreeWayGrnInvoiceMatchAction::class);
        $res = $action->execute($po, $grn, $invoice, tolerancePct: 2);

        $this->assertFalse($res['is_matched']);
        $this->assertSame('variance_flagged', $res['match']->result);
        $this->assertSame('disputed', $invoice->fresh()->status);
    }

    public function test_execute_ap_vendor_payment_run_calculates_discounts_and_executes_batch(): void
    {
        $inv1 = SupplierInvoice::create([
            'number' => 'INV-PAY-01',
            'supplier_id' => (string) $this->supplier->id,
            'status' => 'matched',
            'invoice_amount_idr' => 10000000,
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(15)->toDateString(),
        ]);

        $inv2 = SupplierInvoice::create([
            'number' => 'INV-PAY-02',
            'supplier_id' => (string) $this->supplier->id,
            'status' => 'matched',
            'invoice_amount_idr' => 20000000,
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(15)->toDateString(),
        ]);

        $action = app(ExecuteApVendorPaymentRunAction::class);
        $res = $action->execute(
            batchNumber: 'BATCH-2026-10-01',
            invoicesData: [
                ['invoice' => $inv1, 'early_discount_bps' => 200], // 2% discount = 200,000 -> 9,800,000
                ['invoice' => $inv2, 'early_discount_bps' => 100], // 1% discount = 200,000 -> 19,800,000
            ],
            scheduledDate: now()->addDays(2)->toDateString(),
            userId: (int) $this->user->id
        );

        $this->assertSame('executed', $res['batch']->status);
        $this->assertSame(29600000, $res['net_payable_idr']);
        $this->assertSame(400000, $res['total_discount_idr']);
        $this->assertCount(2, $res['batch']->items);
    }

    public function test_evaluate_supplier_sla_penalty_calculates_late_and_defect_penalties(): void
    {
        $action = app(EvaluateSupplierSlaPenaltyAction::class);

        // 10 days late (100 bps = 1%), 2.0% defect (1.0% excess * 50 = 50 bps = 0.5%)
        // Total bps: 150 bps (1.5%) of 100,000,000 = 1,500,000
        $res = $action->execute(
            supplier: $this->supplier,
            orderValueIdr: 100000000,
            lateDays: 10,
            defectRatePct: 2.0,
            period: '2026-10'
        );

        $this->assertSame(1000000, $res['late_penalty_idr']);
        $this->assertSame(500000, $res['defect_penalty_idr']);
        $this->assertSame(1500000, $res['penalty_amount_idr']);
        $this->assertNull($res['risk_flag']);
    }

    public function test_evaluate_supplier_sla_penalty_flags_critical_risk_when_thresholds_breached(): void
    {
        $action = app(EvaluateSupplierSlaPenaltyAction::class);

        // 20 days late and 12.0% defect rate -> critical severity flag
        $res = $action->execute(
            supplier: $this->supplier,
            orderValueIdr: 100000000,
            lateDays: 20,
            defectRatePct: 12.0,
            period: '2026-10'
        );

        $this->assertNotNull($res['risk_flag']);
        $this->assertSame('critical', $res['risk_flag']->severity);
        $this->assertTrue($res['risk_flag']->is_open);
    }
}
