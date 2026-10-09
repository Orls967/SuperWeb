<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\PerformanceCostOptimizationService;
use Tests\TestCase;

class PerformanceCostOptimizationTest extends TestCase
{
    use RefreshDatabase;

    protected PerformanceCostOptimizationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(PerformanceCostOptimizationService::class);
    }

    public function test_endpoint_performance_metric_passing_and_regression_failure(): void
    {
        // 1. Passing endpoint (within 200ms budget, no N+1)
        $fastEndpoint = $this->service->recordEndpointMetric(
            endpoint: '/api/v1/catalog/products',
            httpMethod: 'GET',
            p50LatencyMs: 45.0,
            p95LatencyMs: 120.0,
            p99LatencyMs: 180.0,
            throughputRps: 450.0,
            slowQueryCount: 0,
            detectedNPlusOne: false,
            budgetMaxP95Ms: 200.0
        );

        $this->assertFalse((bool) $fastEndpoint->is_regression_failed);
        $this->assertEquals(120.0, (float) $fastEndpoint->p95_latency_ms);

        // 2. Failing endpoint due to latency budget breach (239.1, 239.4, 239.7)
        $slowEndpoint = $this->service->recordEndpointMetric(
            endpoint: '/api/v1/orders/checkout',
            httpMethod: 'POST',
            p50LatencyMs: 180.0,
            p95LatencyMs: 380.0,
            p99LatencyMs: 650.0,
            throughputRps: 120.0,
            slowQueryCount: 6,
            detectedNPlusOne: false,
            budgetMaxP95Ms: 200.0
        );

        $this->assertTrue((bool) $slowEndpoint->is_regression_failed);

        // 3. Failing endpoint due to detected N+1 query pattern
        $nPlusOneEndpoint = $this->service->recordEndpointMetric(
            endpoint: '/api/v1/users/profiles',
            httpMethod: 'GET',
            p50LatencyMs: 30.0,
            p95LatencyMs: 80.0,
            p99LatencyMs: 110.0,
            throughputRps: 200.0,
            slowQueryCount: 0,
            detectedNPlusOne: true,
            budgetMaxP95Ms: 200.0
        );

        $this->assertTrue((bool) $nPlusOneEndpoint->is_regression_failed);
    }

    public function test_cost_to_serve_unit_cost_attribution_and_savings(): void
    {
        // 10,000 txs; compute $500, storage $200, queue $100 -> total $800 -> $0.0800 / tx
        $costRecord = $this->service->allocateCostToServe(
            moduleCode: 'PAYMENTS',
            periodMonth: '2026-10',
            transactionCount: 10000,
            computeCostUsd: 500.00,
            storageCostUsd: 200.00,
            queueCostUsd: 100.00,
            savingsAchievedUsd: 150.00
        );

        $this->assertEquals('PAYMENTS', $costRecord->module_code);
        $this->assertEquals(0.0800, (float) $costRecord->unit_cost_per_tx_usd);
        $this->assertEquals(150.00, (float) $costRecord->savings_achieved_usd);

        // Validation against negative cost
        $this->expectException(InvalidArgumentException::class);
        $this->service->allocateCostToServe('LOGISTICS', '2026-10', 100, -50.0, 10.0, 5.0);
    }

    public function test_capacity_planning_traffic_projections_and_treasury_proposal(): void
    {
        // High growth (> 100%) recommends SHARDING (239.3)
        $highGrowthPlan = $this->service->generateCapacityPlan(
            projectedTrafficGrowthPct: 150.0,
            projectedMonths: 12
        );
        $this->assertEquals('SHARDING', $highGrowthPlan->recommended_scaling_action);
        $this->assertEquals(65000.00, (float) $highGrowthPlan->capex_estimate_usd);
        $this->assertEquals('SUBMITTED', $highGrowthPlan->treasury_proposal_status);

        // Low growth (< 20%) recommends COLD_ARCHIVE
        $lowGrowthPlan = $this->service->generateCapacityPlan(
            projectedTrafficGrowthPct: 12.0,
            projectedMonths: 12
        );
        $this->assertEquals('COLD_ARCHIVE', $lowGrowthPlan->recommended_scaling_action);
        $this->assertEquals(5000.00, (float) $lowGrowthPlan->capex_estimate_usd);
    }

    public function test_performance_tradeoff_write_penalty_documentation(): void
    {
        $tradeoff = $this->service->recordPerformanceTradeoff(
            featureName: 'Product Search Materialized View',
            optimizationType: 'CQRS_DENORMALIZATION',
            readGainPct: 85.50,
            writePenaltyPct: 12.00,
            decisionRationale: 'Read traffic outnumbers write by 100:1; 12% write latency penalty acceptable for 85.5% read acceleration.',
            approvedBy: 'ARCHITECT_PRINCIPAL'
        );

        $this->assertEquals('Product Search Materialized View', $tradeoff->feature_name);
        $this->assertEquals(85.50, (float) $tradeoff->read_gain_pct);
        $this->assertEquals(12.00, (float) $tradeoff->write_penalty_pct);
        $this->assertDatabaseHas('platform_performance_tradeoffs', [
            'id' => $tradeoff->id,
            'approved_by' => 'ARCHITECT_PRINCIPAL',
        ]);
    }

    public function test_performance_audit_healthy_and_discrepancy_detection(): void
    {
        // Setup healthy records
        $this->service->recordEndpointMetric('/api/v1/health', 'GET', 10.0, 25.0, 40.0, 1000.0, 0, false);
        $this->service->allocateCostToServe('CORE', '2026-10', 5000, 100.0, 50.0, 20.0);
        $this->service->generateCapacityPlan(45.0, 12);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: unflagged regression
        DB::table('platform_endpoint_perf_metrics')->insert([
            'endpoint' => '/api/v1/leaky',
            'http_method' => 'GET',
            'p50_latency_ms' => 300.0,
            'p95_latency_ms' => 500.0,
            'p99_latency_ms' => 900.0,
            'throughput_rps' => 10.0,
            'slow_query_count' => 10,
            'detected_n_plus_one' => true,
            'budget_max_p95_ms' => 200.0,
            'is_regression_failed' => false, // Discrepancy!
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
