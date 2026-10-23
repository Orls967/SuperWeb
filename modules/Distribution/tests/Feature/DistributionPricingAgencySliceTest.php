<?php

declare(strict_types=1);

namespace Modules\Distribution\tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Agency\Application\Actions\CalculateMultiTierAgentCommissionAction;
use Modules\Agency\Application\Actions\ReleaseClawbackHoldCommissionAction;
use Modules\Agency\Domain\Models\Agent;
use Modules\Agency\Domain\Models\CommissionAccrual;
use Modules\Distribution\Application\Actions\ProcessDistributorSellInOrderAction;
use Modules\Distribution\Application\Actions\ReconcileConsignmentStockCountAction;
use Modules\Distribution\Domain\Models\ConsignmentStock;
use Modules\Distribution\Domain\Models\Distributor;
use Modules\Pricing\Application\Actions\EvaluateWaterfallPromoStackingAction;
use Modules\Pricing\Application\Actions\SettleOffInvoiceRebateClaimAction;
use Modules\Pricing\Domain\Models\Promotion;
use Modules\Pricing\Domain\Models\PromotionClaim;
use Tests\TestCase;

class DistributionPricingAgencySliceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Distributor $distributor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->user = User::first() ?? User::factory()->create(['role' => 'admin']);

        $this->distributor = Distributor::create([
            'code' => 'DST-TEST-001',
            'name' => 'PT Distribusi Niaga Sejahtera',
            'kind' => 'distributor',
            'status' => 'approved',
            'credit_limit_idr' => 100000000,
            'credit_exposure_idr' => 20000000,
        ]);
    }

    public function test_process_distributor_sell_in_order_allocates_stock_and_updates_exposure(): void
    {
        $action = app(ProcessDistributorSellInOrderAction::class);

        $order = $action->execute(
            distributor: $this->distributor,
            items: [
                [
                    'product_id' => 101,
                    'sku' => 'PRD-SNACK-01',
                    'name' => 'Keripik Singkong Balado 200g',
                    'qty' => 100,
                    'unit_price_idr' => 15000, // 1,500,000
                ],
                [
                    'product_id' => 102,
                    'sku' => 'PRD-SNACK-02',
                    'name' => 'Kacang Bawang Renyah 150g',
                    'qty' => 50,
                    'unit_price_idr' => 20000, // 1,000,000
                ],
            ],
            orderNumber: 'DOR-2026-TEST-001',
            discountIdr: 100000, // Subtotal 2,500,000 - 100,000 = 2,400,000 + 11% PPN (264,000) = 2,664,000
            userId: (int) $this->user->id
        );

        $this->assertSame('allocated', $order->status);
        $this->assertSame(2500000, (int) $order->subtotal_idr);
        $this->assertSame(264000, (int) $order->ppn_idr);
        $this->assertSame(2664000, (int) $order->total_idr);
        $this->assertCount(2, $order->lines);
        $this->assertSame(22664000, (int) $this->distributor->fresh()->credit_exposure_idr);
    }

    public function test_process_distributor_sell_in_order_blocks_when_credit_limit_exceeded(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $action = app(ProcessDistributorSellInOrderAction::class);
        $action->execute(
            distributor: $this->distributor,
            items: [
                [
                    'product_id' => 103,
                    'sku' => 'PRD-BULK-01',
                    'name' => 'Bulk Premium Oil 200L',
                    'qty' => 50,
                    'unit_price_idr' => 2000000, // 100,000,000 exceeds remaining 80M credit limit
                ],
            ],
            orderNumber: 'DOR-EXCEED-001'
        );
    }

    public function test_reconcile_consignment_stock_count_converts_shortage_to_unbilled_sale(): void
    {
        $stock = ConsignmentStock::create([
            'distributor_id' => (string) $this->distributor->id,
            'product_id' => 101,
            'sku' => 'PRD-SNACK-01',
            'qty' => 100.0,
            'qty_sold_unbilled' => 0.0,
            'value_idr' => 1500000,
        ]);

        $action = app(ReconcileConsignmentStockCountAction::class);
        $res = $action->execute(
            consignmentStock: $stock,
            physicalCountQty: 80.0, // Shortage of 20 units
            userId: (int) $this->user->id
        );

        $this->assertSame(20.0, $res['variance_qty']);
        $this->assertNotNull($res['sale']);
        $this->assertSame('unbilled', $res['sale']->status);
        $this->assertSame(20.0, (float) $res['sale']->qty);
        $this->assertSame(80.0, (float) $res['stock']->qty);
        $this->assertSame(20.0, (float) $res['stock']->qty_sold_unbilled);
    }

    public function test_reconcile_consignment_stock_count_no_variance_when_match(): void
    {
        $stock = ConsignmentStock::create([
            'distributor_id' => (string) $this->distributor->id,
            'product_id' => 102,
            'sku' => 'PRD-SNACK-02',
            'qty' => 50.0,
            'qty_sold_unbilled' => 0.0,
            'value_idr' => 1000000,
        ]);

        $action = app(ReconcileConsignmentStockCountAction::class);
        $res = $action->execute(
            consignmentStock: $stock,
            physicalCountQty: 50.0
        );

        $this->assertSame(0.0, $res['variance_qty']);
        $this->assertNull($res['sale']);
    }

    public function test_evaluate_waterfall_promo_stacking_calculates_pocket_price(): void
    {
        $action = app(EvaluateWaterfallPromoStackingAction::class);

        // List 100,000 | Promo 5% (5,000) -> 95,000 | Volume 3% (2,850) -> 92,150 | Cash 2% (1,843) -> 90,307
        // HPP: 60,000. Min margin 15% = 69,000. Pocket price 90,307 > 69,000, no clamping.
        $res = $action->execute(
            sku: 'SKU-BEV-01',
            channel: 'modern_trade',
            listPriceIdr: 100000,
            baseHppIdr: 60000,
            promoBps: 500,
            volumeBps: 300,
            cashDiscountBps: 200,
            minMarginThresholdPct: 15.0
        );

        $this->assertSame(100000, $res['list_price_idr']);
        $this->assertSame(5000, $res['promo_discount_idr']);
        $this->assertSame(90307, $res['pocket_price_idr']);
        $this->assertSame('waterfall_stacked_promo', $res['waterfall_log']->applied_driver);
    }

    public function test_evaluate_waterfall_promo_stacking_clamps_to_margin_floor_when_breached(): void
    {
        $action = app(EvaluateWaterfallPromoStackingAction::class);

        // Excessive discounts that would breach minimum margin
        // List 100,000 | Promo 30% -> 70,000 | Volume 20% -> 56,000 | Cash 10% -> 50,400
        // HPP: 60,000. Min margin 20% floor = 72,000. Pocket price clamped to 72,000!
        $res = $action->execute(
            sku: 'SKU-BEV-02',
            channel: 'general_trade',
            listPriceIdr: 100000,
            baseHppIdr: 60000,
            promoBps: 3000,
            volumeBps: 2000,
            cashDiscountBps: 1000,
            minMarginThresholdPct: 20.0
        );

        $this->assertSame(72000, $res['pocket_price_idr']);
        $this->assertSame('guardrail_floor_clamped', $res['waterfall_log']->applied_driver);
    }

    public function test_settle_off_invoice_rebate_claim_settles_valid_claim(): void
    {
        $promo = Promotion::create([
            'code' => 'PRM-Q3-INCENTIVE',
            'name' => 'Q3 Volume Incentive Promo',
            'mechanic' => 'off_invoice',
            'budget_idr' => 100000000,
            'valid_from' => now()->subDays(30)->toDateString(),
            'valid_until' => now()->addDays(30)->toDateString(),
            'is_active' => true,
        ]);

        $claim = PromotionClaim::create([
            'promotion_id' => (string) $promo->id,
            'distributor_id' => (string) $this->distributor->id,
            'source_ref' => 'Q3-TARGET-CLAIM',
            'amount_idr' => 15000000,
            'status' => 'submitted',
            'evidence_note' => 'Target tercapai 110%',
        ]);

        $action = app(SettleOffInvoiceRebateClaimAction::class);
        $res = $action->execute(
            claim: $claim,
            approvedAmountIdr: 15000000,
            settlementNotes: 'Disetujui komite penjualan'
        );

        $this->assertSame('settled', $res['status']);
        $this->assertSame(15000000, $res['settled_amount_idr']);
        $this->assertSame('settled', $claim->fresh()->status);
    }

    public function test_settle_off_invoice_rebate_claim_rejects_already_settled_claim(): void
    {
        $promo = Promotion::create([
            'code' => 'PRM-Q2-SETTLED',
            'name' => 'Q2 Closed Promo',
            'mechanic' => 'off_invoice',
            'budget_idr' => 50000000,
            'valid_from' => now()->subDays(60)->toDateString(),
            'valid_until' => now()->subDays(10)->toDateString(),
            'is_active' => true,
        ]);

        $claim = PromotionClaim::create([
            'promotion_id' => (string) $promo->id,
            'distributor_id' => (string) $this->distributor->id,
            'source_ref' => 'Q2-ALREADY-SETTLED',
            'amount_idr' => 10000000,
            'status' => 'settled',
        ]);

        $this->expectException(\InvalidArgumentException::class);

        $action = app(SettleOffInvoiceRebateClaimAction::class);
        $action->execute($claim, 10000000);
    }

    public function test_calculate_multi_tier_agent_commission_creates_direct_and_upline_accruals(): void
    {
        $parentAgent = Agent::create([
            'code' => 'AGY-UPLINE-01',
            'name' => 'Master Agent Jawa Barat',
            'kind' => 'sales_agent',
            'status' => 'active',
        ]);

        $agent = Agent::create([
            'code' => 'AGY-DIRECT-01',
            'name' => 'Sub Agent Bandung',
            'kind' => 'sales_agent',
            'status' => 'active',
            'parent_id' => (string) $parentAgent->id,
        ]);

        $action = app(CalculateMultiTierAgentCommissionAction::class);
        $res = $action->execute(
            agent: $agent,
            referenceId: 'SO-AGY-001',
            orderValueIdr: 50000000,
            directRatePercent: 8.0,   // 4,000,000
            uplineOverridePercent: 2.0, // 1,000,000
            holdDays: 14
        );

        $this->assertSame(4000000, (int) $res['direct_accrual']->amount_idr);
        $this->assertSame('hold', $res['direct_accrual']->status);
        $this->assertNotNull($res['upline_accrual']);
        $this->assertSame(1000000, (int) $res['upline_accrual']->amount_idr);
        $this->assertSame('hold', $res['upline_accrual']->status);
        $this->assertSame(5000000, $res['total_commission_idr']);
    }

    public function test_release_clawback_hold_commission_transitions_hold_to_payable_and_clawback(): void
    {
        $agent = Agent::create([
            'code' => 'AGY-DIRECT-02',
            'name' => 'Agent Surabaya',
            'kind' => 'sales_agent',
            'status' => 'active',
        ]);

        $accrual1 = CommissionAccrual::create([
            'agent_id' => (string) $agent->id,
            'reference_id' => 'SO-PAYABLE-01',
            'source_type' => 'sales_order',
            'accrued_at' => now()->subDays(15)->toDateString(),
            'base_amount_idr' => 10000000,
            'rate_percent' => 5.0,
            'amount_idr' => 500000,
            'status' => 'hold',
            'hold_until' => now()->subDay()->toDateString(),
        ]);

        $accrual2 = CommissionAccrual::create([
            'agent_id' => (string) $agent->id,
            'reference_id' => 'SO-RETURN-01',
            'source_type' => 'sales_order',
            'accrued_at' => now()->subDays(5)->toDateString(),
            'base_amount_idr' => 20000000,
            'rate_percent' => 5.0,
            'amount_idr' => 1000000,
            'status' => 'hold',
            'hold_until' => now()->addDays(9)->toDateString(),
        ]);

        $action = app(ReleaseClawbackHoldCommissionAction::class);

        // Case 1: Release to payable
        $resPayable = $action->execute($accrual1, isClawback: false);
        $this->assertSame('payable', $resPayable['status']);
        $this->assertSame('payable', $accrual1->fresh()->status);

        // Case 2: Clawback due to customer return
        $resClawback = $action->execute($accrual2, isClawback: true, reason: 'Barang diretur cacat');
        $this->assertSame('clawback', $resClawback['status']);
        $this->assertSame('clawback', $accrual2->fresh()->status);
    }
}
