<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\FulfillmentOrchestrationPromiseService;
use Tests\TestCase;

class FulfillmentOrchestrationPromiseTest extends TestCase
{
    use RefreshDatabase;

    protected FulfillmentOrchestrationPromiseService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(FulfillmentOrchestrationPromiseService::class);
    }

    public function test_order_promise_creation_and_lost_stock_auto_compensation(): void
    {
        // 1. Order promise created with frozen contract price (304.1 & 304.6)
        $promise = $this->service->makeOrderPromise(
            orderCode: 'ORD-B2B-NICKEL-001',
            channel: 'B2B',
            sku: 'SKU-NICKEL-CATHODE',
            quantity: 50,
            promisedDate: '2026-11-05',
            frozenContractPriceUsd: 145000.0
        );
        $this->assertEquals(145000.0, (float) $promise->frozen_contract_price_usd);
        $this->assertFalse((bool) $promise->is_stock_lost_post_promise);
        $this->assertEquals(0.00, (float) $promise->automatic_compensation_usd);

        // 2. Edge case 304.5: Stock lost post-promise auto-credits compensation to customer
        $updated = $this->service->handleLostPromisedStock('ORD-B2B-NICKEL-001', 500.00);
        $this->assertTrue((bool) $updated->is_stock_lost_post_promise);
        $this->assertEquals(500.00, (float) $updated->automatic_compensation_usd);
    }

    public function test_orchestration_routing_strictly_honors_price_freeze(): void
    {
        // 1. Orchestration rule honoring contract price freeze succeeds (304.2 & 304.6)
        $rule = $this->service->configureOrchestrationRule(
            ruleCode: 'ROUTING-EAST-DC-SPLIT',
            node: 'REGIONAL_DC',
            costToServeUsd: 14.50,
            honorContractPriceFreeze: true
        );
        $this->assertTrue((bool) $rule->price_freeze_contract_honored);

        // 2. Attempting to override/bypass price freeze is strictly rejected (304.6 Risk Guardrail)
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Fulfillment orchestration cannot override frozen contractual pricing');
        $this->service->configureOrchestrationRule('ROUTING-PRICE-SURGE', 'DROPSHIP', 35.00, false);
    }

    public function test_fulfillment_ret_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->makeOrderPromise('ORD-AUD', 'WEB', 'SKU-1', 1, '2026-11-01', 100.0);
        $this->service->configureOrchestrationRule('RULE-AUD', 'STORE', 5.0, true);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: lost stock without compensation
        DB::table('fulfillment_order_promises')->insert([
            'order_code' => 'ORD-UNCOMPENSATED',
            'customer_channel' => 'STORE',
            'sku' => 'SKU-LOST',
            'committed_quantity' => 2,
            'promised_delivery_date' => '2026-11-01',
            'frozen_contract_price_usd' => 50.0,
            'is_stock_lost_post_promise' => true,
            'automatic_compensation_usd' => 0.0, // Discrepancy!
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
