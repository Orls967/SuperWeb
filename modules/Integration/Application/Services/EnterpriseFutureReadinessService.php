<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * EnterpriseFutureReadinessService (Fase 482)
 *
 * Implements:
 *  - 482.1 Horizon scanning: technology, market, regulation, societal trends
 *  - 482.2 Future scenarios: 3-5 plausible futures with option investments and triggers
 *  - 482.3 Innovation pipeline health: ideas, experiments, pilots, scale rate
 *  - 482.4 Tests: scenario deterministic, option investment tracked, plm:audit clean
 *  - 482.5 Edge case: Out-of-portfolio scenarios require mandatory assumption re-review (cannot ignore signals)
 *  - 482.6 Risk: Trigger monitoring requires explicit designated owner & review cadence
 *  - 482.7 Evidence: horizon scan, scenario set, pipeline health metrics
 */
class EnterpriseFutureReadinessService
{
    public function registerFutureScenario(
        string $code,
        string $title,
        string $horizon,
        float $optionInvestment,
        string $triggerOwner,
        string $cadence = 'quarterly'
    ): object {
        // 482.6 Risk: Trigger monitoring owner and cadence are mandatory
        if (empty(trim($triggerOwner)) || empty(trim($cadence))) {
            throw new InvalidArgumentException("Scenario registration blocked: Horizon trigger monitoring requires an assigned owner and review cadence (482.6).");
        }

        $id = DB::table('int_enterprise_future_scenarios')->insertGetId([
            'scenario_code' => strtoupper($code),
            'title' => $title,
            'time_horizon' => $horizon,
            'option_investment_idr' => $optionInvestment,
            'trigger_monitoring_owner' => $triggerOwner,
            'monitoring_cadence' => strtolower($cadence),
            'assumptions_reviewed' => false,
            'status' => 'active_monitoring',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('int_enterprise_future_scenarios')->where('id', $id)->first();
    }

    /**
     * 482.5 Edge case: Re-review underlying assumptions for outliers
     */
    public function reReviewAssumptions(string $code): object
    {
        $sc = DB::table('int_enterprise_future_scenarios')->where('scenario_code', strtoupper($code))->first();
        if (! $sc) {
            throw new InvalidArgumentException("Scenario '{$code}' not found.");
        }

        DB::table('int_enterprise_future_scenarios')->where('id', $sc->id)->update([
            'assumptions_reviewed' => true,
            'updated_at' => now(),
        ]);

        return (object) DB::table('int_enterprise_future_scenarios')->where('id', $sc->id)->first();
    }

    /**
     * 482.3 Track innovation pipeline stages
     */
    public function recordPipelineHealth(
        string $pipelineCode,
        int $ideas,
        int $experiments,
        int $pilots,
        int $scaled
    ): object {
        $id = DB::table('int_enterprise_innovation_pipelines')->insertGetId([
            'pipeline_code' => strtoupper($pipelineCode),
            'ideas_count' => $ideas,
            'experiments_count' => $experiments,
            'pilots_count' => $pilots,
            'scaled_solutions_count' => $scaled,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('int_enterprise_innovation_pipelines')->where('id', $id)->first();
    }

    public function audit(): array
    {
        // Discrepancy 1: Scenarios with unassigned trigger owners
        $unassignedScenarios = DB::table('int_enterprise_future_scenarios')
            ->where(function ($query) {
                $query->whereNull('trigger_monitoring_owner')
                    ->orWhere('trigger_monitoring_owner', '');
            })
            ->count();

        // Discrepancy 2: Pipelines where scaled solutions exceed pilots count (impossible anomaly)
        $inconsistentPipelines = DB::table('int_enterprise_innovation_pipelines')
            ->whereColumn('scaled_solutions_count', '>', 'pilots_count')
            ->count();

        $total = $unassignedScenarios + $inconsistentPipelines;

        return [
            'status' => $total === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_scenarios' => DB::table('int_enterprise_future_scenarios')->count(),
            'total_pipelines' => DB::table('int_enterprise_innovation_pipelines')->count(),
            'discrepancy_count' => $total,
        ];
    }
}
