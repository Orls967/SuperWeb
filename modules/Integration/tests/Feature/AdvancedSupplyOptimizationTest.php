<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\AdvancedSupplyOptimizationService;
use Tests\TestCase;

class AdvancedSupplyOptimizationTest extends TestCase
{
    use RefreshDatabase;

    protected AdvancedSupplyOptimizationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(AdvancedSupplyOptimizationService::class);
    }

    public function test_network_optimization_enforces_service_level_guardrail(): void
    {
        // 1. Optimization reaching 98.7% service level (>= 98.5% target) succeeds (301.1 & 301.6)
        $plan = $this->service->optimizeNetworkPlan(
            planCode: 'OPT-NET-2026-11',
            planningMonth: '2026-11',
            projectedCostUsd: 14200000.0,
            simulatedServiceLevelPct: 98.70,
            targetServiceLevelPct: 98.50,
            isSandbox: true
        );
        $this->assertTrue((bool) $plan->service_level_guardrail_met);
        $this->assertTrue((bool) $plan->is_sandbox_simulation);

        // 2. Optimization that depresses safety stock below service level target is rejected (301.6 Risk Guardrail)
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Optimization guardrail breach: Simulated service level');
        $this->service->optimizeNetworkPlan('OPT-AGGRESSIVE-CUT', '2026-11', 11000000.0, 93.50, 98.50);
    }

    public function test_multi_echelon_inventory_policy_buffer_constraints(): void
    {
        // 1. Valid inventory policy with reorder point >= safety stock (301.2 & 301.6)
        $policy = $this->service->setEchelonInventoryPolicy(
            policyCode: 'POL-ECHELON-TIRE-CENTRAL',
            sku: 'SKU-TIRE-RADIAL-18',
            echelonLevel: 'CENTRAL_DC',
            safetyStockUnits: 500,
            reorderPointUnits: 1200,
            workingCapitalUsd: 150000.0,
            minServiceLevelPct: 98.00
        );
        $this->assertEquals(500, (int) $policy->safety_stock_units);
        $this->assertEquals(1200, (int) $policy->reorder_point_units);

        // 2. Invalid policy with reorder point < safety stock is rejected (301.2)
        try {
            $this->service->setEchelonInventoryPolicy('POL-DEFECT', 'SKU-TIRE', 'LOCAL_STORE', 300, 100, 10000.0);
            $this->fail('Expected exception for invalid buffer threshold');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Reorder point (100) cannot be lower than safety stock buffer (300)', $e->getMessage());
        }
    }

    public function test_demand_shaping_anti_hoarding_fairness(): void
    {
        // Demand shaping rules during unexpected seasonal surge enforce anti-hoarding (301.3 & 301.5 Edge Case)
        $rule = $this->service->configureDemandShapingRule(
            ruleCode: 'SHAPE-EV-BATTERY-PEAK',
            sku: 'SKU-EV-CELL-NMC',
            allocationStrategy: 'FAIR_SHARE_TIERED',
            priceMultiplier: 1.05,
            antiHoarding: true
        );

        $this->assertTrue((bool) $rule->anti_hoarding_quota_enforced);
        $this->assertEquals('FAIR_SHARE_TIERED', $rule->scarcity_allocation_strategy);
    }

    public function test_advanced_supply_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->optimizeNetworkPlan('OPT-AUD', '2026-11', 1000.0, 99.0, 98.0);
        $this->service->setEchelonInventoryPolicy('POL-AUD', 'SKU-1', 'CENTRAL_DC', 100, 200, 1000.0);
        $this->service->configureDemandShapingRule('SHAPE-AUD', 'SKU-1', 'FAIR_SHARE_TIERED', 1.0, true);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: failing guardrail optimization
        DB::table('supply_network_optimizations')->insert([
            'plan_code' => 'OPT-BREACH-DISCREPANCY',
            'planning_month' => '2026-11',
            'total_projected_cost_usd' => 500000.0,
            'target_service_level_pct' => 98.0,
            'simulated_service_level_pct' => 88.0,
            'service_level_guardrail_met' => false, // Discrepancy!
            'is_sandbox_simulation' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
