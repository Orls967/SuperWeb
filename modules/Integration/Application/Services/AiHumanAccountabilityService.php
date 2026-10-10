<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * AiHumanAccountabilityService (Fase 350)
 *
 * Implements:
 *  - 350.3 User transparency: disclosure when AI materially affects user outcome + appeal path
 *  - 350.4 Tests: Disclosure present for affected flows; appeal resolves; ethics:audit clean
 *  - 350.5 Edge case: Appeals require designated human reviewer and strict SLA adherence
 *  - 350.6 Risk: Unmonitored black-box AI decisions prevented
 */
class AiHumanAccountabilityService
{
    /**
     * Issue AI outcome decision with mandatory transparency disclosure (350.3 & 350.4).
     */
    public function issueMaterialOutcomeDecision(
        string $decisionCode,
        string $userId,
        string $category,
        bool $disclosureProvided
    ): object {
        $dCode = strtoupper($decisionCode);

        // Core gate 350.4: Material decisions require disclosure
        if (! $disclosureProvided) {
            throw new InvalidArgumentException('AI ethics violation: Material outcome decision requires user transparency disclosure (350.4).');
        }

        $id = DB::table('ai_material_outcome_decisions')->insertGetId([
            'decision_code' => $dCode,
            'user_id' => strtoupper($userId),
            'outcome_category' => strtoupper($category),
            'transparency_disclosure_provided' => true,
            'has_appeal_path' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('ai_material_outcome_decisions')->find($id);
    }

    /**
     * Resolve AI appeal with human reviewer and SLA breach detection (350.5 Edge Case).
     */
    public function resolveAppeal(
        string $appealCode,
        string $decisionCode,
        ?string $humanReviewerId,
        float $turnaroundHours,
        float $slaHours = 48.00
    ): object {
        $aCode = strtoupper($appealCode);
        $dCode = strtoupper($decisionCode);

        // Edge case 350.5: Appeals require human reviewer
        if (empty($humanReviewerId)) {
            throw new InvalidArgumentException('AI accountability violation: Appeals against AI decisions require an assigned human reviewer (350.5).');
        }

        $slaBreached = ($turnaroundHours > $slaHours);

        $id = DB::table('ai_decision_appeals')->insertGetId([
            'appeal_code' => $aCode,
            'decision_code' => $dCode,
            'assigned_human_reviewer_id' => strtoupper($humanReviewerId),
            'response_turnaround_hours' => $turnaroundHours,
            'sla_hours' => $slaHours,
            'sla_breached' => $slaBreached,
            'status' => 'RESOLVED_HUMAN',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('ai_decision_appeals')->find($id);
    }

    /**
     * AI Ethics & Accountability Audit (`ethics:audit`) (350.4, 350.8).
     */
    public function audit(): array
    {
        // Discrepancy 1: Decisions without disclosure
        $undisclosedDecisions = DB::table('ai_material_outcome_decisions')
            ->where('transparency_disclosure_provided', false)
            ->count();

        // Discrepancy 2: Resolved appeals without human reviewer
        $automatedAppeals = DB::table('ai_decision_appeals')
            ->where('status', 'RESOLVED_HUMAN')
            ->whereNull('assigned_human_reviewer_id')
            ->count();

        $discrepancies = $undisclosedDecisions + $automatedAppeals;

        return [
            'status' => $discrepancies === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_decisions' => DB::table('ai_material_outcome_decisions')->count(),
            'total_appeals' => DB::table('ai_decision_appeals')->count(),
            'discrepancy_count' => $discrepancies,
        ];
    }
}
