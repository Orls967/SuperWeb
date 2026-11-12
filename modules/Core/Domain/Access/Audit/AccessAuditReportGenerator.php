<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Access\Audit;

use DateTimeImmutable;

/**
 * Access Governance Audit Report Generator (W8-D60).
 *
 * Formats two-source reconciliation results into an audit report with cryptographic digest,
 * category grouping (Ghost Grants, Status Drift, Broken Continuity), and actionable remediation playbooks.
 */
final class AccessAuditReportGenerator
{
    /**
     * Generate structured audit report from reconciliation results.
     *
     * @param array{
     *     total_grants: int,
     *     total_transitions: int,
     *     reconciled_grants: int,
     *     divergent_grants: int,
     *     ghost_grants: int,
     *     orphaned_transitions: int,
     *     discrepancies: list<array<string, mixed>>
     * } $reconciliationResult
     * @return array<string, mixed>
     */
    public function generateReport(array $reconciliationResult, string $auditorName = 'CoreAccessAuditor'): array
    {
        $now = new DateTimeImmutable;
        $discrepancies = $reconciliationResult['discrepancies'];
        $hasViolations = count($discrepancies) > 0;

        $ghosts = array_values(array_filter($discrepancies, fn ($d) => ($d['type'] ?? '') === 'GHOST_GRANT'));
        $drifts = array_values(array_filter($discrepancies, fn ($d) => ($d['type'] ?? '') === 'STATUS_DIVERGENCE'));
        $brokenChains = array_values(array_filter($discrepancies, fn ($d) => ($d['type'] ?? '') === 'CHAIN_CONTINUITY_BROKEN'));
        $orphaned = array_values(array_filter($discrepancies, fn ($d) => ($d['type'] ?? '') === 'ORPHANED_TRANSITION'));

        $integrityPayload = json_encode([
            'timestamp' => $now->format('c'),
            'reconciled' => $reconciliationResult['reconciled_grants'],
            'divergent' => $reconciliationResult['divergent_grants'],
            'ghost' => $reconciliationResult['ghost_grants'],
            'orphaned' => $reconciliationResult['orphaned_transitions'],
        ], JSON_THROW_ON_ERROR);

        $integrityHash = hash('sha256', (string) $integrityPayload);

        return [
            'report_header' => [
                'audit_run_at' => $now->format('Y-m-d H:i:s T'),
                'auditor' => $auditorName,
                'integrity_hash_sha256' => $integrityHash,
                'compliance_status' => $hasViolations ? 'DISCREPANCIES_FOUND' : 'FULLY_RECONCILED',
            ],
            'executive_summary' => [
                'total_grants_evaluated' => $reconciliationResult['total_grants'],
                'total_transitions_evaluated' => $reconciliationResult['total_transitions'],
                'reconciled_grants_count' => $reconciliationResult['reconciled_grants'],
                'divergent_grants_count' => $reconciliationResult['divergent_grants'],
                'ghost_grants_count' => $reconciliationResult['ghost_grants'],
                'orphaned_transitions_count' => $reconciliationResult['orphaned_transitions'],
                'reconciliation_ratio' => $reconciliationResult['total_grants'] > 0
                    ? round(($reconciliationResult['reconciled_grants'] / $reconciliationResult['total_grants']) * 100, 2)
                    : 100.0,
            ],
            'categorized_findings' => [
                'ghost_grants' => [
                    'count' => count($ghosts),
                    'severity' => 'CRITICAL',
                    'remediation' => 'Immediately freeze and investigate grants without immutable ledger transition records.',
                    'items' => $ghosts,
                ],
                'status_divergences' => [
                    'count' => count($drifts),
                    'severity' => 'HIGH',
                    'remediation' => 'Synchronize grant state with latest ledger transition; invalidate memory cache tokens.',
                    'items' => $drifts,
                ],
                'broken_transition_chains' => [
                    'count' => count($brokenChains),
                    'severity' => 'HIGH',
                    'remediation' => 'Audit transition history sequence for manual tampering or concurrent race conditions.',
                    'items' => $brokenChains,
                ],
                'orphaned_transitions' => [
                    'count' => count($orphaned),
                    'severity' => 'MEDIUM',
                    'remediation' => 'Investigate deleted grant references; foreign key constraint check required.',
                    'items' => $orphaned,
                ],
            ],
        ];
    }
}
