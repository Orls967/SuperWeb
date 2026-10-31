<?php

declare(strict_types=1);

namespace Tests\Acceptance\Retail\Eksplorasi;

use Carbon\Carbon;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Ret\Application\Actions\AllocateDarkStorePickPackOrderAction;
use Modules\Ret\Application\Actions\AuditShelfPlanogramComplianceAction;
use Modules\Ret\Application\Actions\CheckoutOmnichannelUnifiedCartAction;
use Modules\Ret\Application\Actions\ExecuteDynamicExpiryMarkdownPricingAction;
use Modules\Ret\Application\Actions\ProcessClickAndCollectLockerPickupAction;
use Modules\Ret\Application\Actions\ReconcileStoreShrinkageAndInventoryAction;
use Modules\Ret\Domain\Models\RetChannel;
use Modules\Ret\Domain\Models\RetInventoryItem;
use Modules\Ret\Domain\Models\RetOrder;
use Tests\TestCase;

/**
 * L17.0: Buktikan kondisi kode lama Lini 17 (Ritel & E-Commerce).
 *
 * Klasifikasi Action Lama:
 * 1. CheckoutOmnichannelUnifiedCartAction: SEBAGIAN (ada transaksi & ledger, tapi kelola stok sendiri bukan via InventoryService, microtime di nomor pesanan, key idempotensi hash tanpa format standar, tanpa escrow seller).
 * 2. AllocateDarkStorePickPackOrderAction: KERANGKA (langsung DELIVERED tanpa verifikasi SLA riil, microtime di kode order).
 * 3. ProcessClickAndCollectLockerPickupAction: BENAR (transisi status FULFILLED dengan validasi token/status).
 * 4. ExecuteDynamicExpiryMarkdownPricingAction: SALAH (mengubah MAP price langsung alih-alih harga jual dinamis/diskon promosi).
 * 5. ReconcileStoreShrinkageAndInventoryAction: SEBAGIAN (ledger write-off ada, tapi mutasi inventaris langsung di model Ret alih-alih InventoryService).
 * 6. AuditShelfPlanogramComplianceAction: BENAR (logika rasio compliance planogram akurat).
 */
class RetailExplorationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);
    }

    public function test_explores_checkout_omnichannel_unified_cart_action(): void
    {
        $channel = RetChannel::create([
            'id' => 'chan-test-01',
            'channel_code' => 'WEB_STORE',
            'channel_name' => 'Flagship Web Store',
            'channel_type' => 'WEB',
            'status' => 'ACTIVE',
        ]);

        $item = RetInventoryItem::create([
            'id' => 'item-test-01',
            'sku' => 'SKU-SAMPLE-01',
            'product_name' => 'Sample Product',
            'stock_available' => 100,
            'stock_reserved' => 0,
            'map_price_minor' => 150_000,
        ]);

        $action = app(CheckoutOmnichannelUnifiedCartAction::class);
        $order = $action->execute([
            'channel_id' => $channel->id,
            'customer_id' => 'cust-123',
            'items' => [
                ['sku' => 'SKU-SAMPLE-01', 'quantity' => 2],
            ],
        ]);

        $this->assertInstanceOf(RetOrder::class, $order);
        $this->assertEquals(300_000, $order->total_amount_minor);
        $this->assertEquals('PAID', $order->status);

        $item->refresh();
        $this->assertEquals(98, $item->stock_available);
        $this->assertEquals(2, $item->stock_reserved);
    }

    public function test_explores_allocate_dark_store_pick_pack_order_action(): void
    {
        $action = app(AllocateDarkStorePickPackOrderAction::class);
        $qcOrder = $action->execute([
            'dark_store_id' => 'DS-JAKSEL-01',
            'customer_id' => 'cust-456',
            'placed_at' => Carbon::now(),
            'picking_duration_seconds' => 240,
        ]);

        $this->assertNotNull($qcOrder->id);
        $this->assertStringStartsWith('QC-', $qcOrder->order_code);
        $this->assertEquals('DELIVERED', $qcOrder->status);
    }

    public function test_explores_process_click_and_collect_locker_pickup_action(): void
    {
        $order = RetOrder::create([
            'id' => 'ord-cc-01',
            'order_number' => 'ORD-PICKUP-01',
            'customer_id' => 'cust-789',
            'channel_id' => 'chan-test-01',
            'total_amount_minor' => 100_000,
            'status' => 'READY_FOR_PICKUP',
        ]);

        $action = app(ProcessClickAndCollectLockerPickupAction::class);
        $res = $action->execute($order->order_number, 'LOCKER-B12', '9821');

        $this->assertEquals('PICKED_UP', $res['pickup_status']);
        $order->refresh();
        $this->assertEquals('FULFILLED', $order->status);
    }

    public function test_explores_execute_dynamic_expiry_markdown_pricing_action(): void
    {
        $item = RetInventoryItem::create([
            'id' => 'item-exp-01',
            'sku' => 'SKU-EXP-01',
            'product_name' => 'Perishable Fresh Milk',
            'stock_available' => 50,
            'stock_reserved' => 0,
            'map_price_minor' => 40_000,
        ]);

        $action = app(ExecuteDynamicExpiryMarkdownPricingAction::class);
        $res = $action->execute('SKU-EXP-01', 1);

        $this->assertEquals(70, $res['discount_pct']);
        $this->assertEquals(12_000, $res['marked_down_price']);
    }

    public function test_explores_reconcile_store_shrinkage_and_inventory_action(): void
    {
        $item = RetInventoryItem::create([
            'id' => 'item-shrink-01',
            'sku' => 'SKU-SHRINK-01',
            'product_name' => 'Pack of Batteries',
            'stock_available' => 10,
            'stock_reserved' => 0,
            'map_price_minor' => 25_000,
        ]);

        $action = app(ReconcileStoreShrinkageAndInventoryAction::class);
        $res = $action->execute('SKU-SHRINK-01', 8);

        $this->assertEquals(2, $res['shrinkage_qty']);
        $this->assertEquals(50_000, $res['write_off_minor']);

        $item->refresh();
        $this->assertEquals(8, $item->stock_available);
    }

    public function test_explores_audit_shelf_planogram_compliance_action(): void
    {
        $action = app(AuditShelfPlanogramComplianceAction::class);
        $res = $action->execute([
            'shelf_id' => 'SHELF-A1-04',
            'expected_facings' => 10,
            'detected_facings' => 9,
            'out_of_stock_detected' => 1,
        ]);

        $this->assertEquals(80, $res['compliance_score_pct']);
        $this->assertTrue($res['is_compliant']);
        $this->assertEquals('COMPLIANT', $res['status']);
    }
}
