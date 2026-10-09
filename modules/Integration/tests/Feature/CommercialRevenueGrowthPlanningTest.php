<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\CommercialRevenueGrowthPlanningService;
use Tests\TestCase;

class CommercialRevenueGrowthPlanningTest extends TestCase
{
    use RefreshDatabase;

    protected CommercialRevenueGrowthPlanningService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(CommercialRevenueGrowthPlanningService::class);
    }

    public function test_commercial_promotion_performance_and_margin_guardrail(): void
    {
        // 1. Promo with healthy 22% margin (>= 15% guardrail) succeeds (305.1 & 305.2)
        $promo = $this->service->evaluatePromotion(
            promoCode: 'PROMO-SUMMER-TIRES-01',
            productLine: 'AUTO_PARTS',
            baselineSalesUsd: 100000.0,
            promoSalesUsd: 135000.0,
            netMarginPct: 22.0,
            minMarginGuardrailPct: 15.0
        );
        $this->assertEquals(35.0, (float) $promo->incremental_lift_pct);
        $this->assertFalse((bool) $promo->is_halted_by_margin_guard);

        // 2. Promo eroding net margin below threshold (e.g. 11% < 15%) is halted (305.5 Edge Case)
        try {
            $this->service->evaluatePromotion(
                promoCode: 'PROMO-MARGIN-KILLER-02',
                productLine: 'RETAIL_FMCG',
                baselineSalesUsd: 50000.0,
                promoSalesUsd: 90000.0,
                netMarginPct: 11.0, // Below 15% guardrail!
                minMarginGuardrailPct: 15.0
            );
            $this->fail('Expected exception for margin guardrail breach');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('falls below minimum threshold', $e->getMessage());
        }
    }

    public function test_forecast_override_requires_positive_value_of_information(): void
    {
        // 1. Positive net VOI ($5,000 savings - $1,500 cost = +$3,500) authorizes override (305.3 & 305.6)
        $override = $this->service->evaluateForecastOverride(
            overrideCode: 'OVR-Q4-LITHIUM-SURGE',
            plannerId: 'PLANNER_HENDRA',
            projectedErrorSavingsUsd: 5000.0,
            overrideFinanceCostUsd: 1500.0
        );
        $this->assertTrue((bool) $override->is_override_authorized);
        $this->assertEquals(3500.0, (float) $override->value_of_information_net_usd);

        // 2. Non-positive VOI ($1,000 savings - $1,200 cost = -$200) is rejected (305.3)
        try {
            $this->service->evaluateForecastOverride('OVR-UNJUSTIFIED', 'PLANNER_HENDRA', 1000.0, 1200.0);
            $this->fail('Expected exception for non-positive VOI');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Negative or zero Value of Information', $e->getMessage());
        }
    }

    public function test_commercial_pricing_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->evaluatePromotion('PROMO-AUD', 'LINE', 1000.0, 1200.0, 20.0, 15.0);
        $this->service->evaluateForecastOverride('OVR-AUD', 'P1', 500.0, 100.0);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: unhalted breach promo
        DB::table('commercial_promotions')->insert([
            'promo_code' => 'PROMO-UNHALTED-BREACH',
            'product_line' => 'LINE-X',
            'baseline_sales_usd' => 1000.0,
            'promotional_sales_usd' => 2000.0,
            'incremental_lift_pct' => 100.0,
            'min_margin_guardrail_pct' => 20.0,
            'net_realized_margin_pct' => 10.0, // Below guardrail!
            'is_halted_by_margin_guard' => false, // Discrepancy!
            'promo_roi_pct' => 125.0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
