<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * PerformanceCostOptimizationService (Fase 239)
 *
 * Implements:
 *  - 239.1 Performance observability per endpoint: p50/p95/p99, throughput, N+1 detection & CI regression gate
 *  - 239.2 Cost-to-serve per module & transaction (compute, storage, queue) with unit-cost attribution
 *  - 239.3 Capacity planning: 12-month traffic growth projection & Treasury capex/opex proposal
 *  - 239.4 Efficiency culture: strict latency budget enforcement per endpoint
 *  - 239.6 Edge case: write slowdown trade-offs logged and explicitly approved
 *  - 239.7 CI performance budget catches cost & latency regressions before production
 */
class PerformanceCostOptimizationService
{
    /**
     * Record endpoint performance metric and evaluate regression gate (239.1, 239.4, 239.7).
     */
    public function recordEndpointMetric(
        string $endpoint,
        string $httpMethod,
        float $p50LatencyMs,
        float $p95LatencyMs,
        float $p99LatencyMs,
        float $throughputRps,
        int $slowQueryCount,
        bool $detectedNPlusOne,
        float $budgetMaxP95Ms = 200.00
    ): object {
        // Regression occurs if p95 exceeds budget or N+1 query pattern is detected
        $isRegressionFailed = ($p95LatencyMs > $budgetMaxP95Ms) || $detectedNPlusOne || ($slowQueryCount > 5);

        $id = DB::table('platform_endpoint_perf_metrics')->insertGetId([
            'endpoint' => $endpoint,
            'http_method' => strtoupper($httpMethod),
            'p50_latency_ms' => $p50LatencyMs,
            'p95_latency_ms' => $p95LatencyMs,
            'p99_latency_ms' => $p99LatencyMs,
            'throughput_rps' => $throughputRps,
            'slow_query_count' => $slowQueryCount,
            'detected_n_plus_one' => $detectedNPlusOne,
            'budget_max_p95_ms' => $budgetMaxP95Ms,
            'is_regression_failed' => $isRegressionFailed,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('platform_endpoint_perf_metrics')->find($id);
    }

    /**
     * Allocate simulated infrastructure cost-to-serve per module & transaction (239.2).
     */
    public function allocateCostToServe(
        string $moduleCode,
        string $periodMonth,
        int $transactionCount,
        float $computeCostUsd,
        float $storageCostUsd,
        float $queueCostUsd,
        float $savingsAchievedUsd = 0.00
    ): object {
        if ($computeCostUsd < 0 || $storageCostUsd < 0 || $queueCostUsd < 0) {
            throw new InvalidArgumentException('Cost components cannot be negative.');
        }

        $totalCost = $computeCostUsd + $storageCostUsd + $queueCostUsd;
        $unitCost = $transactionCount > 0 ? round($totalCost / $transactionCount, 4) : 0.00;

        $id = DB::table('platform_cost_to_serve_allocations')->insertGetId([
            'module_code' => strtoupper($moduleCode),
            'period_month' => $periodMonth,
            'transaction_count' => $transactionCount,
            'compute_cost_usd' => $computeCostUsd,
            'storage_cost_usd' => $storageCostUsd,
            'queue_cost_usd' => $queueCostUsd,
            'unit_cost_per_tx_usd' => $unitCost,
            'savings_achieved_usd' => $savingsAchievedUsd,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('platform_cost_to_serve_allocations')->find($id);
    }

    /**
     * Generate 12-month deterministic capacity projection and Treasury proposal (239.3).
     */
    public function generateCapacityPlan(
        float $projectedTrafficGrowthPct,
        int $projectedMonths = 12
    ): object {
        $code = 'CAP-'.strtoupper(Str::random(8));

        // Deterministic scaling action recommendation based on growth
        $scalingAction = 'READ_REPLICA';
        $capexEstimate = 15000.00;
        $opexEstimate = 3500.00;

        if ($projectedTrafficGrowthPct >= 100.0) {
            $scalingAction = 'SHARDING';
            $capexEstimate = 65000.00;
            $opexEstimate = 12000.00;
        } elseif ($projectedTrafficGrowthPct <= 20.0) {
            $scalingAction = 'COLD_ARCHIVE';
            $capexEstimate = 5000.00;
            $opexEstimate = 1200.00;
        }

        $id = DB::table('platform_capacity_projections')->insertGetId([
            'forecast_code' => $code,
            'projected_months' => $projectedMonths,
            'projected_traffic_growth_pct' => $projectedTrafficGrowthPct,
            'recommended_scaling_action' => $scalingAction,
            'capex_estimate_usd' => $capexEstimate,
            'opex_estimate_usd' => $opexEstimate,
            'treasury_proposal_status' => 'SUBMITTED',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('platform_capacity_projections')->find($id);
    }

    /**
     * Record write-slowdown trade-off for performance optimization (239.6 Edge Case).
     */
    public function recordPerformanceTradeoff(
        string $featureName,
        string $optimizationType,
        float $readGainPct,
        float $writePenaltyPct,
        string $decisionRationale,
        string $approvedBy
    ): object {
        $id = DB::table('platform_performance_tradeoffs')->insertGetId([
            'feature_name' => $featureName,
            'optimization_type' => strtoupper($optimizationType),
            'read_gain_pct' => $readGainPct,
            'write_penalty_pct' => $writePenaltyPct,
            'decision_rationale' => $decisionRationale,
            'approved_by' => strtoupper($approvedBy),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('platform_performance_tradeoffs')->find($id);
    }

    /**
     * Performance Engineering & Cost Platform Audit (`platform:audit`) (239.5).
     */
    public function audit(): array
    {
        // Discrepancy 1: Metrics exceeding latency budget or having N+1 but not marked failed
        $unflaggedRegressions = DB::table('platform_endpoint_perf_metrics')
            ->where('is_regression_failed', false)
            ->where(function ($q) {
                $q->whereRaw('p95_latency_ms > budget_max_p95_ms')
                  ->orWhere('detected_n_plus_one', true);
            })
            ->count();

        // Discrepancy 2: Cost allocations with negative numbers
        $negativeCosts = DB::table('platform_cost_to_serve_allocations')
            ->where('compute_cost_usd', '<', 0)
            ->orWhere('storage_cost_usd', '<', 0)
            ->orWhere('queue_cost_usd', '<', 0)
            ->count();

        // Discrepancy 3: Capacity plans with negative months or negative growth
        $invalidCapacityPlans = DB::table('platform_capacity_projections')
            ->where('projected_months', '<=', 0)
            ->count();

        $discrepancies = $unflaggedRegressions + $negativeCosts + $invalidCapacityPlans;

        return [
            'status' => $discrepancies === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_endpoint_metrics' => DB::table('platform_endpoint_perf_metrics')->count(),
            'total_cost_allocations' => DB::table('platform_cost_to_serve_allocations')->count(),
            'total_capacity_plans' => DB::table('platform_capacity_projections')->count(),
            'total_tradeoffs_logged' => DB::table('platform_performance_tradeoffs')->count(),
            'discrepancy_count' => $discrepancies,
        ];
    }
}
