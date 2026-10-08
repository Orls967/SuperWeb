<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\FpaDriverBasedBudgetingService;
use Tests\TestCase;

class FpaDriverBasedBudgetingTest extends TestCase
{
    use RefreshDatabase;

    protected FpaDriverBasedBudgetingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(FpaDriverBasedBudgetingService::class);
    }

    public function test_driver_based_planning_and_variance_reforecast_mandate(): void
    {
        // 1. Compute driver plan: 1,000,000 traffic * 3.5% conversion * $100 AOV = $3,500,000 (312.1 & 312.4)
        $plan = $this->service->calculateDriverPlan(
            planCode: 'PLAN-ECOMM-Q1-2027',
            costCenter: 'CC-ECOMMERCE-GROWTH',
            traffic: 1000000,
            conversionRate: 0.035,
            aovUsd: 100.0,
            actualRevenueUsd: 3600000.0 // Variance is ~2.86% (below 20%)
        );
        $this->assertEquals(3500000.0, (float) $plan->computed_revenue_usd);
        $this->assertFalse((bool) $plan->reforecast_mandated);

        // 2. Severe driver miss: Actual revenue $2,500,000 vs $3,500,000 computed (28.57% miss > 20%) mandates reforecast (312.5 Edge Case)
        $missedPlan = $this->service->calculateDriverPlan(
            planCode: 'PLAN-ECOMM-MISS-Q2',
            costCenter: 'CC-ECOMMERCE-GROWTH',
            traffic: 1000000,
            conversionRate: 0.035,
            aovUsd: 100.0,
            actualRevenueUsd: 2500000.0
        );
        $this->assertTrue((bool) $missedPlan->reforecast_mandated);
        $this->assertEquals(28.57, (float) $missedPlan->variance_pct);
    }

    public function test_zero_based_budgeting_and_strategic_carveout(): void
    {
        // 1. Valid ZBB review with Board approved carve-out (312.3 & 312.6 Risk)
        $zbb = $this->service->registerZeroBasedReview(
            initiativeCode: 'ZBB-IT-INFRA-2027',
            costCenter: 'CC-GLOBAL-IT',
            baselineExpenseUsd: 5000000.0,
            eliminatedSavingsUsd: 1200000.0,
            boardCarveoutApproved: true
        );
        $this->assertEquals(1200000.0, (float) $zbb->eliminated_cost_savings_usd);
        $this->assertTrue((bool) $zbb->is_strategic_carveout_approved_by_board);

        // 2. Savings exceeding baseline is rejected (312.3)
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Eliminated savings ($6000000) cannot exceed baseline expense');
        $this->service->registerZeroBasedReview('ZBB-DEFECT', 'CC-IT', 5000000.0, 6000000.0);
    }

    public function test_enterprise_fpa_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->calculateDriverPlan('PLAN-AUD', 'CC-1', 100, 0.1, 10.0, 100.0);
        $this->service->registerZeroBasedReview('ZBB-AUD', 'CC-1', 1000.0, 200.0, true);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: variance > 20% without mandate
        DB::table('fpa_driver_based_plans')->insert([
            'plan_code' => 'PLAN-UNMANDATED-GAP',
            'cost_center_code' => 'CC-X',
            'driver_traffic' => 100,
            'driver_conversion_rate' => 0.1,
            'driver_average_order_usd' => 10.0,
            'computed_revenue_usd' => 100.0,
            'actual_revenue_usd' => 50.0,
            'variance_pct' => 50.0, // > 20%
            'reforecast_mandated' => false, // Discrepancy!
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
