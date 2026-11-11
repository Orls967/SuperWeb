<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Access\Audit;

use DateTimeImmutable;

/**
 * Tenant Isolation & Governance Compliance Scorecard (W8-D58, W8-D60).
 *
 * Evaluates tenant security posture across five pillars:
 * 1. Data Isolation Completeness (100% of models enforce tenant scope)
 * 2. RBAC/ABAC Policy Coverage (all privileged routes guarded)
 * 3. Access Recertification Cadence (no overdue reviews)
 * 4. Vendor Exposure Limit Adherence (no clusters exceeding Rp 500M)
 * 5. Immutable Ledger Transition Continuity (zero broken hash chains)
 */
final class TenantComplianceScorecard
{
    /**
     * Compute comprehensive governance score (0 to 100) and maturity tier.
     *
     * @param array{
     *     isolation_score: float,
     *     policy_coverage_score: float,
     *     recertification_score: float,
     *     vendor_limit_score: float,
     *     audit_ledger_score: float
     * } $pillarScores
     * @return array{
     *     tenant_id: string,
     *     overall_compliance_score: float,
     *     maturity_tier: string,
     *     pillar_breakdown: array<string, float>,
     *     findings: list<string>,
     *     certified_at: string
     * }
     */
    public function computeScorecard(string $tenantId, array $pillarScores): array
    {
        $weights = [
            'isolation_score' => 0.35,          // 35% weight: absolute multi-tenancy isolation
            'audit_ledger_score' => 0.25,       // 25% weight: immutable audit reconciliation
            'policy_coverage_score' => 0.15,    // 15% weight: ABAC/RBAC coverage
            'recertification_score' => 0.15,    // 15% weight: periodic recertification
            'vendor_limit_score' => 0.10,       // 10% weight: Rp 500M vendor limits
        ];

        $overall = 0.0;
        $findings = [];

        foreach ($weights as $pillar => $weight) {
            $score = $pillarScores[$pillar] ?? 0.0;
            $overall += ($score * $weight);

            if ($score < 80.0) {
                $findings[] = sprintf('Pillar [%s] scored below standard: %.1f%%', $pillar, $score);
            }
        }

        $overall = round($overall, 2);

        $maturityTier = match (true) {
            $overall >= 95.0 => 'TIER_4_MILITARY_GRADE_ISOLATION',
            $overall >= 85.0 => 'TIER_3_ENTERPRISE_COMPLIANT',
            $overall >= 70.0 => 'TIER_2_MANAGED_MULTITENANCY',
            default => 'TIER_1_BASIC_ISOLATION_AT_RISK',
        };

        return [
            'tenant_id' => $tenantId,
            'overall_compliance_score' => $overall,
            'maturity_tier' => $maturityTier,
            'pillar_breakdown' => $pillarScores,
            'findings' => $findings,
            'certified_at' => (new DateTimeImmutable)->format('c'),
        ];
    }
}
