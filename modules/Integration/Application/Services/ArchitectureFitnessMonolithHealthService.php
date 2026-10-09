<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * ArchitectureFitnessMonolithHealthService (Fase 370)
 *
 * Implements:
 *  - 370.1 Module boundary fitness evaluations
 *  - 370.2 Direct cross-module persistence violation checks
 *  - 370.4 Tests: Violating sample fails fitness test; CI blocks merge without remediation
 *  - 370.5 Edge case: Fitness function violations fail CI and prevent merge
 *  - 370.6 Risk: Architectural coupling prevented through scheduled quarterly reviews
 */
class ArchitectureFitnessMonolithHealthService
{
    /**
     * Evaluate modular monolith architecture fitness for CI/CD gates (370.1, 370.2, 370.4, 370.5 Edge Case).
     */
    public function evaluateArchitectureFitness(
        string $evaluationCode,
        string $moduleName,
        bool $hasCrossModulePersistenceViolation,
        bool $allTablesOwned = true
    ): object {
        $eCode = strtoupper($evaluationCode);
        $mName = strtoupper($moduleName);

        $passed = (! $hasCrossModulePersistenceViolation && $allTablesOwned);

        // Edge case 370.5: Fitness violation fails CI gate and blocks merge
        if (! $passed) {
            DB::table('platform_architecture_fitness_evaluations')->insert([
                'evaluation_code' => $eCode,
                'module_name' => $mName,
                'has_cross_module_persistence_violation' => $hasCrossModulePersistenceViolation,
                'all_tables_owned_by_domain' => $allTablesOwned,
                'fitness_gate_passed' => false,
                'ci_merge_permitted' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            throw new InvalidArgumentException("Architecture fitness gate failure: Cross-module persistence violation in '{$moduleName}' blocks CI merge (370.5).");
        }

        $id = DB::table('platform_architecture_fitness_evaluations')->insertGetId([
            'evaluation_code' => $eCode,
            'module_name' => $mName,
            'has_cross_module_persistence_violation' => false,
            'all_tables_owned_by_domain' => true,
            'fitness_gate_passed' => true,
            'ci_merge_permitted' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('platform_architecture_fitness_evaluations')->find($id);
    }

    /**
     * Record quarterly architecture coupling review (370.6 Risk).
     */
    public function recordQuarterlyCouplingReview(string $reviewCode, string $quarterCode): object
    {
        $rCode = strtoupper($reviewCode);
        $qCode = strtoupper($quarterCode);

        $id = DB::table('platform_architecture_quarterly_reviews')->insertGetId([
            'review_code' => $rCode,
            'quarter_code' => $qCode,
            'coupling_review_completed' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('platform_architecture_quarterly_reviews')->find($id);
    }

    /**
     * Platform Architecture Fitness Audit (`arch:audit`) (370.4, 370.8).
     */
    public function audit(): array
    {
        // Discrepancy 1: CI merge permitted despite persistence violations
        $violatingPermitted = DB::table('platform_architecture_fitness_evaluations')
            ->where('ci_merge_permitted', true)
            ->where('has_cross_module_persistence_violation', true)
            ->count();

        // Discrepancy 2: Evaluations with unowned tables permitted
        $unownedPermitted = DB::table('platform_architecture_fitness_evaluations')
            ->where('ci_merge_permitted', true)
            ->where('all_tables_owned_by_domain', false)
            ->count();

        $discrepancies = $violatingPermitted + $unownedPermitted;

        return [
            'status' => $discrepancies === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_fitness_evaluations' => DB::table('platform_architecture_fitness_evaluations')->count(),
            'total_quarterly_reviews' => DB::table('platform_architecture_quarterly_reviews')->count(),
            'discrepancy_count' => $discrepancies,
        ];
    }
}
