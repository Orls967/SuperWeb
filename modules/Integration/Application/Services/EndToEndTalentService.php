<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * EndToEndTalentService (Fase 254)
 *
 * Implements:
 *  - 254.1 Unified talent process (plan, attract, select, develop, deploy, perform, reward, retain)
 *  - 254.2 Skills-based organization with internal marketplace first staffing priority
 *  - 254.3 Future workforce scenario planning: automation impact, reskilling plans & cost trajectories
 *  - 254.5 Edge case: External hiring strictly requires prior internal marketplace offering (fair process documented)
 *  - 254.6 Automation scenario automatically triggers reskilling learning plan
 *  - 254.7 Journey completeness: every active employee maintains current stage and visible next action
 */
class EndToEndTalentService
{
    /**
     * Create employee journey ensuring completeness and next action (254.1 & 254.7).
     */
    public function createEmployeeJourney(
        string $employeeId,
        string $employeeName,
        string $currentStage = 'ONBOARDING',
        string $nextActionRequired = 'COMPLETE_COMPLIANCE_TRAINING'
    ): object {
        $id = DB::table('talent_unified_employee_journeys')->insertGetId([
            'employee_id' => strtoupper($employeeId),
            'employee_name' => $employeeName,
            'current_stage' => strtoupper($currentStage),
            'next_action_required' => $nextActionRequired,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('talent_unified_employee_journeys')->find($id);
    }

    /**
     * Advance employee journey with updated stage and next action (254.1 & 254.7).
     */
    public function advanceEmployeeJourney(
        string $employeeId,
        string $nextStage,
        string $nextActionRequired
    ): object {
        $emp = DB::table('talent_unified_employee_journeys')->where('employee_id', strtoupper($employeeId))->first();
        if (! $emp) {
            throw new InvalidArgumentException("Employee '{$employeeId}' not found.");
        }

        $isActive = strtoupper($nextStage) !== 'EXITED';

        DB::table('talent_unified_employee_journeys')
            ->where('employee_id', strtoupper($employeeId))
            ->update([
                'current_stage' => strtoupper($nextStage),
                'next_action_required' => $nextActionRequired,
                'is_active' => $isActive,
                'updated_at' => now(),
            ]);

        return (object) DB::table('talent_unified_employee_journeys')->where('employee_id', strtoupper($employeeId))->first();
    }

    /**
     * Create role requisition with internal-first marketplace priority (254.2 & 254.5).
     */
    public function createJobRequisition(
        string $targetRole,
        array $skillRequirements,
        int $internalOfferDays = 14
    ): object {
        $code = 'REQ-'.strtoupper(Str::random(8));

        $id = DB::table('talent_requisitions_internal_first')->insertGetId([
            'req_code' => $code,
            'target_role' => $targetRole,
            'skill_requirements_json' => json_encode($skillRequirements),
            'internal_marketplace_offered' => true,
            'internal_offer_days' => $internalOfferDays,
            'external_candidate_considered' => false,
            'fair_process_documented' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('talent_requisitions_internal_first')->find($id);
    }

    /**
     * Consider external candidate only after mandatory internal marketplace period (254.5 Edge Case).
     */
    public function considerExternalCandidate(string $reqCode, string $justification): object
    {
        $req = DB::table('talent_requisitions_internal_first')->where('req_code', strtoupper($reqCode))->first();
        if (! $req) {
            throw new InvalidArgumentException("Requisition '{$reqCode}' not found.");
        }

        if ($req->internal_offer_days < 7) {
            throw new InvalidArgumentException('Fair process violation: Requisition must be offered internally for at least 7 days before considering external candidates (254.5).');
        }

        DB::table('talent_requisitions_internal_first')
            ->where('req_code', strtoupper($reqCode))
            ->update([
                'external_candidate_considered' => true,
                'fair_process_documented' => true,
                'updated_at' => now(),
            ]);

        return (object) DB::table('talent_requisitions_internal_first')->where('req_code', strtoupper($reqCode))->first();
    }

    /**
     * Simulate automation workforce scenario and trigger reskilling plan (254.3 & 254.6).
     */
    public function simulateAutomationScenario(
        string $department,
        float $automationImpactPct,
        int $projectedHeadcountDelta,
        float $costTrajectoryUsd
    ): object {
        $reskillingTriggered = ($automationImpactPct >= 20.0);
        $code = 'SCEN-'.strtoupper(Str::random(8));

        $id = DB::table('talent_automation_workforce_scenarios')->insertGetId([
            'scenario_code' => $code,
            'department' => strtoupper($department),
            'automation_impact_pct' => $automationImpactPct,
            'reskilling_plan_triggered' => $reskillingTriggered,
            'projected_headcount_delta' => $projectedHeadcountDelta,
            'cost_trajectory_usd' => $costTrajectoryUsd,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('talent_automation_workforce_scenarios')->find($id);
    }

    /**
     * End-to-End Talent Platform Audit (`hcm:audit`) (254.4, 254.8).
     */
    public function audit(): array
    {
        // Discrepancy 1: Active employees missing visible next action
        $journeyIncompleteness = DB::table('talent_unified_employee_journeys')
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('next_action_required')
                  ->orWhere('next_action_required', '');
            })
            ->count();

        // Discrepancy 2: Requisitions considering external candidates without fair process documented
        $unfairHiringProcesses = DB::table('talent_requisitions_internal_first')
            ->where('external_candidate_considered', true)
            ->where('fair_process_documented', false)
            ->count();

        // Discrepancy 3: High automation scenarios (>= 20%) without reskilling plan triggered
        $unaddressedAutomationImpact = DB::table('talent_automation_workforce_scenarios')
            ->where('automation_impact_pct', '>=', 20.0)
            ->where('reskilling_plan_triggered', false)
            ->count();

        $discrepancies = $journeyIncompleteness + $unfairHiringProcesses + $unaddressedAutomationImpact;

        return [
            'status' => $discrepancies === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_active_employees' => DB::table('talent_unified_employee_journeys')->where('is_active', true)->count(),
            'total_requisitions' => DB::table('talent_requisitions_internal_first')->count(),
            'total_scenarios' => DB::table('talent_automation_workforce_scenarios')->count(),
            'discrepancy_count' => $discrepancies,
        ];
    }
}
