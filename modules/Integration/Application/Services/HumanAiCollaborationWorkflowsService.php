<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * HumanAiCollaborationWorkflowsService (Fase 270)
 *
 * Implements:
 *  - 270.1 Role-based Copilot: AI recommends, human decides, decision strictly recorded with reviewer identity
 *  - 270.2 Continuous skill augmentation loop tracking human-AI disagreement rates
 *  - 270.3 HITL review queue optimization & workload SLA compliance
 *  - 270.4 Architectural guard: AI never auto-executes in HITL critical roles
 *  - 270.5 Edge case: 100% human disagreement rate triggers model/UX review instead of forcing adoption
 *  - 270.6 Intentional honeypot samples injected to test review diligence and prevent rubber-stamping
 *  - 270.7 Reviewer accountability logged with reviewer user ID
 */
class HumanAiCollaborationWorkflowsService
{
    /**
     * Create copilot AI recommendation enforcing non-auto-execution (270.1 & 270.4).
     */
    public function createSuggestion(
        string $roleCode,
        string $modelVersion,
        string $aiRecommendation,
        bool $isHoneypot = false
    ): object {
        $code = 'SUG-'.strtoupper(Str::random(8));

        $id = DB::table('hitl_copilot_suggestions')->insertGetId([
            'suggestion_code' => $code,
            'role_code' => strtoupper($roleCode),
            'model_version' => $modelVersion,
            'ai_recommendation' => $aiRecommendation,
            'is_auto_executed' => false, // Architectural guarantee (270.4)
            'human_decision' => 'PENDING',
            'reviewer_user_id' => null,
            'reviewer_notes' => null,
            'is_honeypot_sample' => $isHoneypot,
            'honeypot_passed' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('hitl_copilot_suggestions')->find($id);
    }

    /**
     * Human reviewer records decision with accountability and honeypot validation (270.1, 270.6, 270.7).
     */
    public function recordHumanDecision(
        string $suggestionCode,
        string $reviewerUserId,
        string $decision, // ACCEPTED, REJECTED, MODIFIED
        ?string $notes = null
    ): object {
        $code = strtoupper($suggestionCode);
        $sug = DB::table('hitl_copilot_suggestions')->where('suggestion_code', $code)->first();
        if (! $sug) {
            throw new InvalidArgumentException("Suggestion '{$suggestionCode}' not found.");
        }

        $decUpper = strtoupper($decision);
        $honeypotPassed = true;

        // Honeypot test (270.6): A deliberate erroneous recommendation that is ACCEPTED indicates sloppy rubber-stamping
        if ($sug->is_honeypot_sample && $decUpper === 'ACCEPTED') {
            $honeypotPassed = false;
        }

        DB::table('hitl_copilot_suggestions')
            ->where('suggestion_code', $code)
            ->update([
                'human_decision' => $decUpper,
                'reviewer_user_id' => strtoupper($reviewerUserId),
                'reviewer_notes' => $notes,
                'honeypot_passed' => $honeypotPassed,
                'updated_at' => now(),
            ]);

        return (object) DB::table('hitl_copilot_suggestions')->where('suggestion_code', $code)->first();
    }

    /**
     * Update review queue metrics, evaluate disagreement rates & trigger model review on 100% rejection (270.2, 270.3, 270.5 Edge Case).
     */
    public function updateQueueMetrics(
        string $queueCode,
        string $roleCode,
        int $pendingItems,
        int $slaBreaches,
        decimal|float $disagreementRatePct
    ): object {
        $code = strtoupper($queueCode);
        $role = strtoupper($roleCode);

        // Edge case 270.5: 100% human disagreement rate triggers mandatory model review instead of forced adoption
        $modelReviewRequired = ($disagreementRatePct >= 100.0);

        DB::table('hitl_review_queues')->updateOrInsert(
            ['queue_code' => $code],
            [
                'role_code' => $role,
                'pending_items_count' => $pendingItems,
                'sla_target_minutes' => 15,
                'sla_breached_count' => $slaBreaches,
                'disagreement_rate_pct' => $disagreementRatePct,
                'model_review_required' => $modelReviewRequired,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        return (object) DB::table('hitl_review_queues')->where('queue_code', $code)->first();
    }

    /**
     * Create skill augmentation curriculum from human disagreement feedback (270.2).
     */
    public function createAugmentationCurriculum(
        string $curriculumCode,
        string $targetRole,
        string $disagreementPattern,
        string $moduleNotes
    ): object {
        $id = DB::table('hitl_skill_augmentations')->insertGetId([
            'curriculum_code' => strtoupper($curriculumCode),
            'target_role' => strtoupper($targetRole),
            'disagreement_pattern' => $disagreementPattern,
            'training_module_notes' => $moduleNotes,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('hitl_skill_augmentations')->find($id);
    }

    /**
     * HITL Collaboration Platform Audit (`hitl:audit`) (270.4, 270.8).
     */
    public function audit(): array
    {
        // Discrepancy 1: Any AI suggestion auto-executed in HITL role
        $autoExecutedInHitl = DB::table('hitl_copilot_suggestions')
            ->where('is_auto_executed', true)
            ->count();

        // Discrepancy 2: Decided suggestions missing reviewer user ID
        $unaccountableDecisions = DB::table('hitl_copilot_suggestions')
            ->where('human_decision', '!=', 'PENDING')
            ->whereNull('reviewer_user_id')
            ->count();

        // Discrepancy 3: Queues with 100% disagreement lacking model review flag
        $unreviewedModelRejections = DB::table('hitl_review_queues')
            ->where('disagreement_rate_pct', '>=', 100.0)
            ->where('model_review_required', false)
            ->count();

        $discrepancies = $autoExecutedInHitl + $unaccountableDecisions + $unreviewedModelRejections;

        return [
            'status' => $discrepancies === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_suggestions' => DB::table('hitl_copilot_suggestions')->count(),
            'total_queues' => DB::table('hitl_review_queues')->count(),
            'total_curriculums' => DB::table('hitl_skill_augmentations')->count(),
            'discrepancy_count' => $discrepancies,
        ];
    }
}
