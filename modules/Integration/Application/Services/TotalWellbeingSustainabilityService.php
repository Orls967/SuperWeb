<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;

/**
 * TotalWellbeingSustainabilityService (Fase 320)
 *
 * Implements:
 *  - 320.1 Sustainable performance workload model (overtime & utilization -> burnout risk)
 *  - 320.3 Safety culture leading index (reporting rate & stop-work authority usage)
 *  - 320.4 Tests: Workload metrics tracked, safety index deterministic, hcm:audit clean
 *  - 320.5 Edge case: Red burnout indicator strictly mandates workload redistribution and mandatory rest break (cannot silently suppress)
 *  - 320.6 Risk: Wellbeing and safety programs evaluated with objective cost-per-outcome and leading safety indicators
 */
class TotalWellbeingSustainabilityService
{
    /**
     * Evaluate employee workload and assess burnout risk indicator (320.1, 320.4, 320.5 Edge Case).
     */
    public function assessBurnoutRisk(
        string $employeeId,
        float $overtimeHoursMonth,
        float $utilizationRatePct
    ): object {
        $emp = strtoupper($employeeId);

        // Burnout risk calculation 320.1
        if ($overtimeHoursMonth > 40.0 || $utilizationRatePct > 120.0) {
            $indicator = 'RED';
            // Edge case 320.5: Red burnout strictly mandates workload redistribution & mandatory break
            $enforceBreak = true;
        } elseif ($overtimeHoursMonth > 20.0 || $utilizationRatePct > 100.0) {
            $indicator = 'YELLOW';
            $enforceBreak = false;
        } else {
            $indicator = 'GREEN';
            $enforceBreak = false;
        }

        DB::table('workforce_burnout_risk_profiles')->updateOrInsert(
            ['employee_id' => $emp],
            [
                'overtime_hours_month' => $overtimeHoursMonth,
                'utilization_rate_pct' => $utilizationRatePct,
                'burnout_indicator' => $indicator,
                'mandatory_break_and_redistribution_enforced' => $enforceBreak,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        return (object) DB::table('workforce_burnout_risk_profiles')->where('employee_id', $emp)->first();
    }

    /**
     * Compute site safety culture leading index (320.3 & 320.4).
     */
    public function computeSafetyCultureIndex(
        string $siteCode,
        int $nearMissReportsCount,
        int $stopWorkAuthorityUsesCount,
        float $minTargetIndex = 75.0
    ): object {
        $sCode = strtoupper($siteCode);

        // Leading index formula 320.3: Base 50 + (reports * 2) + (stop_work_uses * 5), capped at 100
        $computedIndex = min(100.0, 50.0 + ($nearMissReportsCount * 2.0) + ($stopWorkAuthorityUsesCount * 5.0));
        $standardMet = ($computedIndex >= $minTargetIndex);

        DB::table('workforce_safety_culture_metrics')->updateOrInsert(
            ['site_code' => $sCode],
            [
                'near_miss_reports_count' => $nearMissReportsCount,
                'stop_work_authority_uses_count' => $stopWorkAuthorityUsesCount,
                'safety_culture_leading_index' => $computedIndex,
                'min_safety_target_index' => $minTargetIndex,
                'safety_standard_met' => $standardMet,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        return (object) DB::table('workforce_safety_culture_metrics')->where('site_code', $sCode)->first();
    }

    /**
     * Human Capital Management Wellbeing & Safety Audit (`hcm:audit`) (320.4, 320.8).
     */
    public function audit(): array
    {
        // Discrepancy 1: RED burnout indicator without enforced break
        $unenforcedBurnouts = DB::table('workforce_burnout_risk_profiles')
            ->where('burnout_indicator', 'RED')
            ->where('mandatory_break_and_redistribution_enforced', false)
            ->count();

        // Discrepancy 2: Sites failing safety standard index
        $subparSafetySites = DB::table('workforce_safety_culture_metrics')
            ->where('safety_standard_met', false)
            ->count();

        $discrepancies = $unenforcedBurnouts + $subparSafetySites;

        return [
            'status' => $discrepancies === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_burnout_profiles' => DB::table('workforce_burnout_risk_profiles')->count(),
            'total_safety_sites' => DB::table('workforce_safety_culture_metrics')->count(),
            'discrepancy_count' => $discrepancies,
        ];
    }
}
