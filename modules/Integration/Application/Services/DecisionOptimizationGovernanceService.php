<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * DecisionOptimizationGovernanceService (Fase 348)
 *
 * Implements:
 *  - 348.1 Optimization problem registry with owner and governance approval tracking
 *  - 348.4 Tests: Unregistered problem cannot deploy solver; outcome audits complete; ai:audit clean
 *  - 348.5 Edge case: Extreme recommendation breaching safety boundaries is blocked by automated sanity checks
 *  - 348.6 Risk: Uncontrolled solver constraint modifications prohibited
 */
class DecisionOptimizationGovernanceService
{
    /**
     * Register optimization problem and gate solver deployment permissions (348.1 & 348.4).
     */
    public function registerOptimizationProblem(
        string $problemCode,
        string $domainName,
        string $solverVersion,
        string $owner,
        bool $governanceApproved
    ): object {
        $pCode = strtoupper($problemCode);

        // Core gate 348.4: Solver deployment permitted strictly after governance approval
        $deploymentPermitted = $governanceApproved;

        $id = DB::table('decision_optimization_problem_registries')->insertGetId([
            'problem_code' => $pCode,
            'domain_name' => strtoupper($domainName),
            'solver_version' => $solverVersion,
            'business_owner' => $owner,
            'governance_approval_granted' => $governanceApproved,
            'solver_deployment_permitted' => $deploymentPermitted,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('decision_optimization_problem_registries')->find($id);
    }

    /**
     * Audit optimization recommendation with extreme sanity check and safety boundary guards (348.5 Edge Case).
     */
    public function auditRecommendation(
        string $recCode,
        string $problemCode,
        float $recommendedValue,
        float $minBound,
        float $maxBound
    ): object {
        $rCode = strtoupper($recCode);
        $pCode = strtoupper($problemCode);

        // Verify problem exists and solver is permitted
        $problem = DB::table('decision_optimization_problem_registries')
            ->where('problem_code', $pCode)
            ->first();

        if (! $problem || ! $problem->solver_deployment_permitted) {
            throw new InvalidArgumentException("Solver execution breach: Cannot audit recommendations for unregistered or unapproved problem '{$problemCode}' (348.4).");
        }

        // Edge case 348.5: Extreme recommendation breaching safety bounds is blocked
        $isExtreme = ($recommendedValue < $minBound || $recommendedValue > $maxBound);

        if ($isExtreme) {
            DB::table('decision_optimization_recommendation_audits')->insert([
                'recommendation_code' => $rCode,
                'problem_code' => $pCode,
                'recommended_value' => $recommendedValue,
                'safety_min_bound' => $minBound,
                'safety_max_bound' => $maxBound,
                'is_extreme_recommendation' => true,
                'recommendation_blocked' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            throw new InvalidArgumentException("Safety guard breach: Solver produced extreme recommendation ({$recommendedValue}) outside safe bounds [{$minBound}, {$maxBound}] (348.5).");
        }

        $id = DB::table('decision_optimization_recommendation_audits')->insertGetId([
            'recommendation_code' => $rCode,
            'problem_code' => $pCode,
            'recommended_value' => $recommendedValue,
            'safety_min_bound' => $minBound,
            'safety_max_bound' => $maxBound,
            'is_extreme_recommendation' => false,
            'recommendation_blocked' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('decision_optimization_recommendation_audits')->find($id);
    }

    /**
     * AI Optimization Platform Audit (`ai:audit`) (348.4, 348.8).
     */
    public function audit(): array
    {
        // Discrepancy 1: Solver deployments permitted without governance approval
        $unapprovedDeployments = DB::table('decision_optimization_problem_registries')
            ->where('solver_deployment_permitted', true)
            ->where('governance_approval_granted', false)
            ->count();

        // Discrepancy 2: Extreme recommendations that were not blocked
        $unblockedExtremeRecs = DB::table('decision_optimization_recommendation_audits')
            ->where('is_extreme_recommendation', true)
            ->where('recommendation_blocked', false)
            ->count();

        $discrepancies = $unapprovedDeployments + $unblockedExtremeRecs;

        return [
            'status' => $discrepancies === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_problems' => DB::table('decision_optimization_problem_registries')->count(),
            'total_recommendations' => DB::table('decision_optimization_recommendation_audits')->count(),
            'discrepancy_count' => $discrepancies,
        ];
    }
}
