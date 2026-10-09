<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * GovInternalAuditAssuranceService (Fase 293)
 *
 * Implements:
 *  - 293.1 Risk-based internal audit engagements
 *  - 293.2 Engagement fieldwork and finding lifecycle requiring management responses and closure evidence
 *  - 293.4 External auditor read-only portal with immutable access logs
 *  - 293.5 Tests: Deterministic reproducible sample selection; auditor role strictly read-only; issue closure requires verified evidence
 *  - 293.6 Edge case: Out-of-scope issues logged formally as observations rather than ignored
 *  - 293.7 Mandatory management response before finding can transition to closed
 *  - 293.8 Sample selection produced with deterministic seed for replayability
 */
class GovInternalAuditAssuranceService
{
    /**
     * Start audit engagement with deterministic sampling seed (293.1, 293.5, 293.8).
     */
    public function startEngagement(
        string $engagementCode,
        string $auditableEntity,
        string $scope,
        int $sampleSeed = 42
    ): object {
        $eCode = strtoupper($engagementCode);

        $id = DB::table('gov_internal_audit_engagements')->insertGetId([
            'engagement_code' => $eCode,
            'auditable_entity' => strtoupper($auditableEntity),
            'scope_description' => $scope,
            'sample_seed' => $sampleSeed,
            'status' => 'FIELDWORK',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('gov_internal_audit_engagements')->find($id);
    }

    /**
     * Select audit sample deterministically using seed for exact replayability (293.5 & 293.8).
     */
    public function generateDeterministicSample(string $engagementCode, array $populationIds, int $sampleSize): array
    {
        $eCode = strtoupper($engagementCode);
        $eng = DB::table('gov_internal_audit_engagements')->where('engagement_code', $eCode)->first();
        $seed = $eng ? (int) $eng->sample_seed : 42;

        // Seeded deterministic sampling
        mt_srand($seed);
        $pop = $populationIds;
        shuffle($pop);

        return array_slice($pop, 0, min($sampleSize, count($pop)));
    }

    /**
     * Log audit finding, including out-of-scope observations (293.2 & 293.6 Edge Case).
     */
    public function logFinding(
        string $findingCode,
        string $engagementCode,
        string $title,
        string $severity = 'HIGH',
        bool $isOutOfScope = false
    ): object {
        $fCode = strtoupper($findingCode);
        $eCode = strtoupper($engagementCode);

        // Edge case 293.6: Out of scope items captured formally as observations
        $finalSeverity = $isOutOfScope ? 'OUT_OF_SCOPE_OBSERVATION' : strtoupper($severity);

        $id = DB::table('gov_internal_audit_findings')->insertGetId([
            'finding_code' => $fCode,
            'engagement_code' => $eCode,
            'finding_title' => $title,
            'severity' => $finalSeverity,
            'management_response' => null,
            'closure_evidence_ref' => null,
            'status' => 'OPEN',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('gov_internal_audit_findings')->find($id);
    }

    /**
     * Record management response on finding (293.2 & 293.7).
     */
    public function submitManagementResponse(string $findingCode, string $response): object
    {
        $fCode = strtoupper($findingCode);
        DB::table('gov_internal_audit_findings')
            ->where('finding_code', $fCode)
            ->update([
                'management_response' => $response,
                'status' => 'MANAGEMENT_RESPONDED',
                'updated_at' => now(),
            ]);

        return (object) DB::table('gov_internal_audit_findings')->where('finding_code', $fCode)->first();
    }

    /**
     * Close finding enforcing both management response & verified evidence (293.5 & 293.7).
     */
    public function closeFindingWithEvidence(string $findingCode, string $evidenceDocRef): object
    {
        $fCode = strtoupper($findingCode);
        $finding = DB::table('gov_internal_audit_findings')->where('finding_code', $fCode)->first();
        if (! $finding) {
            throw new InvalidArgumentException("Finding '{$findingCode}' not found.");
        }

        // Constraint 293.7: Cannot close finding without management response
        if (empty($finding->management_response)) {
            throw new InvalidArgumentException("Finding closure blocked: Finding '{$findingCode}' requires formal management response before closure (293.7).");
        }

        // Constraint 293.5: Cannot close finding without verified evidence reference
        if (empty($evidenceDocRef)) {
            throw new InvalidArgumentException("Finding closure blocked: Verified remediation evidence document reference is mandatory (293.5).");
        }

        DB::table('gov_internal_audit_findings')
            ->where('finding_code', $fCode)
            ->update([
                'closure_evidence_ref' => $evidenceDocRef,
                'status' => 'CLOSED_VERIFIED',
                'updated_at' => now(),
            ]);

        return (object) DB::table('gov_internal_audit_findings')->where('finding_code', $fCode)->first();
    }

    /**
     * Log external auditor portal read-only access (293.4 & 293.5).
     */
    public function logExternalAuditorAccess(string $auditorId, string $packageRef, bool $isReadOnly = true): object
    {
        // Safety constraint 293.4 & 293.5: External auditor portal must be strictly read-only
        if (! $isReadOnly) {
            throw new InvalidArgumentException("External auditor access violation: External auditor role is strictly read-only (293.5).");
        }

        $id = DB::table('gov_external_auditor_access_logs')->insertGetId([
            'auditor_identity_id' => strtoupper($auditorId),
            'scoped_package_ref' => $packageRef,
            'is_read_only' => true,
            'accessed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('gov_external_auditor_access_logs')->find($id);
    }

    /**
     * Internal Audit Platform Audit (`audit:audit`) (293.5, 293.9).
     */
    public function audit(): array
    {
        // Discrepancy 1: Closed findings missing management response or evidence ref
        $invalidClosedFindings = DB::table('gov_internal_audit_findings')
            ->where('status', 'CLOSED_VERIFIED')
            ->where(function ($query) {
                $query->whereNull('management_response')
                    ->orWhereNull('closure_evidence_ref');
            })
            ->count();

        // Discrepancy 2: Non read-only access logs
        $mutatingAuditorAccess = DB::table('gov_external_auditor_access_logs')
            ->where('is_read_only', false)
            ->count();

        $discrepancies = $invalidClosedFindings + $mutatingAuditorAccess;

        return [
            'status' => $discrepancies === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_engagements' => DB::table('gov_internal_audit_engagements')->count(),
            'total_findings' => DB::table('gov_internal_audit_findings')->count(),
            'total_access_logs' => DB::table('gov_external_auditor_access_logs')->count(),
            'discrepancy_count' => $discrepancies,
        ];
    }
}
