<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * LeadershipPipelineExecutiveService (Fase 318)
 *
 * Implements:
 *  - 318.1 Leadership competency assessment and succession readiness scoring
 *  - 318.2 Executive rotation and cross-border assignment tracking
 *  - 318.3 & 318.6 Thin leadership bench strength alerts automatically sent to Board Compensation Committee
 *  - 318.4 Tests: Deterministic readiness, bench alert triggered, hcm:audit clean
 *  - 318.5 Edge case: Successors with readiness < 85% strictly mandate a structured readiness development plan with explicit deadlines (cannot promote directly)
 */
class LeadershipPipelineExecutiveService
{
    /**
     * Register critical leadership role and evaluate bench strength (318.1, 318.3, 318.6 Risk).
     */
    public function registerLeadershipRole(
        string $roleCode,
        string $title,
        string $tierLevel,
        bool $isCriticalRole = true
    ): object {
        $code = strtoupper($roleCode);

        $id = DB::table('leadership_pipeline_roles')->insertGetId([
            'role_code' => $code,
            'title' => $title,
            'tier_level' => strtoupper($tierLevel),
            'is_critical_role' => $isCriticalRole,
            'ready_successor_count' => 0,
            'board_bench_alert_triggered' => $isCriticalRole, // Zero ready successors triggers immediate alert (318.3 & 318.6)
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('leadership_pipeline_roles')->find($id);
    }

    /**
     * Assess succession candidate and enforce readiness plan rules (318.1, 318.4, 318.5 Edge Case).
     */
    public function assessCandidate(
        string $candidateCode,
        string $roleCode,
        string $employeeId,
        float $readinessScore,
        ?string $planDeadline = null
    ): object {
        $cCode = strtoupper($candidateCode);
        $rCode = strtoupper($roleCode);

        $isReadyNow = ($readinessScore >= 85.0);

        // Edge case 318.5: Candidate not ready (< 85%) strictly requires readiness plan with deadline
        if (! $isReadyNow && empty($planDeadline)) {
            throw new InvalidArgumentException("Readiness governance breach: Unready candidate (score {$readinessScore} < 85%) strictly requires a development plan with an explicit deadline (318.5).");
        }

        $id = DB::table('leadership_succession_candidates')->insertGetId([
            'candidate_code' => $cCode,
            'role_code' => $rCode,
            'employee_id' => strtoupper($employeeId),
            'readiness_score' => $readinessScore,
            'is_ready_now' => $isReadyNow,
            'requires_readiness_plan' => ! $isReadyNow,
            'readiness_plan_deadline' => $planDeadline,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Recompute role bench strength (318.3)
        $readyCount = DB::table('leadership_succession_candidates')
            ->where('role_code', $rCode)
            ->where('is_ready_now', true)
            ->count();

        DB::table('leadership_pipeline_roles')
            ->where('role_code', $rCode)
            ->update([
                'ready_successor_count' => $readyCount,
                'board_bench_alert_triggered' => ($readyCount === 0),
                'updated_at' => now(),
            ]);

        return (object) DB::table('leadership_succession_candidates')->find($id);
    }

    /**
     * Human Capital Management Leadership Audit (`hcm:audit`) (318.4, 318.8).
     */
    public function audit(): array
    {
        // Discrepancy 1: Critical roles with 0 ready successors and unalerted board
        $unalertedThinBench = DB::table('leadership_pipeline_roles')
            ->where('is_critical_role', true)
            ->where('ready_successor_count', 0)
            ->where('board_bench_alert_triggered', false)
            ->count();

        // Discrepancy 2: Unready candidates without readiness plan deadline
        $unplannedCandidates = DB::table('leadership_succession_candidates')
            ->where('is_ready_now', false)
            ->whereNull('readiness_plan_deadline')
            ->count();

        $discrepancies = $unalertedThinBench + $unplannedCandidates;

        return [
            'status' => $discrepancies === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_roles' => DB::table('leadership_pipeline_roles')->count(),
            'total_candidates' => DB::table('leadership_succession_candidates')->count(),
            'discrepancy_count' => $discrepancies,
        ];
    }
}
