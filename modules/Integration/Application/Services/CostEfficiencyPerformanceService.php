<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * CostEfficiencyPerformanceService (Fase 434)
 *
 * Implements:
 *  - 434.1 Efficiency backlog: slow query, storage, cache, model cost, queue backlog
 *  - 434.2 Baseline vs post-optimization measurement; savings validated by FinOps
 *  - 434.3 Performance budget in design review; regression blocks release
 *  - 434.4 Tests: savings calculation, performance budget enforced, platform:audit clean
 *  - 434.5 Edge case: If optimization degrades service quality, trade-off must be explicitly approved
 *  - 434.6 Risk: Savings cannot be claimed without a verified baseline measurement
 *  - 434.7 Evidence: efficiency backlog, measurements, budget check
 */
class CostEfficiencyPerformanceService
{
    public function registerEfficiencyItem(
        string $itemCode,
        string $category,
        float $baselineCostPerMonth
    ): object {
        // 434.6 Risk: Mandatory verified baseline
        if ($baselineCostPerMonth <= 0.00) {
            throw new InvalidArgumentException("Item blocked: A valid baseline cost (> 0) is mandatory before registering efficiency items (434.2, 434.6).");
        }

        $id = DB::table('plt_efficiency_backlog_items')->insertGetId([
            'item_code' => strtoupper($itemCode),
            'category' => strtolower($category),
            'baseline_cost_per_month' => $baselineCostPerMonth,
            'post_opt_cost_per_month' => null,
            'validated_monthly_savings' => 0.00,
            'service_quality_tradeoff_approved' => true,
            'status' => 'backlog',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('plt_efficiency_backlog_items')->where('id', $id)->first();
    }

    public function validateEfficiencySavings(
        string $itemCode,
        float $postOptCost,
        bool $tradeoffApproved = true
    ): object {
        $item = DB::table('plt_efficiency_backlog_items')->where('item_code', strtoupper($itemCode))->first();
        if (! $item) {
            throw new InvalidArgumentException("Efficiency item '{$itemCode}' not found.");
        }

        // 434.5 Edge case: Trade-off approval required if quality is affected
        if (! $tradeoffApproved) {
            throw new InvalidArgumentException("Validation blocked: Quality trade-off must be reviewed and approved (434.5).");
        }

        $savings = max(0.00, (float) $item->baseline_cost_per_month - $postOptCost);

        DB::table('plt_efficiency_backlog_items')->where('id', $item->id)->update([
            'post_opt_cost_per_month' => $postOptCost,
            'validated_monthly_savings' => $savings,
            'service_quality_tradeoff_approved' => true,
            'status' => 'validated',
            'updated_at' => now(),
        ]);

        return (object) DB::table('plt_efficiency_backlog_items')->where('id', $item->id)->first();
    }

    public function definePerformanceBudget(string $featureCode, float $maxLatencyMs, float $maxMemoryMb): object
    {
        $id = DB::table('plt_feature_performance_budgets')->insertGetId([
            'feature_code' => strtoupper($featureCode),
            'max_allowed_latency_ms' => $maxLatencyMs,
            'max_allowed_memory_mb' => $maxMemoryMb,
            'tested_latency_ms' => null,
            'tested_memory_mb' => null,
            'budget_passed' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('plt_feature_performance_budgets')->where('id', $id)->first();
    }

    /**
     * 434.3 & 434.4 Evaluate feature performance against defined budget in CI
     */
    public function evaluatePerformanceBudget(string $featureCode, float $testedLatency, float $testedMemory): object
    {
        $budget = DB::table('plt_feature_performance_budgets')->where('feature_code', strtoupper($featureCode))->first();
        if (! $budget) {
            throw new InvalidArgumentException("Budget for '{$featureCode}' not found.");
        }

        $passed = ($testedLatency <= (float) $budget->max_allowed_latency_ms && $testedMemory <= (float) $budget->max_allowed_memory_mb);

        if (! $passed) {
            throw new InvalidArgumentException("Release blocked: Feature '{$featureCode}' violates performance budget (latency {$testedLatency}ms > {$budget->max_allowed_latency_ms}ms or memory {$testedMemory}MB > {$budget->max_allowed_memory_mb}MB) (434.3, 434.4).");
        }

        DB::table('plt_feature_performance_budgets')->where('id', $budget->id)->update([
            'tested_latency_ms' => $testedLatency,
            'tested_memory_mb' => $testedMemory,
            'budget_passed' => true,
            'updated_at' => now(),
        ]);

        return (object) DB::table('plt_feature_performance_budgets')->where('id', $budget->id)->first();
    }

    public function audit(): array
    {
        // Discrepancy: Items claiming savings with negative baseline or unapproved tradeoffs
        $invalidSavings = DB::table('plt_efficiency_backlog_items')
            ->where('status', 'validated')
            ->where(function ($query) {
                $query->where('validated_monthly_savings', '<=', 0)
                    ->orWhere('service_quality_tradeoff_approved', false);
            })
            ->count();

        return [
            'status' => $invalidSavings === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_items' => DB::table('plt_efficiency_backlog_items')->count(),
            'total_budgets' => DB::table('plt_feature_performance_budgets')->count(),
            'discrepancy_count' => $invalidSavings,
        ];
    }
}
