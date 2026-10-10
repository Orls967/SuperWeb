<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * CustomerServiceAgenticCommerceService (Fase 353)
 *
 * Implements:
 *  - 353.1 Autonomous tier-1 service resolution and escalation with full context handoff
 *  - 353.1 & 353.4 Refund limits: No autonomous refunds permitted above threshold ($100)
 *  - 353.3 & 353.6 User trust controls: Mandatory AI disclosure and human opt-out
 *  - 353.4 Tests: Escalation works; refund limit enforced; crm:audit clean
 *  - 353.5 Edge case: Unresolvable agent session triggers full context handoff to human agent without data loss
 */
class CustomerServiceAgenticCommerceService
{
    /**
     * Start and manage customer service session with full context handoff on escalation (353.1, 353.3, 353.5 Edge Case).
     */
    public function handleSession(
        string $sessionCode,
        string $customerId,
        bool $disclosureProvided,
        bool $resolvedAutonomously,
        ?string $handoffContext = null
    ): object {
        $sCode = strtoupper($sessionCode);

        // Core gate 353.3 & 353.6: Disclosure is mandatory
        if (! $disclosureProvided) {
            throw new InvalidArgumentException('Customer trust violation: Session requires mandatory AI transparency disclosure (353.6).');
        }

        // Edge case 353.5: Failed resolution triggers handoff with complete context
        $escalated = ! $resolvedAutonomously;
        if ($escalated && empty($handoffContext)) {
            throw new InvalidArgumentException('Agent handoff error: Escalation requires non-empty context payload to prevent customer data loss (353.5).');
        }

        $id = DB::table('customer_service_agent_sessions')->insertGetId([
            'session_code' => $sCode,
            'customer_id' => strtoupper($customerId),
            'ai_disclosure_provided' => true,
            'resolved_autonomously' => $resolvedAutonomously,
            'escalated_to_human' => $escalated,
            'handoff_context_payload' => $handoffContext,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('customer_service_agent_sessions')->find($id);
    }

    /**
     * Process commerce refund with autonomous threshold guard (353.1 & 353.4).
     */
    public function processCommerceRefund(
        string $refundCode,
        string $customerId,
        float $amountUsd,
        float $autonomousLimitUsd = 100.00
    ): object {
        $rCode = strtoupper($refundCode);

        // Core gate 353.1 & 353.4: Over-limit refunds cannot be autonomously approved
        $exceedsLimit = ($amountUsd > $autonomousLimitUsd);

        $id = DB::table('agentic_commerce_refund_requests')->insertGetId([
            'refund_code' => $rCode,
            'customer_id' => strtoupper($customerId),
            'refund_amount_usd' => $amountUsd,
            'autonomous_limit_usd' => $autonomousLimitUsd,
            'autonomous_approved' => ! $exceedsLimit,
            'requires_manager_review' => $exceedsLimit,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('agentic_commerce_refund_requests')->find($id);
    }

    /**
     * CRM & Customer Service Audit (`crm:audit`) (353.4, 353.8).
     */
    public function audit(): array
    {
        // Discrepancy 1: Over-limit refunds approved autonomously
        $improperRefunds = DB::table('agentic_commerce_refund_requests')
            ->where('refund_amount_usd', '>', DB::raw('autonomous_limit_usd'))
            ->where('autonomous_approved', true)
            ->count();

        // Discrepancy 2: Escalated sessions without context payload
        $emptyHandoffs = DB::table('customer_service_agent_sessions')
            ->where('escalated_to_human', true)
            ->whereNull('handoff_context_payload')
            ->count();

        $discrepancies = $improperRefunds + $emptyHandoffs;

        return [
            'status' => $discrepancies === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_sessions' => DB::table('customer_service_agent_sessions')->count(),
            'total_refunds' => DB::table('agentic_commerce_refund_requests')->count(),
            'discrepancy_count' => $discrepancies,
        ];
    }
}
