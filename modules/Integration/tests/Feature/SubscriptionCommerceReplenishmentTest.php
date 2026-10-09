<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Integration\Application\Services\SubscriptionCommerceReplenishmentService;
use Tests\TestCase;

class SubscriptionCommerceReplenishmentTest extends TestCase
{
    use RefreshDatabase;

    protected SubscriptionCommerceReplenishmentService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(SubscriptionCommerceReplenishmentService::class);
    }

    public function test_subscription_creation_and_discount_ladder(): void
    {
        // 1. Create replenishment plan: $100 base with 15% discount ladder = $85 (314.1 & 314.4)
        $plan = $this->service->createSubscriptionPlan(
            planCode: 'SUB-GROCERY-ORGANIC-01',
            subscriberId: 'USER_RAHMAD',
            sku: 'SKU-ORGANIC-COFFEE-BEANS',
            frequencyDays: 30,
            basePriceUsd: 100.0,
            discountLadderPct: 15.0
        );
        $this->assertEquals(85.0, (float) $plan->final_discounted_price_usd);
        $this->assertEquals('ACTIVE', $plan->status);

        // 2. Subscriber pause control (314.2)
        $paused = $this->service->updatePlanStatus('SUB-GROCERY-ORGANIC-01', 'PAUSED');
        $this->assertEquals('PAUSED', $paused->status);
    }

    public function test_auto_ship_and_surprise_charge_prevention_on_stockout(): void
    {
        $this->service->createSubscriptionPlan('SUB-TIRE-OIL', 'USER_FLEET', 'SKU-SYNTHETIC-OIL', 60, 50.0, 10.0);

        // 1. Successful auto-ship when inventory and payment are valid (314.2 & 314.4)
        $shipped = $this->service->executeAutoShipCycle('SHIP-CYCLE-01', 'SUB-TIRE-OIL', '2026-11-01', true, true);
        $this->assertEquals('SHIPPED', $shipped->execution_outcome);
        $this->assertTrue((bool) $shipped->surprise_charge_prevented);

        // 2. Edge case 314.5: Stockout auto-pauses plan without surprise charge
        $stockout = $this->service->executeAutoShipCycle('SHIP-CYCLE-02', 'SUB-TIRE-OIL', '2026-12-01', false, true);
        $this->assertEquals('PAUSED_OUT_OF_STOCK', $stockout->execution_outcome);
        $this->assertTrue((bool) $stockout->surprise_charge_prevented);

        $planAfter = DB::table('subscription_replenishment_plans')->where('plan_code', 'SUB-TIRE-OIL')->first();
        $this->assertEquals('PAUSED', $planAfter->status);
    }

    public function test_billing_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->createSubscriptionPlan('SUB-AUD', 'USER-1', 'SKU-1', 30, 20.0, 10.0);
        $this->service->executeAutoShipCycle('SHIP-AUD', 'SUB-AUD', '2026-11-01', true, true);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: unprevented surprise charge
        DB::table('subscription_auto_ship_executions')->insert([
            'shipment_code' => 'SHIP-SURPRISE-CHARGE',
            'plan_code' => 'SUB-AUD',
            'scheduled_date' => '2026-11-01',
            'inventory_available' => false,
            'payment_successful' => false,
            'execution_outcome' => 'FAILED',
            'surprise_charge_prevented' => false, // Discrepancy!
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
