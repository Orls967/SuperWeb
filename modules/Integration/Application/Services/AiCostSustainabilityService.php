<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * AiCostSustainabilityService (Fase 349)
 *
 * Implements:
 *  - 349.1 AI cost attribution per domain with usage tracking
 *  - 349.3 Model tiering policy: high-stakes tasks require reviewed tier; low-risk tasks use efficient tier
 *  - 349.4 Tests: Cost attribution reconciles; tier policy enforced; ai:audit clean
 *  - 349.5 Edge case: Agent loop runaway invocation count (> 20 consecutive) trips circuit breaker immediately to stop cost explosion
 *  - 349.6 Risk: Inconsistent emissions or unmonitored agent costs prevented
 */
class AiCostSustainabilityService
{
    /**
     * Track AI invocation cost with loop runaway circuit breaker protection (349.1, 349.4, 349.5 Edge Case).
     */
    public function trackInvocationCost(
        string $invocationCode,
        string $domainName,
        string $agentId,
        float $costUsd,
        int $consecutiveCount = 1
    ): object {
        $iCode = strtoupper($invocationCode);

        // Edge case 349.5: Agent loop runaway (> 20 calls) trips circuit breaker
        $tripped = ($consecutiveCount > 20);

        if ($tripped) {
            DB::table('ai_cost_sustainability_trackers')->insert([
                'invocation_code' => $iCode,
                'domain_name' => strtoupper($domainName),
                'agent_identifier' => strtoupper($agentId),
                'invocation_cost_usd' => $costUsd,
                'consecutive_invocation_count' => $consecutiveCount,
                'circuit_breaker_tripped' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            throw new InvalidArgumentException("AI runtime circuit breaker tripped: Agent loop detected ({$consecutiveCount} consecutive calls) - cost explosion prevented (349.5).");
        }

        $id = DB::table('ai_cost_sustainability_trackers')->insertGetId([
            'invocation_code' => $iCode,
            'domain_name' => strtoupper($domainName),
            'agent_identifier' => strtoupper($agentId),
            'invocation_cost_usd' => $costUsd,
            'consecutive_invocation_count' => $consecutiveCount,
            'circuit_breaker_tripped' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('ai_cost_sustainability_trackers')->find($id);
    }

    /**
     * Enforce model tiering policy at runtime based on decision stakes (349.3 & 349.4).
     */
    public function enforceModelTierPolicy(
        string $requestCode,
        string $stakes,
        string $modelTier
    ): object {
        $rCode = strtoupper($requestCode);
        $sStakes = strtoupper($stakes);
        $mTier = strtoupper($modelTier);

        // Core gate 349.3 & 349.4: High-stakes tasks cannot use unreviewed/efficient tiers
        if ($sStakes === 'HIGH_STAKES' && $mTier !== 'REVIEWED_PREMIUM') {
            throw new InvalidArgumentException('Model tiering violation: High-stakes decision requires REVIEWED_PREMIUM model tier (349.3).');
        }

        $id = DB::table('ai_model_tiering_runtime_enforcements')->insertGetId([
            'request_code' => $rCode,
            'decision_stakes' => $sStakes,
            'assigned_model_tier' => $mTier,
            'tier_policy_compliant' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('ai_model_tiering_runtime_enforcements')->find($id);
    }

    /**
     * AI Sustainability & Cost Audit (`ai:audit`) (349.4, 349.8).
     */
    public function audit(): array
    {
        // Discrepancy 1: High stakes mapped to non-premium tier
        $nonCompliantTiers = DB::table('ai_model_tiering_runtime_enforcements')
            ->where('decision_stakes', 'HIGH_STAKES')
            ->where('assigned_model_tier', '!=', 'REVIEWED_PREMIUM')
            ->count();

        // Discrepancy 2: Runaway loops (> 20) that did not trip circuit breaker
        $unbrokenLoops = DB::table('ai_cost_sustainability_trackers')
            ->where('consecutive_invocation_count', '>', 20)
            ->where('circuit_breaker_tripped', false)
            ->count();

        $discrepancies = $nonCompliantTiers + $unbrokenLoops;

        return [
            'status' => $discrepancies === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_invocations' => DB::table('ai_cost_sustainability_trackers')->count(),
            'total_tier_enforcements' => DB::table('ai_model_tiering_runtime_enforcements')->count(),
            'discrepancy_count' => $discrepancies,
        ];
    }
}
