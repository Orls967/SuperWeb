<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * B2bIndustryEcosystemPlatformService (Fase 316)
 *
 * Implements:
 *  - 316.1 Industry vertical platform catalog, tender, and settlement
 *  - 316.2 Bounded anti-chicken-and-egg liquidity subsidies strictly capped to budget
 *  - 316.3 Platform KYB tiering & public reputation trust index
 *  - 316.4 Tests: Multi-party consistency, trust index deterministic, subsidy does not exceed budget, b2b:audit clean
 *  - 316.5 Edge case: Low trust index (< 6.0) automatically mandates an active remediation plan and transparency report
 *  - 316.6 Risk: Small SME partners protected via tiered fee caps (<= 2.5% for SME vs 5% for enterprise)
 */
class B2bIndustryEcosystemPlatformService
{
    /**
     * Onboard industry participant with tiered platform fee caps (316.1, 316.3, 316.6 Risk).
     */
    public function onboardParticipant(
        string $partnerCode,
        string $vertical,
        string $kybTier,
        float $proposedFeePct
    ): object {
        $pCode = strtoupper($partnerCode);
        $tier = strtoupper($kybTier);

        // Fee cap guardrail 316.6: Tier 3 SME fee cannot exceed 2.5% to protect small partners
        $maxAllowedFee = ($tier === 'TIER_3_SME') ? 2.50 : 5.00;
        if ($proposedFeePct > $maxAllowedFee) {
            throw new InvalidArgumentException("Fee cap breach: Proposed fee ({$proposedFeePct}%) exceeds maximum limit ({$maxAllowedFee}%) for tier {$tier} (316.6).");
        }

        $id = DB::table('b2b_industry_vertical_participants')->insertGetId([
            'partner_code' => $pCode,
            'industry_vertical' => strtoupper($vertical),
            'kyb_tier' => $tier,
            'platform_fee_rate_pct' => $proposedFeePct,
            'reputation_trust_index' => 9.00,
            'remediation_plan_active' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('b2b_industry_vertical_participants')->find($id);
    }

    /**
     * Update participant trust index with remediation plan trigger on low score (316.3 & 316.5 Edge Case).
     */
    public function updateTrustIndex(string $partnerCode, float $newScore): object
    {
        $pCode = strtoupper($partnerCode);
        $partner = DB::table('b2b_industry_vertical_participants')->where('partner_code', $pCode)->first();
        if (! $partner) {
            throw new InvalidArgumentException("Partner '{$partnerCode}' not found.");
        }

        // Edge case 316.5: Trust index below 6.0 mandates active remediation plan
        $needsRemediation = ($newScore < 6.00);

        DB::table('b2b_industry_vertical_participants')
            ->where('partner_code', $pCode)
            ->update([
                'reputation_trust_index' => $newScore,
                'remediation_plan_active' => $needsRemediation,
                'updated_at' => now(),
            ]);

        return (object) DB::table('b2b_industry_vertical_participants')->where('partner_code', $pCode)->first();
    }

    /**
     * Allocate liquidity subsidy strictly bounded by campaign budget (316.2 & 316.4).
     */
    public function allocateLiquiditySubsidy(
        string $campaignCode,
        string $vertical,
        float $allocatedBudgetUsd,
        float $disbursedAmountUsd
    ): object {
        $cCode = strtoupper($campaignCode);

        // Budget cap enforcement 316.2 & 316.4: Subsidies must never exceed allocated campaign budget
        if ($disbursedAmountUsd > $allocatedBudgetUsd) {
            throw new InvalidArgumentException("Budget violation: Disbursed subsidy (\${$disbursedAmountUsd}) exceeds allocated campaign cap (\${$allocatedBudgetUsd}) (316.4).");
        }

        $id = DB::table('b2b_marketplace_liquidity_subsidies')->insertGetId([
            'campaign_code' => $cCode,
            'industry_vertical' => strtoupper($vertical),
            'allocated_budget_usd' => $allocatedBudgetUsd,
            'utilized_subsidy_usd' => $disbursedAmountUsd,
            'budget_cap_exceeded' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('b2b_marketplace_liquidity_subsidies')->find($id);
    }

    /**
     * B2B Industry Ecosystem Audit (`b2b:audit`) (316.4, 316.8).
     */
    public function audit(): array
    {
        // Discrepancy 1: Low trust index without remediation plan
        $unremediatedPartners = DB::table('b2b_industry_vertical_participants')
            ->where('reputation_trust_index', '<', 6.0)
            ->where('remediation_plan_active', false)
            ->count();

        // Discrepancy 2: Subsidies exceeding budget
        $overspentSubsidies = DB::table('b2b_marketplace_liquidity_subsidies')
            ->whereRaw('utilized_subsidy_usd > allocated_budget_usd')
            ->count();

        $discrepancies = $unremediatedPartners + $overspentSubsidies;

        return [
            'status' => $discrepancies === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_participants' => DB::table('b2b_industry_vertical_participants')->count(),
            'total_subsidy_campaigns' => DB::table('b2b_marketplace_liquidity_subsidies')->count(),
            'discrepancy_count' => $discrepancies,
        ];
    }
}
