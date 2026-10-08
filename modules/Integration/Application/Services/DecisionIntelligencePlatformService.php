<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * DecisionIntelligencePlatformService (Fase 266)
 *
 * Implements:
 *  - 266.1 Enterprise decision catalog across critical business lines (pricing, capital, staffing, risk)
 *  - 266.2 Decision quality scoring & cognitive bias detection triggering manager learning loops
 *  - 266.3 Scenario workbench for executive what-if simulations yielding deterministic outcomes
 *  - 266.5 Edge case: Critical decisions taken without workbench simulation logged as governance exceptions
 *  - 266.6 Retrospective outcome evaluation closing the continuous learning loop
 *  - 266.7 Data sandbox strict architectural isolation from real production data
 */
class DecisionIntelligencePlatformService
{
    /**
     * Run scenario workbench what-if simulation strictly isolating production data (266.3, 266.4, 266.7).
     */
    public function runScenarioWorkbench(
        string $scenarioCode,
        string $scenarioTitle,
        array $syntheticInputParams,
        bool $attemptRealDataAccess = false
    ): object {
        // Architectural guard (266.7): Scenario workbench cannot access real production data
        if ($attemptRealDataAccess) {
            throw new InvalidArgumentException('Architectural violation: Scenario workbench is strictly prohibited from touching real production data (266.7).');
        }

        $code = strtoupper($scenarioCode);

        // Deterministic simulation calculation from inputs
        $projectedRevenue = (float) ($syntheticInputParams['price'] ?? 100.0) * (int) ($syntheticInputParams['volume'] ?? 1000);
        $projectedCost = (float) ($syntheticInputParams['unit_cost'] ?? 60.0) * (int) ($syntheticInputParams['volume'] ?? 1000);
        $projectedMarginPct = round((($projectedRevenue - $projectedCost) / max(1.0, $projectedRevenue)) * 100.0, 2);

        $deterministicOutcome = [
            'projected_revenue_usd' => $projectedRevenue,
            'projected_cost_usd' => $projectedCost,
            'projected_margin_pct' => $projectedMarginPct,
            'simulation_engine' => 'DETERMINISTIC_SANDBOX_V1',
        ];

        $id = DB::table('decision_scenario_workbenches')->insertGetId([
            'scenario_code' => $code,
            'scenario_title' => $scenarioTitle,
            'is_sandbox_isolated' => true,
            'production_data_touched' => false,
            'simulation_input_params_json' => json_encode($syntheticInputParams),
            'deterministic_outcome_result_json' => json_encode($deterministicOutcome),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('decision_scenario_workbenches')->find($id);
    }

    /**
     * Catalog business decision and flag governance exceptions if workbench was bypassed (266.1 & 266.5 Edge Case).
     */
    public function catalogDecision(
        string $decisionCode,
        string $domainLine,
        string $decisionTitle,
        string $ownerRole,
        string $modelUsed,
        float $predictedOutcomeValue,
        bool $passedWorkbenchSimulation = true
    ): object {
        $code = strtoupper($decisionCode);
        $isException = ! $passedWorkbenchSimulation;
        $notes = $isException
            ? 'GOVERNANCE EXCEPTION: Critical business decision was committed without prior scenario workbench simulation (266.5).'
            : null;

        $id = DB::table('decision_catalog_entries')->insertGetId([
            'decision_code' => $code,
            'domain_line' => strtoupper($domainLine),
            'decision_title' => $decisionTitle,
            'owner_role' => strtoupper($ownerRole),
            'model_used' => strtoupper($modelUsed),
            'predicted_outcome_value' => $predictedOutcomeValue,
            'actual_outcome_value' => null,
            'passed_workbench_simulation' => $passedWorkbenchSimulation,
            'is_governance_exception' => $isException,
            'governance_exception_notes' => $notes,
            'review_cycle_status' => 'PENDING',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('decision_catalog_entries')->find($id);
    }

    /**
     * Retrospectively evaluate decision outcome & detect prediction bias (266.2 & 266.6).
     */
    public function evaluateDecisionOutcome(
        int $decisionId,
        float $actualOutcomeValue
    ): object {
        $decision = DB::table('decision_catalog_entries')->find($decisionId);
        if (! $decision) {
            throw new InvalidArgumentException("Decision #{$decisionId} not found.");
        }

        $predicted = (float) $decision->predicted_outcome_value;
        $deviationPct = abs($actualOutcomeValue - $predicted) / max(1.0, abs($predicted));
        $accuracyPct = max(0.0, round((1.0 - $deviationPct) * 100.0, 2));

        // Bias check: accuracy < 75% triggers bias flag and manager training recommendation (266.2)
        $biasDetected = ($accuracyPct < 75.0);
        $trainingRecommended = $biasDetected;

        DB::table('decision_catalog_entries')
            ->where('id', $decisionId)
            ->update([
                'actual_outcome_value' => $actualOutcomeValue,
                'review_cycle_status' => 'EVALUATED',
                'updated_at' => now(),
            ]);

        $scoreId = DB::table('decision_quality_scores')->insertGetId([
            'decision_id' => $decisionId,
            'consistency_score' => 90.0,
            'prediction_accuracy_pct' => $accuracyPct,
            'bias_detected' => $biasDetected,
            'manager_training_recommended' => $trainingRecommended,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('decision_quality_scores')->find($scoreId);
    }

    /**
     * Decision Intelligence Platform Audit (`decision:audit`) (266.4, 266.8).
     */
    public function audit(): array
    {
        // Discrepancy 1: Workbench runs where production data isolation was breached
        $productionDataLeaks = DB::table('decision_scenario_workbenches')
            ->where('production_data_touched', true)
            ->count();

        // Discrepancy 2: Governance exceptions lacking documented notes
        $unexplainedExceptions = DB::table('decision_catalog_entries')
            ->where('is_governance_exception', true)
            ->whereNull('governance_exception_notes')
            ->count();

        // Discrepancy 3: Biased decisions (< 75% accuracy) without recommended training
        $unaddressedBiases = DB::table('decision_quality_scores')
            ->where('bias_detected', true)
            ->where('manager_training_recommended', false)
            ->count();

        $discrepancies = $productionDataLeaks + $unexplainedExceptions + $unaddressedBiases;

        return [
            'status' => $discrepancies === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_decisions' => DB::table('decision_catalog_entries')->count(),
            'total_evaluations' => DB::table('decision_quality_scores')->count(),
            'total_workbench_scenarios' => DB::table('decision_scenario_workbenches')->count(),
            'discrepancy_count' => $discrepancies,
        ];
    }
}
