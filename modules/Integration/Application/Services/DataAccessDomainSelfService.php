<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * DataAccessDomainSelfService (Fase 344)
 *
 * Implements:
 *  - 344.1 Time-bound access grants with automatic expiry enforcement
 *  - 344.2 Domain product templates with CI schema & quality checks
 *  - 344.3 Data literacy certification required for sensitive domain tier access
 *  - 344.4 Tests: Grant expiry enforced; CI gate passes; untrained user blocked for sensitive tier; data:audit clean
 *  - 344.5 Edge case: Missing domain templates established by governance council (domains cannot arbitrarily invent templates)
 *  - 344.6 Risk: Over-broad access mitigated by time-bound least privilege
 */
class DataAccessDomainSelfService
{
    /**
     * Grant time-bound data access with literacy training gate on sensitive tiers (344.1, 344.3, 344.4).
     */
    public function issueTimeBoundAccessGrant(
        string $grantCode,
        string $userId,
        string $domainName,
        string $accessTier,
        bool $userCertified,
        Carbon $expiresAt
    ): object {
        $gCode = strtoupper($grantCode);
        $tier = strtoupper($accessTier);

        // Core gate 344.3 & 344.4: Untrained user blocked from sensitive domain tier
        if ($tier === 'RESTRICTED_SENSITIVE' && ! $userCertified) {
            throw new InvalidArgumentException('Data literacy gate rejection: User requires certified training to access sensitive domain data (344.4).');
        }

        $id = DB::table('domain_data_access_grants')->insertGetId([
            'grant_code' => $gCode,
            'user_id' => strtoupper($userId),
            'domain_name' => strtoupper($domainName),
            'access_tier' => $tier,
            'user_certified_literacy' => $userCertified,
            'grant_expires_at' => $expiresAt,
            'access_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('domain_data_access_grants')->find($id);
    }

    /**
     * Verify active access status with automatic time-bound expiration enforcement (344.1 & 344.4).
     */
    public function isAccessActive(string $grantCode): bool
    {
        $grant = DB::table('domain_data_access_grants')->where('grant_code', strtoupper($grantCode))->first();
        if (! $grant || ! $grant->access_active) {
            return false;
        }

        if (Carbon::parse($grant->grant_expires_at)->isPast()) {
            DB::table('domain_data_access_grants')
                ->where('grant_code', strtoupper($grantCode))
                ->update(['access_active' => false, 'updated_at' => now()]);

            return false;
        }

        return true;
    }

    /**
     * Publish domain data product template with governance council and CI quality checks (344.2, 344.4, 344.5 Edge Case).
     */
    public function publishDomainTemplate(
        string $templateCode,
        string $domainName,
        bool $fromGovernanceCouncil,
        bool $ciCheckPassed
    ): object {
        $tCode = strtoupper($templateCode);

        // Edge case 344.5: Domain templates must be formally established by governance council
        if (! $fromGovernanceCouncil) {
            throw new InvalidArgumentException('Template governance breach: Domain product templates must be established by Governance Council (344.5).');
        }

        // CI gate check 344.4
        $published = ($fromGovernanceCouncil && $ciCheckPassed);

        $id = DB::table('domain_data_product_templates')->insertGetId([
            'template_code' => $tCode,
            'domain_name' => strtoupper($domainName),
            'established_by_governance_council' => $fromGovernanceCouncil,
            'ci_schema_quality_check_passed' => $ciCheckPassed,
            'published_to_catalog' => $published,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('domain_data_product_templates')->find($id);
    }

    /**
     * Data Access Platform Audit (`data:audit`) (344.4, 344.8).
     */
    public function audit(): array
    {
        // Discrepancy 1: Sensitive tier active for uncertified user
        $uncertifiedSensitiveGrants = DB::table('domain_data_access_grants')
            ->where('access_tier', 'RESTRICTED_SENSITIVE')
            ->where('user_certified_literacy', false)
            ->where('access_active', true)
            ->count();

        // Discrepancy 2: Published templates not established by governance council
        $unauthorizedTemplates = DB::table('domain_data_product_templates')
            ->where('published_to_catalog', true)
            ->where('established_by_governance_council', false)
            ->count();

        $discrepancies = $uncertifiedSensitiveGrants + $unauthorizedTemplates;

        return [
            'status' => $discrepancies === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_grants' => DB::table('domain_data_access_grants')->count(),
            'total_templates' => DB::table('domain_data_product_templates')->count(),
            'discrepancy_count' => $discrepancies,
        ];
    }
}
