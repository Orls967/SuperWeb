<?php

declare(strict_types=1);

namespace Tests\Acceptance\Retail;

use App\Models\User;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Ret\Application\Actions\AllocateDarkStorePickPackAction;
use Modules\Ret\Application\Actions\CompleteDarkStoreDeliveryAction;
use Modules\Ret\Application\Actions\PlanOmnichannelFulfillmentAction;
use Modules\Ret\Domain\Exceptions\InvalidFulfillmentStatusTransitionException;
use Modules\Ret\Domain\Models\RetChannel;
use Modules\Ret\Domain\Models\RetFulfillmentSplit;
use Modules\Ret\Domain\Models\RetOrder;
use Modules\Ret\Domain\Models\RetQuickCommerceOrder;
use Tests\TestCase;

class DarkStoreFulfillmentAndSlaTest extends TestCase
{
    use RefreshDatabase;

    protected User $customer;

    protected RetChannel $channel;

    protected RetOrder $order;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);

        $this->customer = User::factory()->create(['role' => 'customer']);

        $this->channel = RetChannel::create([
            'id' => (string) Str::uuid(),
            'channel_code' => 'QCOMMERCE_APP',
            'channel_name' => 'Quick Commerce 30m',
            'channel_type' => 'WEB',
            'default_commission_bps' => 500,
            'status' => 'ACTIVE',
        ]);

        $this->order = RetOrder::create([
            'id' => (string) Str::uuid(),
            'order_number' => 'ORD-20261031-000101',
            'customer_id' => $this->customer->id,
            'channel_id' => $this->channel->id,
            'total_amount_minor' => 150_000,
            'commission_amount_minor' => 7_500,
            'status' => 'PAID',
            'idempotency_key' => 'ret:order:setup-01',
        ]);
    }

    public function test_fulfillment_planner_partitions_multi_item_order_across_nearest_nodes(): void
    {
        $plannerAction = app(PlanOmnichannelFulfillmentAction::class);

        $plan = $plannerAction->execute([
            'order_id' => $this->order->id,
            'items' => [
                ['sku' => 'SKU-FRESH-MILK', 'quantity' => 2],
                ['sku' => 'SKU-ICE-CREAM', 'quantity' => 1],
            ],
            'customer_coordinates' => ['lat' => -6.2088, 'lng' => 106.8456],
        ]);

        $this->assertNotEmpty($plan['splits']);
        $this->assertDatabaseHas('ret_fulfillment_splits', [
            'order_id' => $this->order->id,
            'status' => 'PLANNED',
        ]);
    }

    public function test_order_allocation_to_dark_store_creates_pick_pack_task(): void
    {
        $action = app(AllocateDarkStorePickPackAction::class);

        $qcOrder = $action->execute([
            'order_id' => $this->order->id,
            'dark_store_id' => 'DS-KUNINGAN-01',
            'picking_duration_seconds' => 180,
            'idempotency_key' => 'ret:qc:alloc-01',
        ]);

        $this->assertInstanceOf(RetQuickCommerceOrder::class, $qcOrder);
        $this->assertEquals('PICKING', $qcOrder->status);
        $this->assertEquals(180, $qcOrder->picking_duration_seconds);
        $this->assertFalse($qcOrder->is_sla_breached);
    }

    public function test_delivery_within_promised_sla_window_passes_sla_check(): void
    {
        $action = app(AllocateDarkStorePickPackAction::class);
        $qcOrder = $action->execute([
            'order_id' => $this->order->id,
            'dark_store_id' => 'DS-KUNINGAN-01',
            'picking_duration_seconds' => 200,
            'idempotency_key' => 'ret:qc:alloc-02',
        ]);

        $completeAction = app(CompleteDarkStoreDeliveryAction::class);
        $deliveredOrder = $completeAction->execute([
            'qc_order_id' => $qcOrder->id,
            'delivered_at' => now()->addMinutes(25), // 25 mins <= 30 mins SLA
            'pod_signature_hash' => hash('sha256', "POD:{$qcOrder->id}:SUCCESS"),
            'idempotency_key' => 'ret:qc:complete-01',
        ]);

        $this->assertEquals('DELIVERED', $deliveredOrder->status);
        $this->assertFalse($deliveredOrder->is_sla_breached);
        $this->assertEquals(0, $deliveredOrder->compensation_credit_minor);
    }

    public function test_sla_breach_triggers_automatic_customer_compensation_voucher(): void
    {
        $action = app(AllocateDarkStorePickPackAction::class);
        $qcOrder = $action->execute([
            'order_id' => $this->order->id,
            'dark_store_id' => 'DS-KUNINGAN-01',
            'picking_duration_seconds' => 300,
            'idempotency_key' => 'ret:qc:alloc-03',
        ]);

        $completeAction = app(CompleteDarkStoreDeliveryAction::class);
        $deliveredOrder = $completeAction->execute([
            'qc_order_id' => $qcOrder->id,
            'delivered_at' => now()->addMinutes(45), // 45 mins > 30 mins SLA breach!
            'pod_signature_hash' => hash('sha256', "POD:{$qcOrder->id}:LATE"),
            'idempotency_key' => 'ret:qc:complete-02',
        ]);

        $this->assertEquals('DELIVERED', $deliveredOrder->status);
        $this->assertTrue($deliveredOrder->is_sla_breached);
        $this->assertEquals(25_000, $deliveredOrder->compensation_credit_minor); // 25,000 compensation voucher
    }

    public function test_compensation_credit_is_awarded_only_once_on_retry(): void
    {
        $action = app(AllocateDarkStorePickPackAction::class);
        $qcOrder = $action->execute([
            'order_id' => $this->order->id,
            'dark_store_id' => 'DS-KUNINGAN-01',
            'picking_duration_seconds' => 300,
            'idempotency_key' => 'ret:qc:alloc-04',
        ]);

        $completeAction = app(CompleteDarkStoreDeliveryAction::class);
        $key = 'ret:qc:complete-dup-01';

        $res1 = $completeAction->execute([
            'qc_order_id' => $qcOrder->id,
            'delivered_at' => now()->addMinutes(45),
            'pod_signature_hash' => hash('sha256', "POD:{$qcOrder->id}:LATE"),
            'idempotency_key' => $key,
        ]);

        $res2 = $completeAction->execute([
            'qc_order_id' => $qcOrder->id,
            'delivered_at' => now()->addMinutes(45),
            'pod_signature_hash' => hash('sha256', "POD:{$qcOrder->id}:LATE"),
            'idempotency_key' => $key,
        ]);

        $this->assertEquals($res1->compensation_credit_minor, $res2->compensation_credit_minor);
        $this->assertEquals(25_000, $res2->compensation_credit_minor);
    }

    public function test_status_transitions_strictly_enforced(): void
    {
        $split = RetFulfillmentSplit::create([
            'id' => (string) Str::uuid(),
            'order_id' => $this->order->id,
            'source_location_id' => 'DS-01',
            'source_type' => 'DARK_STORE',
            'allocated_quantity' => 1,
            'status' => 'PLANNED',
        ]);

        // PLANNED to PICKING is valid
        $split->transitionTo('PICKING');
        $this->assertEquals('PICKING', $split->status);

        // PICKING to SHIPPED is valid
        $split->transitionTo('SHIPPED');
        $this->assertEquals('SHIPPED', $split->status);

        // SHIPPED to DELIVERED is valid
        $split->transitionTo('DELIVERED');
        $this->assertEquals('DELIVERED', $split->status);
    }

    public function test_invalid_status_jump_throws_exception(): void
    {
        $split = RetFulfillmentSplit::create([
            'id' => (string) Str::uuid(),
            'order_id' => $this->order->id,
            'source_location_id' => 'DS-01',
            'source_type' => 'DARK_STORE',
            'allocated_quantity' => 1,
            'status' => 'PLANNED',
        ]);

        // Jumping from PLANNED directly to DELIVERED is forbidden
        $this->expectException(InvalidFulfillmentStatusTransitionException::class);
        $split->transitionTo('DELIVERED');
    }

    public function test_pod_signature_hash_verification(): void
    {
        $action = app(AllocateDarkStorePickPackAction::class);
        $qcOrder = $action->execute([
            'order_id' => $this->order->id,
            'dark_store_id' => 'DS-KUNINGAN-01',
            'picking_duration_seconds' => 150,
            'idempotency_key' => 'ret:qc:alloc-05',
        ]);

        $completeAction = app(CompleteDarkStoreDeliveryAction::class);

        $this->expectException(\InvalidArgumentException::class);
        $completeAction->execute([
            'qc_order_id' => $qcOrder->id,
            'delivered_at' => now()->addMinutes(20),
            'pod_signature_hash' => 'invalid-short-hash', // hash must be 64-char sha256
            'idempotency_key' => 'ret:qc:complete-badpod',
        ]);
    }

    public function test_crowdshipping_task_assignment(): void
    {
        $action = app(AllocateDarkStorePickPackAction::class);
        $qcOrder = $action->execute([
            'order_id' => $this->order->id,
            'dark_store_id' => 'DS-KUNINGAN-01',
            'picking_duration_seconds' => 120,
            'driver_type' => 'CROWDSHIPPING',
            'driver_user_id' => 'user-driver-77',
            'idempotency_key' => 'ret:qc:alloc-crowd-01',
        ]);

        $this->assertEquals('CROWDSHIPPING', $qcOrder->driver_type);
        $this->assertEquals('user-driver-77', $qcOrder->driver_user_id);
    }
}
