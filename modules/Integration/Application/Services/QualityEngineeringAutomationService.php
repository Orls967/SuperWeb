<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * QualityEngineeringAutomationService (Fase 426)
 *
 * Implements:
 *  - 426.1 Test pyramid enforcement: unit/contract/integration/e2e coverage gates per module
 *  - 426.2 Flaky test quarantine with dedicated owner
 *  - 426.3 Mutation-style checks for critical business rules (ledger, pricing, capacity)
 *  - 426.4 Tests: flaky test quarantined not deleted, mutation kill rate >= 80%, coverage gates enforced
 *  - 426.5 Edge case: Tests quarantined > 14 days automatically converted to active blocking defects
 *  - 426.6 Risk: High coverage with weak tests mitigated by mandatory mutation score check
 *  - 426.7 Evidence: coverage report, quarantine list, mutation score
 */
class QualityEngineeringAutomationService
{
    public function evaluateModuleQualityGate(
        string $moduleCode,
        float $unit,
        float $contract,
        float $integration,
        float $e2e,
        float $mutationKillRate
    ): object {
        // Enforce coverage gates: Unit >= 80%, Contract >= 80%, Integration >= 75%, E2E >= 70%
        // Enforce mutation score: Mutation Kill Rate >= 80% (426.3, 426.6)
        $passed = ($unit >= 80.00 && $contract >= 80.00 && $integration >= 75.00 && $e2e >= 70.00 && $mutationKillRate >= 80.00);

        if (! $passed) {
            throw new InvalidArgumentException("Quality gate failed: Module '{$moduleCode}' does not meet required coverage / mutation kill rate (≥80%) thresholds (426.1, 426.3, 426.4).");
        }

        $id = DB::table('plt_quality_gate_modules')->insertGetId([
            'module_code' => strtoupper($moduleCode),
            'unit_coverage_percent' => $unit,
            'contract_coverage_percent' => $contract,
            'integration_coverage_percent' => $integration,
            'e2e_coverage_percent' => $e2e,
            'mutation_kill_rate_percent' => $mutationKillRate,
            'passed_all_gates' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('plt_quality_gate_modules')->where('id', $id)->first();
    }

    public function quarantineFlakyTest(string $signature, string $moduleCode, string $owner): object
    {
        $id = DB::table('plt_flaky_test_quarantine')->insertGetId([
            'test_signature' => $signature,
            'module_code' => strtoupper($moduleCode),
            'owner' => $owner,
            'quarantine_days' => 0,
            'is_flagged_as_defect' => false,
            'status' => 'quarantined',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('plt_flaky_test_quarantine')->where('id', $id)->first();
    }

    /**
     * 426.5 Edge case: Tests left in quarantine > 14 days become active blocking defects
     */
    public function advanceQuarantineDays(string $signature, int $days): object
    {
        $test = DB::table('plt_flaky_test_quarantine')->where('test_signature', $signature)->first();
        if (! $test) {
            throw new InvalidArgumentException("Quarantined test '{$signature}' not found.");
        }

        $newDays = $test->quarantine_days + $days;
        $isDefect = $newDays > 14;
        $status = $isDefect ? 'active_defect' : 'quarantined';

        DB::table('plt_flaky_test_quarantine')->where('id', $test->id)->update([
            'quarantine_days' => $newDays,
            'is_flagged_as_defect' => $isDefect,
            'status' => $status,
            'updated_at' => now(),
        ]);

        return (object) DB::table('plt_flaky_test_quarantine')->where('id', $test->id)->first();
    }

    public function audit(): array
    {
        // Discrepancy: Active defects that have not been resolved
        $activeDefects = DB::table('plt_flaky_test_quarantine')
            ->where('is_flagged_as_defect', true)
            ->where('status', 'active_defect')
            ->count();

        return [
            'status' => $activeDefects === 0 ? 'HEALTHY' : 'DEFECTS_DETECTED',
            'total_modules_passed' => DB::table('plt_quality_gate_modules')->count(),
            'total_quarantined' => DB::table('plt_flaky_test_quarantine')->count(),
            'active_defects' => $activeDefects,
        ];
    }
}
