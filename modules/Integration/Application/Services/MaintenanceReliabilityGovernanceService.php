<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * MaintenanceReliabilityGovernanceService (Fase 413)
 *
 * Implements:
 *  - 413.1 Asset criticality ranking (A_CRITICAL, B_MEDIUM, C_LOW) and strategy selection
 *  - 413.2 PM compliance rate and backlog aging metrics
 *  - 413.3 Reliability improvement: chronic failure analysis -> change -> effectiveness verification
 *  - 413.4 Tests: strategy selection reproducible, PM compliance measurable, ast:audit clean
 *  - 413.5 Edge case: Repeatedly missed PMs (aging > 30 days) automatically escalate to area supervisor
 *  - 413.6 Risk: Aging threshold prevents maintenance backlog pileup
 *  - 413.7 Evidence: criticality ranking, PM compliance, improvement verification
 */
class MaintenanceReliabilityGovernanceService
{
    public function registerProgram(
        string $programCode,
        string $assetClass,
        string $criticalityRank,
        string $strategy,
        string $engineer
    ): object {
        $id = DB::table('ops_maintenance_programs')->insertGetId([
            'program_code' => strtoupper($programCode),
            'asset_class' => strtoupper($assetClass),
            'criticality_rank' => strtoupper($criticalityRank),
            'strategy' => strtolower($strategy),
            'pm_compliance_rate' => 100.00,
            'backlog_aging_days' => 0,
            'escalated_to_supervisor' => false,
            'responsible_engineer' => $engineer,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('ops_maintenance_programs')->where('id', $id)->first();
    }

    /**
     * 413.2, 413.5, 413.6 Update PM compliance & backlog aging with automatic supervisor escalation
     */
    public function updatePmCompliance(string $programCode, float $complianceRate, int $backlogAgingDays): object
    {
        $prog = DB::table('ops_maintenance_programs')->where('program_code', strtoupper($programCode))->first();
        if (! $prog) {
            throw new InvalidArgumentException("Program '{$programCode}' not found.");
        }

        // 413.5 Edge case & 413.6 Risk: PM backlog aging > 30 days or compliance < 80% triggers mandatory escalation
        $shouldEscalate = ($backlogAgingDays > 30 || $complianceRate < 80.00);

        DB::table('ops_maintenance_programs')->where('id', $prog->id)->update([
            'pm_compliance_rate' => $complianceRate,
            'backlog_aging_days' => $backlogAgingDays,
            'escalated_to_supervisor' => $shouldEscalate,
            'updated_at' => now(),
        ]);

        return (object) DB::table('ops_maintenance_programs')->where('id', $prog->id)->first();
    }

    public function recordReliabilityImprovement(
        string $programCode,
        string $improvementCode,
        string $chronicFailureCause,
        string $changeDetails,
        bool $verified = true
    ): object {
        $prog = DB::table('ops_maintenance_programs')->where('program_code', strtoupper($programCode))->first();
        if (! $prog) {
            throw new InvalidArgumentException("Program '{$programCode}' not found.");
        }

        $id = DB::table('ops_reliability_improvements')->insertGetId([
            'program_id' => $prog->id,
            'improvement_code' => strtoupper($improvementCode),
            'chronic_failure_cause' => $chronicFailureCause,
            'design_or_operating_change' => $changeDetails,
            'effectiveness_verified' => $verified,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('ops_reliability_improvements')->where('id', $id)->first();
    }

    public function audit(): array
    {
        // Discrepancy 1: High backlog aging (> 30 days) that was not escalated
        $unescalatedBacklog = DB::table('ops_maintenance_programs')
            ->where('backlog_aging_days', '>', 30)
            ->where('escalated_to_supervisor', false)
            ->count();

        // Discrepancy 2: Reliability improvements with unverified effectiveness
        $unverifiedImprovements = DB::table('ops_reliability_improvements')
            ->where('effectiveness_verified', false)
            ->count();

        $totalDiscrepancies = $unescalatedBacklog + $unverifiedImprovements;

        return [
            'status' => $totalDiscrepancies === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'unescalated_backlog' => $unescalatedBacklog,
            'unverified_improvements' => $unverifiedImprovements,
            'discrepancy_count' => $totalDiscrepancies,
        ];
    }
}
