<?php

declare(strict_types=1);

namespace Modules\CloudKitchen\tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\CloudKitchen\Application\Actions\AuditThermalSealTamperChainAction;
use Modules\CloudKitchen\Application\Actions\BatchCourierMultiDropDeliveryAction;
use Modules\CloudKitchen\Application\Actions\RouteVirtualBrandOrderToStationAction;
use Modules\CloudKitchen\Application\Actions\SettleVirtualBrandRevenueSplitAction;
use Modules\CloudKitchen\Domain\Models\CateringOrder;
use Modules\CloudKitchen\Domain\Models\CateringSubscription;
use Modules\CloudKitchen\Domain\Models\CloudKitchen;
use Tests\TestCase;

class CloudKitchenEnterpriseDeepeningTest extends TestCase
{
    use RefreshDatabase;

    private function createKitchenAndSubscription(): array
    {
        $kitchen = CloudKitchen::create([
            'kitchen_code' => 'CK-SCBD-01',
            'name' => 'SCBD Central Cloud Kitchen',
            'type' => 'hub',
            'hourly_capacity' => 100,
            'current_hourly_load' => 10,
            'service_radius_km' => 7.5,
            'is_active' => true,
        ]);

        $user = User::factory()->create();

        $subscription = CateringSubscription::create([
            'subscription_code' => 'SUB-HEALTHY-01',
            'user_id' => $user->id,
            'package_name' => 'Keto Meal Plan',
            'daily_quota' => 2,
            'used_quota_today' => 0,
            'monthly_fee_idr' => 1800000,
            'daily_meal_price_idr' => 45000,
            'starts_at' => now()->toDateString(),
            'expires_at' => now()->addDays(30)->toDateString(),
            'status' => 'active',
        ]);

        return [$kitchen, $subscription, $user];
    }

    public function test_route_virtual_brand_order_to_station_picks_kitchen_with_capacity(): void
    {
        [$kitchen, $subscription] = $this->createKitchenAndSubscription();
        $action = app(RouteVirtualBrandOrderToStationAction::class);

        $result = $action->handle($subscription->id, 'lunch', 45000);

        $this->assertEquals('preparing', $result['status']);
        $this->assertEquals($kitchen->id, $result['kitchen_id']);
        $this->assertEquals(11, $kitchen->fresh()->current_hourly_load);
        $this->assertDatabaseHas('resto_ck_catering_orders', [
            'subscription_id' => $subscription->id,
            'meal_slot' => 'lunch',
            'meal_cost_idr' => 45000,
        ]);
    }

    public function test_batch_courier_multi_drop_delivery_batches_orders_and_updates_status(): void
    {
        [$kitchen, $subscription] = $this->createKitchenAndSubscription();
        $routeAction = app(RouteVirtualBrandOrderToStationAction::class);

        $ord1 = $routeAction->handle($subscription->id, 'lunch', 45000, '2026-10-23')['order'];
        $ord2 = $routeAction->handle($subscription->id, 'dinner', 45000, '2026-10-23')['order'];

        $batchAction = app(BatchCourierMultiDropDeliveryAction::class);
        $result = $batchAction->handle([$ord1->id, $ord2->id], 'Courier Alpha', 15000, 5000);

        $this->assertEquals(2, $result['orders_count']);
        $this->assertEquals(25000, $result['courier_payout_idr']); // 15000 + 2*5000
        $this->assertEquals('in_transit', $ord1->fresh()->status);
        $this->assertEquals('in_transit', $ord2->fresh()->status);
    }

    public function test_settle_virtual_brand_revenue_split_allocates_exact_integer_percentages(): void
    {
        [$kitchen, $subscription] = $this->createKitchenAndSubscription();
        $order = CateringOrder::create([
            'order_code' => 'ORD-SPLIT-01',
            'subscription_id' => $subscription->id,
            'kitchen_id' => $kitchen->id,
            'delivery_date' => now()->toDateString(),
            'meal_slot' => 'lunch',
            'status' => 'in_transit',
            'meal_cost_idr' => 50000, // 50,000 IDR
            'idempotency_key' => 'idemp-split-01',
        ]);

        $action = app(SettleVirtualBrandRevenueSplitAction::class);
        $result = $action->handle($order->id);

        $this->assertEquals(30000, $result['kitchen_fee_idr']); // 60% of 50,000
        $this->assertEquals(12500, $result['brand_royalty_idr']); // 25% of 50,000
        $this->assertEquals(7500, $result['platform_fee_idr']); // 15% of 50,000
        $this->assertEquals(50000, $result['total_idr']);
        $this->assertEquals('delivered', $order->fresh()->status);
    }

    public function test_audit_thermal_seal_passes_when_temperature_and_seal_valid(): void
    {
        [$kitchen, $subscription] = $this->createKitchenAndSubscription();
        $order = CateringOrder::create([
            'order_code' => 'ORD-SEAL-01',
            'subscription_id' => $subscription->id,
            'kitchen_id' => $kitchen->id,
            'delivery_date' => now()->toDateString(),
            'meal_slot' => 'lunch',
            'status' => 'in_transit',
            'meal_cost_idr' => 45000,
            'idempotency_key' => 'idemp-seal-01',
        ]);

        $action = app(AuditThermalSealTamperChainAction::class);
        $result = $action->handle($order->id, 'SEAL-VALID-12345', 68.5, 'hot');

        $this->assertTrue($result['is_safe']);
        $this->assertTrue($result['seal_intact']);
        $this->assertEquals('HACCP_PASSED', $result['safety_verdict']);
    }

    public function test_audit_thermal_seal_flags_breach_when_temperature_unsafe(): void
    {
        [$kitchen, $subscription] = $this->createKitchenAndSubscription();
        $order = CateringOrder::create([
            'order_code' => 'ORD-BREACH-01',
            'subscription_id' => $subscription->id,
            'kitchen_id' => $kitchen->id,
            'delivery_date' => now()->toDateString(),
            'meal_slot' => 'lunch',
            'status' => 'in_transit',
            'meal_cost_idr' => 45000,
            'idempotency_key' => 'idemp-breach-01',
        ]);

        $action = app(AuditThermalSealTamperChainAction::class);
        // Temperature below 60°C for hot food fails HACCP
        $result = $action->handle($order->id, 'SEAL-VALID-12345', 45.0, 'hot');

        $this->assertFalse($result['is_safe']);
        $this->assertEquals('SAFETY_BREACH_FLAGGED', $result['safety_verdict']);
    }

    public function test_cloud_kitchen_operations_http_routes_render_and_handle_requests(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get(route('cloud_kitchen.operations.order-routing'));
        $response->assertStatus(200);
        $response->assertSee('Cloud Kitchen Virtual Brand Routing');

        $response = $this->actingAs($admin)->get(route('cloud_kitchen.operations.courier-batches'));
        $response->assertStatus(200);
        $response->assertSee('Multi-Drop Courier Batching');

        $response = $this->actingAs($admin)->get(route('cloud_kitchen.operations.revenue-split'));
        $response->assertStatus(200);
        $response->assertSee('Virtual Brand Revenue Split');

        $response = $this->actingAs($admin)->get(route('cloud_kitchen.operations.thermal-audit'));
        $response->assertStatus(200);
        $response->assertSee('HACCP Thermal Seal');
    }
}
