<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * UnifiedControlTowerDecisionService (Fase 388)
 *
 * Implements:
 *  - 388.2 Decision loop: recommendation -> delegated approval -> execution
 *  - 388.4 Tests: Recommendation cannot execute without required delegated approval
 *  - 388.5 Edge case: Stalled approval at level 1 triggers automated escalation to next tier
 *  - 388.6 Risk: Execution without approval strictly rejected by system, not just warned
 */
class UnifiedControlTowerDecisionService
{
    /**
     * Create decision recommendation (388.2 & 388.4).
     */
    public function createRecommendation(
        string $decisionCode,
        string $recommendationTitle,
        bool $requiresDelegatedApproval = true
    ): object {
        $dCode = strtoupper($decisionCode);

        $id = DB::table('global_control_tower_decision_loops')->insertGetId([
            'decision_code' => $dCode,
            'recommendation_title' => $recommendationTitle,
            'requires_delegated_approval' => $requiresDelegatedApproval,
            'approval_granted' => false,
            'decision_executed' => false,
            'approval_escalation_tier' => 'TIER_1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('global_control_tower_decision_loops')->find($id);
    }

    /**
     * Escalate stalled approval to next tier (388.3 & 388.5 Edge Case).
     */
    public function escalateStalledApproval(string $decisionCode): object
    {
        $dCode = strtoupper($decisionCode);

        // Edge case 388.5: Stalled approval aging alert escalates to Tier 2
        DB::table('global_control_tower_decision_loops')
            ->where('decision_code', $dCode)
            ->update([
                'approval_escalation_tier' => 'TIER_2_EXECUTIVE_ESCALATED',
                'updated_at' => now(),
            ]);

        return (object) DB::table('global_control_tower_decision_loops')->where('decision_code', $dCode)->first();
    }

    /**
     * Grant approval and execute recommendation (388.2, 388.4, 388.6 Risk).
     */
    public function executeDecision(
        string $decisionCode,
        bool $approvalGranted = false
    ): object {
        $dCode = strtoupper($decisionCode);

        $decision = DB::table('global_control_tower_decision_loops')
            ->where('decision_code', $dCode)
            ->first();

        // Core gate 388.4 & 388.6 Risk: Execution without approval strictly rejected
        if ($decision && $decision->requires_delegated_approval && ! $approvalGranted) {
            throw new InvalidArgumentException("Governance block: Decision '{$decisionCode}' requires delegated approval before execution; unauthorized execution rejected (388.6).");
        }

        DB::table('global_control_tower_decision_loops')
            ->where('decision_code', $dCode)
            ->update([
                'approval_granted' => true,
                'decision_executed' => true,
                'updated_at' => now(),
            ]);

        return (object) DB::table('global_control_tower_decision_loops')->where('decision_code', $dCode)->first();
    }

    /**
     * Control Tower Decision Audit (388.4, 388.8).
     */
    public function audit(): array
    {
        // Discrepancy: Decisions executed without approval granted
        $unapprovedExecutions = DB::table('global_control_tower_decision_loops')
            ->where('decision_executed', true)
            ->where('requires_delegated_approval', true)
            ->where('approval_granted', false)
            ->count();

        return [
            'status' => $unapprovedExecutions === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_decisions' => DB::table('global_control_tower_decision_loops')->count(),
            'discrepancy_count' => $unapprovedExecutions,
        ];
    }
}
