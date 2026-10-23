<?php

declare(strict_types=1);

namespace Modules\Ret\tests\Feature;

use App\Models\User;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Modules\Ret\Application\Actions\AllocateDarkStorePickPackOrderAction;
use Modules\Ret\Application\Actions\AuditShelfPlanogramComplianceAction;
use Modules\Ret\Application\Actions\CheckoutOmnichannelUnifiedCartAction;
use Modules\Ret\Application\Actions\ExecuteDynamicExpiryMarkdownPricingAction;
use Modules\Ret\Application\Actions\ProcessClickAndCollectLockerPickupAction;
use Modules\Ret\Application\Actions\ReconcileStoreShrinkageAndInventoryAction;
use Modules\Ret\Domain\Models\RetChannel;
use Modules\Ret\Domain\Models\RetInventoryItem;
use RuntimeException;
use Tests\TestCase;

class RetVerticalSliceHttpAndDomainTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);
    }

    private function createSampleChannelAndItem(): array
    {
        $channel = RetChannel::create([
            'id' => (string) Str::uuid(),
            'channel_code' => 'CHN-PHYSICAL-'.Str::random(4),
            'channel_name' => 'Grand Indonesia Flagship Store',
            'channel_type' => 'PHYSICAL_STORE',
            'default_commission_pct' => 5.0,
            'status' => 'ACTIVE',
        ]);

        $item = RetInventoryItem::create([
            'id' => (string) Str::uuid(),
            'sku' => 'SKU-BEV-'.Str::random(5),
            'product_name' => 'Artisanal Cold Brew Coffee 250ml',
            'stock_available' => 100,
            'stock_reserved' => 0,
            'map_price_minor' => 35000,
        ]);

        return [$channel, $item];
    }

    public function test_1_authorized_user_can_access_ret_orders_index(): void
    {
        [$channel, $item] = $this->createSampleChannelAndItem();
        $manager = User::factory()->create(['role' => 'retail_ops']);

        $response = $this->actingAs($manager)->get(route('ret.orders.index'));
        $response->assertOk();
        $response->assertSeeText('Omnichannel Retail Orders & Unified Cart');
        $response->assertSeeText($channel->channel_name);
    }

    public function test_2_checkout_omnichannel_unified_cart_via_http(): void
    {
        [$channel, $item] = $this->createSampleChannelAndItem();
        $manager = User::factory()->create(['role' => 'retail_ops']);

        $response = $this->actingAs($manager)->post(route('ret.orders.checkout'), [
            'channel_id' => $channel->id,
            'customer_id' => 'CUST-OMNI-01',
            'items' => [
                ['sku' => $item->sku, 'quantity' => 2],
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertSame(98, $item->fresh()->stock_available);
        $this->assertSame(2, $item->fresh()->stock_reserved);

        $this->assertDatabaseHas('bank_ledger_transactions', [
            'type' => 'RETAIL_OMNICHANNEL_CHECKOUT',
        ]);
    }

    public function test_3_checkout_rejected_when_stock_is_insufficient(): void
    {
        [$channel, $item] = $this->createSampleChannelAndItem();
        $action = app(CheckoutOmnichannelUnifiedCartAction::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Insufficient stock for SKU');

        $action->execute([
            'channel_id' => $channel->id,
            'customer_id' => 'CUST-FAIL',
            'items' => [
                ['sku' => $item->sku, 'quantity' => 250], // Available is only 100
            ],
        ]);
    }

    public function test_4_allocate_dark_store_pick_pack_order(): void
    {
        $action = app(AllocateDarkStorePickPackOrderAction::class);
        $order = $action->execute([
            'dark_store_id' => 'DARKSTORE-SCBD-01',
            'customer_id' => 'CUST-EXP-99',
            'picking_duration_seconds' => 280,
        ]);

        $this->assertSame('DARKSTORE-SCBD-01', $order->dark_store_id);
        $this->assertSame('DELIVERED', $order->status);
        $this->assertFalse($order->is_sla_breached);
    }

    public function test_5_execute_dynamic_expiry_markdown_pricing(): void
    {
        [$channel, $item] = $this->createSampleChannelAndItem();
        $action = app(ExecuteDynamicExpiryMarkdownPricingAction::class);

        // 2 days remaining -> 50% discount
        $result = $action->execute($item->sku, 2);

        $this->assertSame(50, $result['discount_pct']);
        $this->assertSame(17500, $result['marked_down_price']); // 35000 / 2
        $this->assertSame(17500, $item->fresh()->map_price_minor);
    }

    public function test_6_audit_shelf_planogram_compliance(): void
    {
        $action = app(AuditShelfPlanogramComplianceAction::class);
        $result = $action->execute([
            'shelf_id' => 'SHELF-DAIRY-01',
            'expected_facings' => 20,
            'detected_facings' => 19,
            'out_of_stock_detected' => 1,
        ]);

        $this->assertTrue($result['is_compliant']);
        $this->assertSame(90, $result['compliance_score_pct']); // (19-1)/20 = 90%
        $this->assertSame('COMPLIANT', $result['status']);
    }

    public function test_7_process_click_and_collect_locker_pickup(): void
    {
        [$channel, $item] = $this->createSampleChannelAndItem();
        $order = app(CheckoutOmnichannelUnifiedCartAction::class)->execute([
            'channel_id' => $channel->id,
            'customer_id' => 'CUST-LOCKER-USER',
            'items' => [
                ['sku' => $item->sku, 'quantity' => 1],
            ],
        ]);

        $action = app(ProcessClickAndCollectLockerPickupAction::class);
        $pickup = $action->execute($order->order_number, 'LOCKER-KUNINGAN-03', '9981');

        $this->assertSame('PICKED_UP', $pickup['pickup_status']);
        $this->assertSame('FULFILLED', $order->fresh()->status);
    }

    public function test_8_reconcile_store_shrinkage_and_audit_command(): void
    {
        [$channel, $item] = $this->createSampleChannelAndItem();

        $action = app(ReconcileStoreShrinkageAndInventoryAction::class);
        $result = $action->execute($item->sku, 95); // 5 units lost

        $this->assertSame(5, $result['shrinkage_qty']);
        $this->assertSame(175000, $result['write_off_minor']); // 5 * 35000
        $this->assertSame(95, $item->fresh()->stock_available);

        $this->assertDatabaseHas('bank_ledger_transactions', [
            'type' => 'RETAIL_SHRINKAGE_WRITE_OFF',
            'reference_id' => $item->sku,
        ]);

        $exitCode = Artisan::call('ret:audit');
        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Retail audit PASSED with 0 discrepancy', Artisan::output());

        // Unauthorized user 403
        $customer = User::factory()->create(['role' => 'customer']);
        $this->actingAs($customer)->get(route('ret.orders.index'))->assertForbidden();

        // Unauthenticated user 302
        auth()->logout();
        $this->get(route('ret.orders.index'))->assertRedirect(route('login'));
    }
}
