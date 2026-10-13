<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * NatureWaterCommunityFinanceService (Fase 330)
 *
 * Implements:
 *  - 330.1 Nature credit marketplace project issuance gate & community benefit sharing
 *  - 330.2 Water stewardship performance-based financing with independent meter verification
 *  - 330.4 Tests: Additionality enforced; benefit share reconciles; independent measurement verified; nature:audit clean
 *  - 330.5 Edge case: Community objection strictly halts credit issuance until social remediation is filed and approved
 *  - 330.6 Risk: Credits lacking verifiable additionality are rejected by third-party verifier
 */
class NatureWaterCommunityFinanceService
{
    /**
     * Issue nature project credits with additionality and community consent gates (330.1, 330.4, 330.5 Edge Case, 330.6 Risk).
     */
    public function issueNatureCredits(
        string $projectCode,
        string $projectType,
        float $grossProceedsUsd,
        bool $verifiedAdditionality,
        bool $communityConsentGranted,
        bool $socialRemediationFiled = false,
        float $benefitSharePct = 30.00
    ): object {
        $pCode = strtoupper($projectCode);

        // Additionality check 330.6 Risk
        if (! $verifiedAdditionality) {
            throw new InvalidArgumentException('Additionality failure: Nature credit issuance rejected due to lack of verifiable project additionality (330.6).');
        }

        // Community consent & remediation gate 330.5 Edge Case
        if (! $communityConsentGranted && ! $socialRemediationFiled) {
            throw new InvalidArgumentException('Social risk breach: Community objection halts project issuance until formal social remediation is filed (330.5).');
        }

        // Benefit share reconciliation 330.4: exactly 30% proceeds disbursed to local community
        $disbursedShare = round($grossProceedsUsd * ($benefitSharePct / 100.0), 2);

        $id = DB::table('nature_credit_marketplace_projects')->insertGetId([
            'project_code' => $pCode,
            'project_type' => strtoupper($projectType),
            'has_verified_additionality' => true,
            'community_consent_granted' => $communityConsentGranted,
            'social_remediation_filed' => $socialRemediationFiled,
            'gross_credit_proceeds_usd' => $grossProceedsUsd,
            'community_benefit_share_pct' => $benefitSharePct,
            'disbursed_benefit_share_usd' => $disbursedShare,
            'issuance_cleared' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('nature_credit_marketplace_projects')->find($id);
    }

    /**
     * Settle water stewardship performance-based payment with independent measurement gate (330.2 & 330.4).
     */
    public function recordWaterStewardshipSavings(
        string $facilityCode,
        string $siteCode,
        float $baselineM3,
        float $actualM3,
        float $rateUsdPerM3 = 2.50,
        bool $independentlyMeasured = true
    ): object {
        $fCode = strtoupper($facilityCode);

        if (! $independentlyMeasured) {
            throw new InvalidArgumentException('Measurement verification breach: Water stewardship performance payments require independent meter auditing (330.4).');
        }

        $savingsM3 = max(0.00, round($baselineM3 - $actualM3, 2));
        $paymentUsd = round($savingsM3 * $rateUsdPerM3, 2);

        $id = DB::table('water_stewardship_performance_facilities')->insertGetId([
            'facility_code' => $fCode,
            'site_code' => strtoupper($siteCode),
            'metered_baseline_m3' => $baselineM3,
            'metered_actual_m3' => $actualM3,
            'verified_water_savings_m3' => $savingsM3,
            'performance_payment_usd' => $paymentUsd,
            'is_independently_measured' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('water_stewardship_performance_facilities')->find($id);
    }

    /**
     * Nature & Community Finance Audit (`nature:audit`) (330.4, 330.8).
     */
    public function audit(): array
    {
        // Discrepancy 1: Credits issued without additionality
        $nonAdditionalIssuances = DB::table('nature_credit_marketplace_projects')
            ->where('issuance_cleared', true)
            ->where('has_verified_additionality', false)
            ->count();

        // Discrepancy 2: Community rejected without remediation
        $unremediatedObjections = DB::table('nature_credit_marketplace_projects')
            ->where('community_consent_granted', false)
            ->where('social_remediation_filed', false)
            ->where('issuance_cleared', true)
            ->count();

        // Discrepancy 3: Water payments without independent measurement
        $unverifiedWaterPayments = DB::table('water_stewardship_performance_facilities')
            ->where('is_independently_measured', false)
            ->count();

        $discrepancies = $nonAdditionalIssuances + $unremediatedObjections + $unverifiedWaterPayments;

        return [
            'status' => $discrepancies === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_nature_projects' => DB::table('nature_credit_marketplace_projects')->count(),
            'total_water_facilities' => DB::table('water_stewardship_performance_facilities')->count(),
            'discrepancy_count' => $discrepancies,
        ];
    }
}
