<?php

declare(strict_types=1);

namespace Tests\Acceptance\Culinary\Eksplorasi;

use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\LedgerAccountsSeeder;
use Database\Seeders\RbacConglomerateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Modules\Banking\Application\Services\LedgerService;
use Modules\CloudKitchen\Application\Actions\AuditThermalSealTamperChainAction;
use Modules\CloudKitchen\Application\Actions\BatchCourierMultiDropDeliveryAction;
use Modules\CloudKitchen\Application\Actions\RouteVirtualBrandOrderToStationAction;
use Modules\CloudKitchen\Application\Actions\SettleVirtualBrandRevenueSplitAction;
use Modules\CloudKitchen\Application\Services\CloudKitchenService;
use Modules\CloudKitchen\Domain\Models\CateringOrder;
use Modules\CloudKitchen\Domain\Models\CateringSubscription;
use Modules\CloudKitchen\Domain\Models\CloudKitchen;
use Modules\Core\Contracts\SimClockInterface;
use Tests\TestCase;

/**
 * LINI 5: Kuliner - CloudKitchen Legacy Codebase Exploration (L5.0).
 *
 * Membuktikan kondisi awal kode lama sebelum MVP diperbaiki:
 * - RouteVirtualBrandOrderToStationAction: SEBAGIAN (ada load balancing sederhana, tapi tanpa BOM DAG B0′.4)
 * - BatchCourierMultiDropDeliveryAction: SEBAGIAN (mengelompokkan order, tapi tanpa rute optimal B0′.6)
 * - SettleVirtualBrandRevenueSplitAction: SALAH (float math * 0.60, tanpa double-entry ledger)
 * - AuditThermalSealTamperChainAction: BENAR (validasi HACCP safe range dan barcode prefix)
 * - CloudKitchenService: SEBAGIAN (payroll deduction diposting langsung, bukan via event hcm.payroll.posted.v1)
 */
class CloudKitchenLegacyExplorationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected CloudKitchen $kitchen;

    protected CateringSubscription $subscription;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacConglomerateSeeder::class);
        $this->seed(LedgerAccountsSeeder::class);

        $this->user = User::factory()->create([
            'email' => 'ck_chef@superweb.test',
            'name' => 'Chef Juna',
        ]);

        $this->kitchen = CloudKitchen::create([
            'kitchen_code' => 'CK-HQ-01',
            'name' => 'Central Ghost Kitchen Sudirman',
            'type' => 'central',
            'hourly_capacity' => 100,
            'current_hourly_load' => 0,
            'is_active' => true,
        ]);

        $this->subscription = CateringSubscription::create([
            'subscription_code' => 'SUB-TEST-001',
            'user_id' => $this->user->id,
            'package_name' => 'lunch_5d',
            'daily_quota' => 2,
            'used_quota_today' => 0,
            'monthly_fee_idr' => 1_000_000,
            'daily_meal_price_idr' => 45_000,
            'starts_at' => now()->toDateString(),
            'expires_at' => now()->addMonth()->toDateString(),
            'status' => 'active',
        ]);
    }

    public function test_legacy_tables_exist_and_legacy_prefixes_identified(): void
    {
        // Legacy CloudKitchen tables exist in schema
        expect(Schema::hasTable('resto_ck_kitchens'))->toBeTrue();
        expect(Schema::hasTable('resto_ck_catering_orders'))->toBeTrue();
        expect(Schema::hasTable('resto_catering_subscriptions'))->toBeTrue();

        // Proves legacy prefixes resto_ck_* were used prior to canonical ckt_* migration
        expect(str_starts_with('resto_ck_kitchens', 'ckt_'))->toBeFalse();
    }

    public function test_settle_virtual_brand_revenue_split_action_status_salah(): void
    {
        $order = CateringOrder::create([
            'order_code' => 'CK-TEST-001',
            'subscription_id' => $this->subscription->id,
            'kitchen_id' => $this->kitchen->id,
            'delivery_date' => now()->toDateString(),
            'meal_slot' => 'lunch',
            'status' => 'in_transit',
            'meal_cost_idr' => 45_001, // odd amount to expose rounding flaws
            'idempotency_key' => 'ck:test:split:001',
        ]);

        $action = app(SettleVirtualBrandRevenueSplitAction::class);
        $result = $action->handle($order->id);

        // Code uses float round($totalIdr * 0.60) violating U-1 integer math
        expect($result['kitchen_fee_idr'])->toBe((int) round(45_001 * 0.60))
            ->and($result['brand_royalty_idr'])->toBe((int) round(45_001 * 0.25))
            ->and($result['total_idr'])->toBe(45_001);

        // BUKTI CACAT: Tidak ada double-entry ledger posting sama sekali untuk pembagian hasil!
        $ledgerService = app(LedgerService::class);
        $revenueBalance = $ledgerService->getCachedBalance('resto:catering_revenue:IDR');
        // Revenue balance should be untouched because SettleVirtualBrandRevenueSplitAction lacks ledger integration
        expect($revenueBalance)->toBe('0');
    }

    public function test_route_virtual_brand_order_to_station_action_status_sebagian(): void
    {
        $action = app(RouteVirtualBrandOrderToStationAction::class);

        $result = $action->handle(
            subscriptionId: $this->subscription->id,
            mealSlot: 'lunch',
            mealCostIdr: 50_000,
            deliveryDate: now()->toDateString()
        );

        // Action works for basic kitchen capacity increment
        expect($result['status'])->toBe('preparing')
            ->and($result['kitchen_code'])->toBe('CK-HQ-01')
            ->and($this->kitchen->fresh()->current_hourly_load)->toBe(1);

        // BUKTI KEKURANGAN: Tidak ada BOM recipe DAG traversal untuk bahan masakan (B0′.4)
        // dan tidak ada inventory movement deduction.
    }

    public function test_batch_courier_multi_drop_delivery_action_status_sebagian(): void
    {
        $order1 = CateringOrder::create([
            'order_code' => 'CK-DROP-01',
            'subscription_id' => $this->subscription->id,
            'kitchen_id' => $this->kitchen->id,
            'delivery_date' => now()->toDateString(),
            'meal_slot' => 'lunch',
            'status' => 'preparing',
            'meal_cost_idr' => 45_000,
            'idempotency_key' => 'ck:drop:01',
        ]);

        $order2 = CateringOrder::create([
            'order_code' => 'CK-DROP-02',
            'subscription_id' => $this->subscription->id,
            'kitchen_id' => $this->kitchen->id,
            'delivery_date' => now()->toDateString(),
            'meal_slot' => 'lunch',
            'status' => 'preparing',
            'meal_cost_idr' => 45_000,
            'idempotency_key' => 'ck:drop:02',
        ]);

        $action = app(BatchCourierMultiDropDeliveryAction::class);
        $result = $action->handle([$order1->id, $order2->id]);

        expect($result['status'])->toBe('in_transit')
            ->and($result['orders_count'])->toBe(2)
            ->and($result['courier_payout_idr'])->toBe(25_000); // 15_000 + 2 * 5_000

        // BUKTI KEKURANGAN: Payout dihitung fixed flat fee, tanpa optimasi rute B0′.6 (MST / 2-opt)
    }

    public function test_audit_thermal_seal_tamper_chain_action_status_benar(): void
    {
        $order = CateringOrder::create([
            'order_code' => 'CK-HACCP-01',
            'subscription_id' => $this->subscription->id,
            'kitchen_id' => $this->kitchen->id,
            'delivery_date' => now()->toDateString(),
            'meal_slot' => 'lunch',
            'status' => 'in_transit',
            'meal_cost_idr' => 45_000,
            'idempotency_key' => 'ck:haccp:01',
        ]);

        $action = app(AuditThermalSealTamperChainAction::class);

        // Safe hot holding test (65°C, intact seal)
        $safeResult = $action->handle($order->id, 'SEAL-20261031-SECURE', 65.5, 'hot');
        expect($safeResult['is_safe'])->toBeTrue()
            ->and($safeResult['safety_verdict'])->toBe('HACCP_PASSED');

        // Breach hot holding test (under temperature 45°C)
        $breachResult = $action->handle($order->id, 'SEAL-20261031-SECURE', 45.0, 'hot');
        expect($breachResult['is_safe'])->toBeFalse()
            ->and($breachResult['safety_verdict'])->toBe('SAFETY_BREACH_FLAGGED');
    }

    public function test_cloud_kitchen_service_subscription_status_sebagian(): void
    {
        $service = app(CloudKitchenService::class);
        $simClock = app(SimClockInterface::class);

        $subscription = $service->createSubscription(
            userId: $this->user->id,
            packageName: 'Executive Executive Catering',
            dailyQuota: 2,
            monthlyFeeIdr: 1_200_000,
            dailyMealPriceIdr: 40_000,
            startDate: Carbon::parse($simClock->now()->toDateTimeString()),
            durationMonths: 1
        );

        expect($subscription->status)->toBe('active')
            ->and($subscription->monthly_fee_idr)->toBe(1_200_000);

        // BUKTI KEKURANGAN (KONSEP §3F Slice 2):
        // Subscription memposting langsung ke ledger:
        // Dr hcm:meals_deduction:IDR, Cr resto:catering_revenue:IDR
        // Padahal aturan KONSEP §3F mengharuskan:
        // "saat hcm.payroll.posted.v1, potongan dibuat sebagai baris payroll (bukan posting terpisah tanpa payroll)"
        $ledgerService = app(LedgerService::class);
        $mealsDeduction = $ledgerService->getCachedBalance('hcm:meals_deduction:IDR');
        expect($mealsDeduction)->toBe('-1200000');
    }
}
