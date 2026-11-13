<?php

declare(strict_types=1);

use Modules\ControlTower\Domain\Algorithms\BlastRadiusImpactAnalyzer;
use Modules\ControlTower\Domain\Algorithms\DemandForecastEngine;
use Modules\ControlTower\Domain\Algorithms\SafetyStockAbcXyzOptimizer;
use Modules\ControlTower\Domain\Algorithms\WhatIfTwinSimulator;
use Modules\ControlTower\Domain\Services\CapacityBottleneckDetector;
use Modules\ControlTower\Domain\Services\DisruptionSeverityClassifier;
use Modules\ControlTower\Domain\Services\EchelonRebalancingPlanner;
use Modules\ControlTower\Domain\Services\ForecastModelSelector;
use Modules\ControlTower\Domain\Services\MultiEchelonVisibilityAggregator;
use Modules\ControlTower\Domain\Services\OrderPromiseAllocator;
use Modules\ControlTower\Domain\Services\OtifAndC2cCalculator;
use Modules\ControlTower\Domain\Services\ShelfLifeExpiryRiskEvaluator;
use Modules\ControlTower\Domain\Services\SlowMovingDeadStockDetector;
use Modules\ControlTower\Domain\Services\SopConsensusComparator;
use Modules\ControlTower\Domain\Services\SopFinancialBalancer;
use Modules\ControlTower\Domain\Services\SupplierLeadTimeVariabilityAnalyzer;
use Modules\ControlTower\Domain\ValueObjects\EchelonPosition;
use Modules\ControlTower\Domain\ValueObjects\PromiseTimeBucket;
use Modules\ControlTower\Domain\ValueObjects\SopScenarioProjection;

it('evaluates all four demand forecast models deterministically', function (): void {
    $engine = new DemandForecastEngine;
    $history = [100, 110, 120, 130, 140];

    foreach (['MOVING_AVG', 'EXP_SMOOTHING', 'HOLT_WINTERS', 'LINEAR_REGRESSION'] as $model) {
        $res = $engine->forecast('SKU-UNIT-1', $history, $model);
        expect($res['forecast_qty'])->toBeGreaterThan(0);
    }

    $best = (new ForecastModelSelector($engine))->selectBestModel('SKU-UNIT-1', $history);
    expect($best['forecast_qty'])->toBeGreaterThan(0);
});

it('rejects negative demand history and short series', function (): void {
    $engine = new DemandForecastEngine;
    expect(fn () => $engine->forecast('SKU-1', [10, 20]))->toThrow(InvalidArgumentException::class);
    expect(fn () => $engine->forecast('SKU-1', [10, -5, 20]))->toThrow(InvalidArgumentException::class);
});

it('plans multi-echelon rebalancing and detects bottlenecks', function (): void {
    $positions = [
        new EchelonPosition('SKU-1', 'PLANT', 200, 0, 20, 50),
        new EchelonPosition('SKU-1', 'DC', 10, 0, 0, 40),
    ];

    $agg = (new MultiEchelonVisibilityAggregator)->aggregate($positions);
    expect($agg['total_net_available'])->toBe(190)
        ->and($agg['nodes_below_safety'])->toBe(['DC']);

    $plan = (new EchelonRebalancingPlanner)->plan($positions);
    expect($plan)->toHaveCount(1)
        ->and($plan[0]['transfer_qty'])->toBe(30);

    $bottlenecks = (new CapacityBottleneckDetector)->detect([
        'LINE-A' => ['load_units' => 950, 'capacity_units' => 1000],
        'LINE-B' => ['load_units' => 700, 'capacity_units' => 1000],
    ]);
    expect($bottlenecks)->toHaveCount(1)
        ->and($bottlenecks[0]['work_center'])->toBe('LINE-A');
});

it('calculates sop financial balance, kpis, shelf-life, and what-if twin', function (): void {
    $fin = (new SopFinancialBalancer)->balance(100, 120, 50_000, 30_000, 1_000);
    expect($fin['gross_margin_idr'])->toBe(1_980_000)
        ->and($fin['working_capital_delta_idr'])->toBe(-600_000);

    $cmp = (new SopConsensusComparator)->compare([
        new SopScenarioProjection('conservative', 100, 100, 8000, 1_000_000, 100_000),
        new SopScenarioProjection('moderate', 120, 120, 9000, 1_500_000, 50_000),
    ]);
    expect($cmp['recommended_scenario'])->toBe('moderate');

    $kpi = (new OtifAndC2cCalculator)->calculate(96, 100, 25, 20, 30, 5000, 250, 2400, 20_000_000, 500_000_000);
    expect($kpi->meetsWorldClassOtif())->toBeTrue()
        ->and($kpi->cashToCashDays)->toBe(15);

    expect((new ShelfLifeExpiryRiskEvaluator)->evaluate(10, 20)['risk_level'])->toBe('HIGH');
    expect((new SlowMovingDeadStockDetector)->classify(100, 0, 180)['dead_stock'])->toBeTrue();
    expect((new SupplierLeadTimeVariabilityAnalyzer)->analyze([5, 7, 6], 6)['reliability_bps'])->toBe(6666);

    $twin = (new WhatIfTwinSimulator)->simulate('PORT_CLOSURE', 2000, 10, 9500, 100, 10_000_000);
    expect($twin['simulated_otif_bps'])->toBeLessThan(9500);

    $blast = (new BlastRadiusImpactAnalyzer)->analyze('PORT-TANJUNG-PRIOK', [
        ['order_ref' => 'SO-1', 'item_code' => 'SKU-1', 'echelon_path' => ['PORT-TANJUNG-PRIOK', 'DC'], 'value_idr' => 150_000_000],
    ]);
    expect($blast['affected_orders_count'])->toBe(1)
        ->and((new DisruptionSeverityClassifier)->classify(1, 5, 150_000_000))->toBe('HIGH');

    $buckets = [
        new PromiseTimeBucket('2026-11', 50, 20, 30),
        new PromiseTimeBucket('2026-12', 40, 30, 10),
    ];
    expect((new OrderPromiseAllocator)->allocateAcrossBuckets($buckets, 60)['earliest_bucket'])->toBe('2026-12');

    $ss = (new SafetyStockAbcXyzOptimizer)->optimize('SKU-9', 10_000_000, 100_000_000, [50, 52, 48], 5, 1000);
    expect($ss['abc_class'])->toBe('B');
});
