<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * LegalOperationsService (Fase 176 — Lini 25)
 *
 * Implements:
 *  - 176.1 Matter management with privilege classification
 *  - 176.2 Dispute lifecycle & single idempotent ledger settlement posting
 *  - 176.4 Evidence bundle generator with SHA-256 checksum and role privilege gating
 */
class LegalOperationsService
{
    /**
     * Create legal matter.
     */
    public function createMatter(string $caseTitle, string $privilegeClass, string $counselRole = 'LEGAL_COUNSEL'): object
    {
        $code = 'MAT-LEG-'.strtoupper(Str::random(8));

        $id = DB::table('leg_matters')->insertGetId([
            'matter_code' => $code,
            'case_title' => $caseTitle,
            'privilege_classification' => strtoupper($privilegeClass),
            'assigned_counsel_role' => strtoupper($counselRole),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('leg_matters')->find($id);
    }

    /**
     * Store evidence bundle with SHA-256 checksum.
     */
    public function storeEvidence(string $matterCode, string $docTitle, string $fileContent): object
    {
        $code = 'EVD-LEG-'.strtoupper(Str::random(8));
        $checksum = hash('sha256', $fileContent);

        $id = DB::table('leg_evidence_bundles')->insertGetId([
            'bundle_code' => $code,
            'matter_code' => $matterCode,
            'document_title' => $docTitle,
            'file_checksum_sha256' => $checksum,
            'is_privileged' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('leg_evidence_bundles')->find($id);
    }

    /**
     * Access evidence bundle. Rejects non-counsel users for privileged documents.
     */
    public function accessEvidence(string $bundleCode, string $userRole): object
    {
        $bundle = DB::table('leg_evidence_bundles')->where('bundle_code', $bundleCode)->first();
        if (! $bundle) {
            throw new \InvalidArgumentException("Evidence {$bundleCode} not found.");
        }

        if ((bool) $bundle->is_privileged && strtoupper($userRole) !== 'LEGAL_COUNSEL') {
            throw new \RuntimeException('Access denied: Privileged legal evidence is strictly restricted to LEGAL_COUNSEL role.');
        }

        return (object) $bundle;
    }

    /**
     * Settle dispute and post to financial ledger once (idempotent).
     */
    public function executeSettlement(string $matterCode, float $amountIdr): object
    {
        $existing = DB::table('leg_dispute_settlements')->where('matter_code', $matterCode)->first();
        if ($existing && (bool) $existing->is_posted_to_ledger) {
            return (object) $existing; // Idempotent return without duplicate posting
        }

        $code = 'SET-LEG-'.strtoupper(Str::random(8));

        $id = DB::table('leg_dispute_settlements')->insertGetId([
            'settlement_code' => $code,
            'matter_code' => $matterCode,
            'settlement_amount_idr' => $amountIdr,
            'is_posted_to_ledger' => true,
            'status' => 'SETTLED',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('leg_dispute_settlements')->find($id);
    }

    /**
     * Quality audit gate (`legal:audit`).
     */
    public function audit(): array
    {
        $duplicatePostings = DB::table('leg_dispute_settlements')
            ->select('matter_code', DB::raw('count(*) as count'))
            ->groupBy('matter_code')
            ->having('count', '>', 1)
            ->count();

        return [
            'status' => $duplicatePostings === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_matters' => DB::table('leg_matters')->count(),
            'total_evidence_bundles' => DB::table('leg_evidence_bundles')->count(),
            'total_settlements' => DB::table('leg_dispute_settlements')->count(),
            'discrepancy_count' => $duplicatePostings,
        ];
    }
}
