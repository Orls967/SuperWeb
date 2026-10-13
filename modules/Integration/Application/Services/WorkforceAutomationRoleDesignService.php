<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * WorkforceAutomationRoleDesignService (Fase 319)
 *
 * Implements:
 *  - 319.1 Role automation assessment (automatable task % calculation & role redesign)
 *  - 319.2 Labor-automation governance & stakeholder consultation approval
 *  - 319.4 Tests: Assessment reproducible, governance approval enforced, hcm:audit clean
 *  - 319.5 Edge case: Automation reducing headcount strictly mandates redeployment and reskilling before any layoffs (just transition)
 *  - 319.6 Risk: Quality/safety counter-metrics guarded to prevent productivity pressure from eroding quality standards
 */
class WorkforceAutomationRoleDesignService
{
    /**
     * Conduct role automation assessment with quality counter-metric guards (319.1, 319.4, 319.6 Risk).
     */
    public function assessRoleAutomation(
        string $assessmentCode,
        string $functionName,
        float $automatableTaskPct,
        float $realizedQualityPct = 99.00,
        float $minQualityThreshold = 98.00
    ): object {
        $aCode = strtoupper($assessmentCode);

        // Counter-metric risk check 319.6: Realized quality cannot fall below safety threshold
        if ($realizedQualityPct < $minQualityThreshold) {
            throw new InvalidArgumentException("Quality counter-metric breach: Automation quality ({$realizedQualityPct}%) falls below safety threshold ({$minQualityThreshold}%) (319.6).");
        }

        $id = DB::table('workforce_automation_assessments')->insertGetId([
            'assessment_code' => $aCode,
            'function_name' => strtoupper($functionName),
            'automatable_task_pct' => $automatableTaskPct,
            'human_quality_counter_metric_min_pct' => $minQualityThreshold,
            'realized_quality_pct' => $realizedQualityPct,
            'labor_governance_approved' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('workforce_automation_assessments')->find($id);
    }

    /**
     * Grant labor-automation governance approval (319.2 & 319.4).
     */
    public function approveLaborGovernance(string $assessmentCode): object
    {
        $aCode = strtoupper($assessmentCode);
        DB::table('workforce_automation_assessments')
            ->where('assessment_code', $aCode)
            ->update([
                'labor_governance_approved' => true,
                'updated_at' => now(),
            ]);

        return (object) DB::table('workforce_automation_assessments')->where('assessment_code', $aCode)->first();
    }

    /**
     * Formulate Just Transition redeployment plan for impacted workforce (319.2 & 319.5 Edge Case).
     */
    public function registerJustTransitionPlan(
        string $planCode,
        string $assessmentCode,
        int $impactedHeadcount,
        int $redeployedOrReskilledCount
    ): object {
        $pCode = strtoupper($planCode);
        $aCode = strtoupper($assessmentCode);

        $assessment = DB::table('workforce_automation_assessments')->where('assessment_code', $aCode)->first();
        if (! $assessment) {
            throw new InvalidArgumentException("Assessment '{$assessmentCode}' not found.");
        }

        // Governance gate 319.2
        if (! $assessment->labor_governance_approved) {
            throw new InvalidArgumentException('Governance breach: Just Transition plan requires approved labor governance on automation assessment (319.2).');
        }

        // Edge case 319.5: 100% redeployment/reskilling must be fulfilled before layoff is ever permitted
        $isFullyRedeployed = ($redeployedOrReskilledCount >= $impactedHeadcount);

        $id = DB::table('workforce_just_transition_plans')->insertGetId([
            'plan_code' => $pCode,
            'assessment_code' => $aCode,
            'impacted_headcount' => $impactedHeadcount,
            'redeployed_or_reskilled_count' => $redeployedOrReskilledCount,
            'just_transition_redeployment_completed' => $isFullyRedeployed,
            'layoff_permitted' => $isFullyRedeployed, // only allowed if transition fulfilled
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('workforce_just_transition_plans')->find($id);
    }

    /**
     * Human Capital Management Automation Audit (`hcm:audit`) (319.4, 319.8).
     */
    public function audit(): array
    {
        // Discrepancy 1: Assessments violating quality counter-metric
        $qualityBreaches = DB::table('workforce_automation_assessments')
            ->whereRaw('realized_quality_pct < human_quality_counter_metric_min_pct')
            ->count();

        // Discrepancy 2: Layoffs permitted while redeployment incomplete
        $unjustLayoffs = DB::table('workforce_just_transition_plans')
            ->where('layoff_permitted', true)
            ->where('just_transition_redeployment_completed', false)
            ->count();

        $discrepancies = $qualityBreaches + $unjustLayoffs;

        return [
            'status' => $discrepancies === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_assessments' => DB::table('workforce_automation_assessments')->count(),
            'total_transition_plans' => DB::table('workforce_just_transition_plans')->count(),
            'discrepancy_count' => $discrepancies,
        ];
    }
}
