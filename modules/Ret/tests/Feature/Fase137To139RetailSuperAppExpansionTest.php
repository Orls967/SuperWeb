<?php

declare(strict_types=1);

namespace Modules\Ret\tests\Feature;

use Database\Seeders\LedgerAccountsSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Banking\Domain\Models\LedgerTransaction;
use Modules\Ret\Application\Actions\DispatchQuickCommerce15MinDarkStoreGridAction;
use Modules\Ret\Application\Actions\RedeemCrossLineSuperAppLoyaltyCashbackAction;
use Modules\Ret\Application\Actions\SettleOmnichannelMarketplaceGroupMerchantSplitAction;
use Tests\TestCase;

class Fase137To139RetailSuperAppExpansionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);
    }

    public function test_fase_137_marketplace_merchant_settlement_and_anti_double_sell_stock_locking(): void
    {
        $action = app(SettleOmnichannelMarketplaceGroupMerchantSplitAction::class);

        // Gross total: 5,000,000. Commission: 15% = 750,000. Merchant payable: 4,250,000.
        $params = [
            'order_code' => 'ORD-MKT-001',
            'merchant_id' => 'MERCHANT-BATIK-SOLO',
            'gross_order_total_minor' => 5000000,
            'category_commission_pct' => 15,
            'line_items' => [
                ['sku' => 'SKU-BATIK-SILK', 'requested_qty' => 5, 'available_stock' => 10, 'source_location' => 'STORE-SOLO-01'],
                ['sku' => 'SKU-BATIK-COTTON', 'requested_qty' => 10, 'available_stock' => 20, 'source_location' => 'WMS-SURABAYA'],
            ],
        ];

        $res = $action->execute($params);

        $this->assertEquals(5000000, $res['net_order_total_minor']);
        $this->assertEquals(750000, $res['commission_minor']);
        $this->assertEquals(4250000, $res['merchant_payable_minor']);
        $this->assertCount(2, $res['allocated_items']);

        $tx = LedgerTransaction::where('reference_id', 'ORD-MKT-001')->first();
        $this->assertNotNull($tx);
        $this->assertEquals(0, $tx->entries->sum('amount_minor'));
    }

    public function test_fase_137_coupon_single_use_idempotency_and_order_split_fulfillment(): void
    {
        $action = app(SettleOmnichannelMarketplaceGroupMerchantSplitAction::class);

        // Shortage scenario: requested > available -> exception
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Omnichannel inventory shortage');

        $action->execute([
            'order_code' => 'ORD-SHORTAGE',
            'merchant_id' => 'MKT-01',
            'line_items' => [
                ['sku' => 'SKU-LAPTOP-PRO', 'requested_qty' => 10, 'available_stock' => 2, 'source_location' => 'STORE-CENTRAL'],
            ],
        ]);
    }

    public function test_fase_138_cross_line_cashback_earning_and_liability_ledger_balance(): void
    {
        $action = app(RedeemCrossLineSuperAppLoyaltyCashbackAction::class);

        // Earn cashback: 1,000,000 purchase @ 5% = 50,000. Cap is 50,000.
        $params = [
            'transaction_code' => 'CB-EARN-001',
            'customer_id' => 'CUST-LOYAL-01',
            'mode' => 'EARN',
            'purchase_amount_minor' => 1000000,
            'cashback_rate_pct' => 5,
            'max_cashback_cap_minor' => 50000,
        ];

        $res = $action->execute($params);

        $this->assertEquals(50000, $res['cashback_issued_minor']);
        $this->assertStringStartsWith('RCP-', $res['gapless_receipt_token']);

        $tx = LedgerTransaction::where('reference_id', 'CB-EARN-001')->first();
        $this->assertNotNull($tx);
        $this->assertEquals(0, $tx->entries->sum('amount_minor'));

        // Redeem cashback: 30,000 from 50,000 available
        $redeemRes = $action->execute([
            'transaction_code' => 'CB-REDEEM-002',
            'customer_id' => 'CUST-LOYAL-01',
            'mode' => 'REDEEM',
            'redeem_points_minor' => 30000,
            'available_cashback_minor' => 50000,
        ]);

        $this->assertEquals(30000, $redeemRes['cashback_redeemed_minor']);
        $txRedeem = LedgerTransaction::where('reference_id', 'CB-REDEEM-002')->first();
        $this->assertNotNull($txRedeem);
        $this->assertEquals(0, $txRedeem->entries->sum('amount_minor'));
    }

    public function test_fase_138_super_app_subscription_bundle_settlement_across_lines(): void
    {
        $action = app(RedeemCrossLineSuperAppLoyaltyCashbackAction::class);

        // Bundle: 1,500,000 total = HOTEL (500k) + MEDIA (300k) + TLX (700k)
        $params = [
            'transaction_code' => 'BUNDLE-FAMILY-001',
            'customer_id' => 'CUST-CORP-VIP',
            'mode' => 'BUNDLE_SETTLEMENT',
            'bundle_price_minor' => 1500000,
            'line_splits' => [
                'HOTEL' => 500000,
                'MEDIA' => 300000,
                'TLX' => 700000,
            ],
        ];

        $res = $action->execute($params);

        $this->assertEquals(1500000, $res['bundle_settlement_minor']);

        $tx = LedgerTransaction::where('reference_id', 'BUNDLE-FAMILY-001')->first();
        $this->assertNotNull($tx);
        $this->assertEquals(0, $tx->entries->sum('amount_minor'));
    }

    public function test_fase_139_quick_commerce_dark_store_grid_dispatch_within_radius(): void
    {
        $action = app(DispatchQuickCommerce15MinDarkStoreGridAction::class);

        // 2 km <= 3 km max radius -> success
        $params = [
            'dispatch_code' => 'QC-GRID-OK-01',
            'dark_store_id' => 'DS-JAKARTA-SELATAN',
            'delivery_distance_km' => 2,
            'promised_delivery_minutes' => 15,
            'actual_delivery_minutes' => 13,
            'packaging_mode' => 'ISSUE',
            'packaging_deposit_minor' => 15000,
            'order_value_minor' => 100000,
            'crowdshipper_fee_minor' => 12000,
        ];

        $res = $action->execute($params);

        $this->assertFalse($res['sla_breached']);
        $this->assertEquals(0, $res['sla_compensation_minor']);
        $this->assertEquals('DISPATCHED', $res['dispatch_status']);

        $tx = LedgerTransaction::where('reference_id', 'QC-GRID-OK-01')->first();
        $this->assertNotNull($tx);
        $this->assertEquals(0, $tx->entries->sum('amount_minor'));
    }

    public function test_fase_139_fulfillment_sla_breach_auto_credit_and_reusable_packaging_loop(): void
    {
        $action = app(DispatchQuickCommerce15MinDarkStoreGridAction::class);

        // Actual 19 min > 15 min promised -> SLA breach with 25,000 voucher
        $params = [
            'dispatch_code' => 'QC-DELAY-002',
            'dark_store_id' => 'DS-BANDUNG-01',
            'delivery_distance_km' => 2,
            'promised_delivery_minutes' => 15,
            'actual_delivery_minutes' => 19,
            'delay_voucher_amount_minor' => 25000,
            'packaging_mode' => 'RETURN',
            'packaging_deposit_minor' => 15000,
            'order_value_minor' => 80000,
            'crowdshipper_fee_minor' => 10000,
        ];

        $res = $action->execute($params);

        $this->assertTrue($res['sla_breached']);
        $this->assertEquals(25000, $res['sla_compensation_minor']);

        $txDelay = LedgerTransaction::where('reference_id', 'QC-DELAY-002')->where('type', 'RETAIL_SLA_BREACH_COMPENSATION')->first();
        $this->assertNotNull($txDelay);
        $this->assertEquals(0, $txDelay->entries->sum('amount_minor'));

        $txPkg = LedgerTransaction::where('reference_id', 'QC-DELAY-002')->where('type', 'RETAIL_PACKAGING_DEPOSIT_REFUND')->first();
        $this->assertNotNull($txPkg);
        $this->assertEquals(0, $txPkg->entries->sum('amount_minor'));

        // Radius breach exception test: distance 5km > 3km
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('exceeds maximum dark store grid radius');

        $action->execute([
            'dispatch_code' => 'QC-RADIUS-BREACH',
            'delivery_distance_km' => 5,
        ]);
    }
}
