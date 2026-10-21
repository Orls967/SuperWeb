<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Application\Services\CoreAiAnomalyQuarantineService;
use Modules\Core\Application\Services\MultiVectorDemandForecastingService;
use Modules\Core\Application\Services\PrescriptiveOperationsService;
use Modules\Core\Application\Services\UnifiedDynamicPricingEngineService;
use Tests\TestCase;

class UnifiedDynamicPricingTest extends TestCase
{
    use RefreshDatabase;

    public function test_contract_priority_wins_over_dynamic_multipliers(): void
    {
        $engine = app(UnifiedDynamicPricingEngineService::class);

        // Case 1: Contract active -> Contract price wins unconditionally
        $res = $engine->calculateFinalPrice(1000000, 800000, 2000000, 18000, 900000);
        $this->assertSame(900000, $res['final_price_idr']);
        $this->assertSame('CONTRACT_PRIORITY', $res['pricing_strategy']);
        $this->assertTrue($res['contract_enforced']);

        // Case 2: No contract -> Dynamic pricing within bounds
        $resDynamic = $engine->calculateFinalPrice(1000000, 800000, 1500000, 12000, null);
        $this->assertSame(1200000, $resDynamic['final_price_idr']);
        $this->assertSame('DYNAMIC_BOUNDED', $resDynamic['pricing_strategy']);
        $this->assertFalse($resDynamic['contract_enforced']);

        // Case 3: Demand dips -> Floor price clamped
        $resFloor = $engine->calculateFinalPrice(1000000, 900000, 1500000, 5000, null);
        $this->assertSame(900000, $resFloor['final_price_idr']);

        // Case 4: Demand surges -> Ceiling price clamped
        $resCeiling = $engine->calculateFinalPrice(1000000, 800000, 1300000, 25000, null);
        $this->assertSame(1300000, $resCeiling['final_price_idr']);
    }

    public function test_demand_forecasting_mape_accuracy(): void
    {
        $service = app(MultiVectorDemandForecastingService::class);
        $actuals = [100, 120, 130, 150, 140];
        $forecasts = [105, 115, 135, 145, 142];

        $mape = $service->calculateMape($actuals, $forecasts);
        $this->assertLessThanOrEqual(12.5, $mape);
        $this->assertGreaterThan(0.0, $mape);
    }

    public function test_anomaly_fraud_quarantine_blocks_suspicious_settlement(): void
    {
        $service = app(CoreAiAnomalyQuarantineService::class);

        // Transaction with rapid velocity and extreme magnitude -> score: 30 + 35 = 65 >= 60
        $eval = $service->evaluateTransactionRisk(
            'TX-ANOMALY-9901',
            'HOTEL',
            8,
            ['EXTREME_MAGNITUDE']
        );

        $this->assertTrue($eval['is_quarantined']);
        $this->assertSame(65, $eval['risk_score']);
        $this->assertDatabaseHas('core_ai_anomaly_quarantines', [
            'transaction_ref' => 'TX-ANOMALY-9901',
            'status' => 'QUARANTINED',
        ]);
    }

    public function test_prescriptive_ops_auto_executes_below_threshold(): void
    {
        $service = app(PrescriptiveOperationsService::class);

        // High confidence (95%), low amount -> autonomous execute
        $res1 = $service->evaluatePrescriptiveAction('REORDER_PO', 9500, 25000000);
        $this->assertSame('AUTONOMOUS_EXECUTED', $res1['decision']);

        // Lower confidence (85%), high amount -> managerial approval
        $res2 = $service->evaluatePrescriptiveAction('MINING_BLENDING', 8500, 150000000);
        $this->assertSame('MANAGERIAL_APPROVAL_REQUIRED', $res2['decision']);
    }
}
