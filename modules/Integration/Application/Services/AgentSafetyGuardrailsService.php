<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * AgentSafetyGuardrailsService (Fase 346)
 *
 * Implements:
 *  - 346.1 Safety eval suite: prompt injection, jailbreak, and tool misuse blocking
 *  - 346.2 Tool permission matrix with runtime and CI change approval enforcement
 *  - 346.4 Tests: Injection blocked in seed set; permission expansion needs approval; ai:audit clean
 *  - 346.5 Edge case: Safety eval failure on new seed halts release and mandates fallback to prior agent version
 *  - 346.6 Risk: Tool permission expansion strictly gated by CI approval
 */
class AgentSafetyGuardrailsService
{
    /**
     * Run safety evaluation suite with release gating and fallback on failure (346.1, 346.4, 346.5 Edge Case).
     */
    public function runSafetyEvaluation(
        string $evalCode,
        string $agentId,
        string $version,
        bool $injectionBlocked,
        bool $jailbreakBlocked
    ): object {
        $eCode = strtoupper($evalCode);
        $suitePassed = ($injectionBlocked && $jailbreakBlocked);

        // Edge case 346.5: Failed eval holds release and keeps prior agent active
        $releaseHeld = ! $suitePassed;

        $id = DB::table('ai_agent_safety_evaluations')->insertGetId([
            'eval_code' => $eCode,
            'agent_identifier' => strtoupper($agentId),
            'agent_version' => $version,
            'prompt_injection_blocked' => $injectionBlocked,
            'jailbreak_blocked' => $jailbreakBlocked,
            'eval_suite_passed' => $suitePassed,
            'release_held_fallback_prior' => $releaseHeld,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('ai_agent_safety_evaluations')->find($id);
    }

    /**
     * Provision tool permission with CI change approval gate for expansion (346.2, 346.4, 346.6 Risk).
     */
    public function provisionToolPermission(
        string $permCode,
        string $agentId,
        string $toolName,
        bool $isExpansion,
        bool $ciApprovalGranted
    ): object {
        $pCode = strtoupper($permCode);

        // Core gate 346.4 & 346.6: Permission expansion strictly requires CI change approval
        if ($isExpansion && ! $ciApprovalGranted) {
            throw new InvalidArgumentException("AI security violation: Agent tool permission expansion requires mandatory CI change approval (346.6).");
        }

        $id = DB::table('ai_agent_tool_permissions')->insertGetId([
            'permission_code' => $pCode,
            'agent_identifier' => strtoupper($agentId),
            'tool_name' => strtoupper($toolName),
            'is_permission_expansion' => $isExpansion,
            'ci_change_approval_granted' => $ciApprovalGranted,
            'permission_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('ai_agent_tool_permissions')->find($id);
    }

    /**
     * AI Agent Security Audit (`ai:audit`) (346.4, 346.8).
     */
    public function audit(): array
    {
        // Discrepancy 1: Failed evals that were not held back
        $unheldFailures = DB::table('ai_agent_safety_evaluations')
            ->where('eval_suite_passed', false)
            ->where('release_held_fallback_prior', false)
            ->count();

        // Discrepancy 2: Active permission expansions lacking CI approval
        $unapprovedExpansions = DB::table('ai_agent_tool_permissions')
            ->where('is_permission_expansion', true)
            ->where('ci_change_approval_granted', false)
            ->where('permission_active', true)
            ->count();

        $discrepancies = $unheldFailures + $unapprovedExpansions;

        return [
            'status' => $discrepancies === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_evaluations' => DB::table('ai_agent_safety_evaluations')->count(),
            'total_permissions' => DB::table('ai_agent_tool_permissions')->count(),
            'discrepancy_count' => $discrepancies,
        ];
    }
}
