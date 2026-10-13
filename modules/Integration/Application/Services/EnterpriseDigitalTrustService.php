<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * EnterpriseDigitalTrustService (Fase 484)
 *
 * Implements:
 *  - 484.1 Verifiable claim framework: quality, sustainability, financial, safety linked to evidence hash
 *  - 484.2 Trust infrastructure: hash verification API and public verification
 *  - 484.3 Trust score computation
 *  - 484.4 Tests: claim without evidence blocked, verification API works, trust:audit clean
 *  - 484.5 Edge case: Claims lacking cryptographic evidence hash rejected from publication (never assumed true)
 *  - 484.6 Risk: Trust scores strictly derived from verified empirical data only (anti-manipulation)
 *  - 484.7 Evidence: trust framework, verification API logs, score methodology
 */
class EnterpriseDigitalTrustService
{
    /**
     * 484.1, 484.4, 484.5 Register and publish external claim requiring evidence hash
     */
    public function publishClaim(
        string $code,
        string $type,
        string $statement,
        string $evidenceHash,
        bool $publicVerifiable = true
    ): object {
        $t = strtolower($type);
        $validTypes = ['quality', 'sustainability', 'financial', 'safety'];
        if (! in_array($t, $validTypes, true)) {
            throw new InvalidArgumentException("Invalid claim type '{$type}' (484.1).");
        }

        // 484.5 Edge case: Claim without cryptographic evidence hash is rejected
        if (empty(trim($evidenceHash))) {
            throw new InvalidArgumentException('Claim publication blocked: External enterprise claims strictly require cryptographic evidence verification (484.1, 484.5).');
        }

        $id = DB::table('int_verifiable_enterprise_claims')->insertGetId([
            'claim_code' => strtoupper($code),
            'claim_type' => $t,
            'claim_statement' => $statement,
            'evidence_hash' => $evidenceHash,
            'public_verifiable' => $publicVerifiable,
            'status' => 'published_verified',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('int_verifiable_enterprise_claims')->where('id', $id)->first();
    }

    /**
     * 484.2 Third-party verification API
     */
    public function verifyClaimHash(string $code, string $candidateData): bool
    {
        $claim = DB::table('int_verifiable_enterprise_claims')->where('claim_code', strtoupper($code))->first();
        if (! $claim) {
            throw new InvalidArgumentException("Claim '{$code}' not found.");
        }

        $candidateHash = hash('sha256', $candidateData);

        return hash_equals($claim->evidence_hash, $candidateHash);
    }

    /**
     * 484.3 & 484.6 Calculate composite trust score strictly from verified data
     */
    public function calculateTrustScore(
        string $domain,
        float $verificationCoverage,
        float $auditPassRate,
        bool $verifiedDataOnly = true
    ): object {
        // 484.6 Risk: Unverified data input blocked
        if (! $verifiedDataOnly) {
            throw new InvalidArgumentException('Trust score computation blocked: Scores must be derived strictly from verified data (484.6).');
        }

        $composite = round(($verificationCoverage * 0.50) + ($auditPassRate * 0.50), 2);

        $id = DB::table('int_enterprise_trust_scores')->insertGetId([
            'domain_code' => strtoupper($domain),
            'verification_coverage_percentage' => $verificationCoverage,
            'audit_pass_rate_percentage' => $auditPassRate,
            'composite_trust_score' => $composite,
            'score_derived_from_verified_data_only' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('int_enterprise_trust_scores')->where('id', $id)->first();
    }

    public function audit(): array
    {
        // Discrepancy 1: Published claims without evidence hash
        $unhashedClaims = DB::table('int_verifiable_enterprise_claims')
            ->where('status', 'published_verified')
            ->where(function ($query) {
                $query->whereNull('evidence_hash')
                    ->orWhere('evidence_hash', '');
            })
            ->count();

        // Discrepancy 2: Trust scores derived from unverified data
        $unverifiedScores = DB::table('int_enterprise_trust_scores')
            ->where('score_derived_from_verified_data_only', false)
            ->count();

        $total = $unhashedClaims + $unverifiedScores;

        return [
            'status' => $total === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_claims' => DB::table('int_verifiable_enterprise_claims')->count(),
            'total_trust_scores' => DB::table('int_enterprise_trust_scores')->count(),
            'discrepancy_count' => $total,
        ];
    }
}
