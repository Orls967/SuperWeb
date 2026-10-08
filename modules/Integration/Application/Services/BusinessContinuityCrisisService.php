<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * BusinessContinuityCrisisService (Fase 206)
 *
 * Implements:
 *  - 206.1 Business Impact Analysis (BIA) tiering with RTO tracking and drill evaluations
 *  - 206.3 Crisis Command Center virtual war room with approved holding statements
 */
class BusinessContinuityCrisisService
{
    /**
     * Register BIA tier plan with target RTO.
     */
    public function registerContinuityPlan(string $domain, string $tier, int $targetRtoMinutes): object
    {
        $code = 'BIA-'.strtoupper($domain).'-'.strtoupper($tier);

        DB::table('erm_bia_continuity_plans')->updateOrInsert(
            ['plan_code' => $code],
            [
                'domain_code' => strtoupper($domain),
                'continuity_tier' => strtoupper($tier),
                'target_rto_minutes' => $targetRtoMinutes,
                'actual_drill_rto_minutes' => 0,
                'rto_met' => true,
                'updated_at' => now(),
            ]
        );

        return (object) DB::table('erm_bia_continuity_plans')->where('plan_code', $code)->first();
    }

    /**
     * Record annual full-scale drill outcome.
     */
    public function recordDrillResult(string $planCode, int $actualRtoMinutes): object
    {
        $plan = DB::table('erm_bia_continuity_plans')->where('plan_code', $planCode)->first();
        if (! $plan) {
            throw new \InvalidArgumentException("Continuity plan {$planCode} not found.");
        }

        $rtoMet = ($actualRtoMinutes <= (int) $plan->target_rto_minutes);

        DB::table('erm_bia_continuity_plans')->where('plan_code', $planCode)->update([
            'actual_drill_rto_minutes' => $actualRtoMinutes,
            'rto_met' => $rtoMet,
            'updated_at' => now(),
        ]);

        return (object) DB::table('erm_bia_continuity_plans')->where('plan_code', $planCode)->first();
    }

    /**
     * Open Crisis War Room and approve official holding statement.
     */
    public function activateWarRoom(string $scenario, string $statement, string $approvedBy): object
    {
        if (empty(trim($approvedBy))) {
            throw new \InvalidArgumentException('Crisis communication requirement: Holding statement requires approval.');
        }

        $code = 'WR-'.strtoupper(Str::random(8));

        $id = DB::table('erm_crisis_war_rooms')->insertGetId([
            'room_code' => $code,
            'crisis_scenario' => $scenario,
            'status' => 'ACTIVE',
            'public_holding_statement' => $statement,
            'statement_approved_by' => $approvedBy,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('erm_crisis_war_rooms')->find($id);
    }

    /**
     * Quality audit gate (`crisis:audit`).
     */
    public function audit(): array
    {
        $unmetRtos = DB::table('erm_bia_continuity_plans')
            ->where('rto_met', false)
            ->count();

        $unapprovedStatements = DB::table('erm_crisis_war_rooms')
            ->whereNull('statement_approved_by')
            ->count();

        $discrepancies = $unmetRtos + $unapprovedStatements;

        return [
            'status' => $discrepancies === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_continuity_plans' => DB::table('erm_bia_continuity_plans')->count(),
            'total_war_rooms' => DB::table('erm_crisis_war_rooms')->count(),
            'discrepancy_count' => $discrepancies,
        ];
    }
}
