<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * AgentHitlOrchestrationService (Fase 196)
 *
 * Implements:
 *  - 196.1 Agent runtime with strict tool whitelisting and execution step budget
 *  - 196.2 Human-in-the-loop review queue for high-risk actions
 *  - 196.4 Instant kill-switch terminating agent execution immediately
 */
class AgentHitlOrchestrationService
{
    /**
     * Register agent with tool whitelist and step budget.
     */
    public function registerAgent(string $agentCode, string $role, array $toolWhitelist, int $stepBudget = 10): object
    {
        DB::table('ai_agent_runtimes')->updateOrInsert(
            ['agent_code' => strtoupper($agentCode)],
            [
                'agent_role' => strtoupper($role),
                'tool_whitelist' => json_encode($toolWhitelist),
                'max_step_budget' => $stepBudget,
                'is_killed' => false,
                'updated_at' => now(),
            ]
        );

        return (object) DB::table('ai_agent_runtimes')->where('agent_code', strtoupper($agentCode))->first();
    }

    /**
     * Invoke tool action by agent.
     * Enforces: (1) agent not killed, (2) tool is whitelisted, (3) current step does not exceed budget.
     */
    public function executeAgentStep(string $agentCode, string $toolName, int $currentStep): bool
    {
        $agent = DB::table('ai_agent_runtimes')->where('agent_code', strtoupper($agentCode))->first();
        if (! $agent) {
            throw new \InvalidArgumentException("Agent {$agentCode} not found.");
        }

        if ((bool) $agent->is_killed) {
            throw new \RuntimeException("Agent execution blocked: Kill-switch is active for {$agentCode}.");
        }

        if ($currentStep > $agent->max_step_budget) {
            throw new \RuntimeException("Agent execution blocked: Step budget exceeded ({$currentStep} > {$agent->max_step_budget}).");
        }

        $allowedTools = json_decode($agent->tool_whitelist, true);
        if (! in_array($toolName, $allowedTools, true)) {
            throw new \RuntimeException("Agent execution blocked: Tool '{$toolName}' is not in the whitelist for role {$agent->agent_role}.");
        }

        return true;
    }

    /**
     * Enqueue high-risk action for Human-in-the-loop review.
     */
    public function enqueueHitlReview(string $agentCode, string $actionName, float $riskAmountIdr, string $reviewerRole): object
    {
        $code = 'REV-HITL-'.strtoupper(Str::random(8));

        $id = DB::table('ai_hitl_reviews')->insertGetId([
            'review_code' => $code,
            'agent_code' => strtoupper($agentCode),
            'action_name' => strtoupper($actionName),
            'risk_amount_idr' => $riskAmountIdr,
            'required_reviewer_role' => strtoupper($reviewerRole),
            'status' => 'PENDING',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('ai_hitl_reviews')->find($id);
    }

    /**
     * Resolve Human-in-the-loop review (Approve or Reject with mandatory audit reason).
     */
    public function resolveHitlReview(string $reviewCode, bool $approved, string $reason): object
    {
        if (empty(trim($reason))) {
            throw new \InvalidArgumentException('Audit requirement: Reviewer reason cannot be empty.');
        }

        DB::table('ai_hitl_reviews')->where('review_code', $reviewCode)->update([
            'status' => $approved ? 'APPROVED' : 'REJECTED',
            'reviewer_reason' => $reason,
            'updated_at' => now(),
        ]);

        return (object) DB::table('ai_hitl_reviews')->where('review_code', $reviewCode)->first();
    }

    /**
     * Trigger instant kill-switch.
     */
    public function triggerKillSwitch(string $agentCode): object
    {
        DB::table('ai_agent_runtimes')->where('agent_code', strtoupper($agentCode))->update([
            'is_killed' => true,
            'updated_at' => now(),
        ]);

        return (object) DB::table('ai_agent_runtimes')->where('agent_code', strtoupper($agentCode))->first();
    }

    /**
     * Quality audit gate (`agent:audit`).
     */
    public function audit(): array
    {
        // Reviews resolved without audit reason
        $missingReasonReviews = DB::table('ai_hitl_reviews')
            ->whereIn('status', ['APPROVED', 'REJECTED'])
            ->where(function ($query) {
                $query->whereNull('reviewer_reason')
                    ->orWhere('reviewer_reason', '');
            })
            ->count();

        return [
            'status' => $missingReasonReviews === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_agents' => DB::table('ai_agent_runtimes')->count(),
            'total_reviews' => DB::table('ai_hitl_reviews')->count(),
            'discrepancy_count' => $missingReasonReviews,
        ];
    }
}
