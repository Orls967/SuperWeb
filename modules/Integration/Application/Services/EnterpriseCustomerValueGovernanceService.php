<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * EnterpriseCustomerValueGovernanceService (Fase 463)
 *
 * Implements:
 *  - 463.1 Customer value proposition across 30 lines
 *  - 463.2 Customer value measurement: delivered vs promised, value gap
 *  - 463.3 Value-based selling enablement with evidence-based proof points
 *  - 463.4 Tests: value proposition documented, gap measurement valid, crm:audit clean
 *  - 463.5 Edge case: Large value gap (> 10%) automatically forces prioritized improvement backlog
 *  - 463.6 Risk: Unverified marketing value claims cannot be approved for sales collateral
 *  - 463.7 Evidence: value proposition doc, gap measurement, win/loss learnings
 */
class EnterpriseCustomerValueGovernanceService
{
    public function registerProposition(string $lineCode, string $title, float $promisedSla): object
    {
        $id = DB::table('int_customer_value_propositions')->insertGetId([
            'line_code' => strtoupper($lineCode),
            'proposition_title' => $title,
            'promised_sla_percentage' => $promisedSla,
            'actual_delivered_sla_percentage' => $promisedSla,
            'value_gap_percentage' => 0.00,
            'has_prioritized_improvement_backlog' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('int_customer_value_propositions')->where('id', $id)->first();
    }

    /**
     * 463.2 & 463.5 Record actual delivered value and compute value gap
     */
    public function recordDeliveredValue(string $lineCode, float $actualDeliveredSla): object
    {
        $prop = DB::table('int_customer_value_propositions')->where('line_code', strtoupper($lineCode))->first();
        if (! $prop) {
            throw new InvalidArgumentException("Value proposition for line '{$lineCode}' not found.");
        }

        $gap = max(0.00, (float) $prop->promised_sla_percentage - $actualDeliveredSla);

        // 463.5 Edge case: Significant value gap (> 10%) mandates prioritized improvement backlog
        $requiresBacklog = ($gap > 10.00);

        DB::table('int_customer_value_propositions')->where('id', $prop->id)->update([
            'actual_delivered_sla_percentage' => $actualDeliveredSla,
            'value_gap_percentage' => $gap,
            'has_prioritized_improvement_backlog' => $requiresBacklog,
            'updated_at' => now(),
        ]);

        return (object) DB::table('int_customer_value_propositions')->where('id', $prop->id)->first();
    }

    public function registerProofPoint(string $proofCode, string $lineCode, string $quantifiedClaim): object
    {
        $id = DB::table('int_value_selling_proof_points')->insertGetId([
            'proof_code' => strtoupper($proofCode),
            'line_code' => strtoupper($lineCode),
            'quantified_claim' => $quantifiedClaim,
            'evidence_verified' => false,
            'status' => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('int_value_selling_proof_points')->where('id', $id)->first();
    }

    /**
     * 463.3, 463.4, 463.6 Approve sales proof point strictly requiring verified empirical evidence
     */
    public function approveProofPoint(string $proofCode, bool $evidenceVerified): object
    {
        $pp = DB::table('int_value_selling_proof_points')->where('proof_code', strtoupper($proofCode))->first();
        if (! $pp) {
            throw new InvalidArgumentException("Proof point '{$proofCode}' not found.");
        }

        // 463.6 Risk: Unsubstantiated value claim is blocked from sales enablement
        if (! $evidenceVerified) {
            throw new InvalidArgumentException("Approval blocked: Sales value claim requires empirical evidence verification before commercial release (463.3, 463.6).");
        }

        DB::table('int_value_selling_proof_points')->where('id', $pp->id)->update([
            'evidence_verified' => true,
            'status' => 'verified_approved',
            'updated_at' => now(),
        ]);

        return (object) DB::table('int_value_selling_proof_points')->where('id', $pp->id)->first();
    }

    public function audit(): array
    {
        // Discrepancy 1: Lines with gap > 10% without prioritized improvement backlog
        $unaddressedGaps = DB::table('int_customer_value_propositions')
            ->where('value_gap_percentage', '>', 10.00)
            ->where('has_prioritized_improvement_backlog', false)
            ->count();

        // Discrepancy 2: Approved proof points with unverified evidence
        $unverifiedClaims = DB::table('int_value_selling_proof_points')
            ->where('status', 'verified_approved')
            ->where('evidence_verified', false)
            ->count();

        $total = $unaddressedGaps + $unverifiedClaims;

        return [
            'status' => $total === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_propositions' => DB::table('int_customer_value_propositions')->count(),
            'total_proof_points' => DB::table('int_value_selling_proof_points')->count(),
            'discrepancy_count' => $total,
        ];
    }
}
