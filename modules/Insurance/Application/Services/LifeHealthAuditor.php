<?php

declare(strict_types=1);

namespace Modules\Insurance\Application\Services;

use Modules\Insurance\Domain\Models\GuaranteeLetter;
use Modules\Insurance\Domain\Models\HealthClaim;
use Modules\Insurance\Domain\Models\HealthPolicy;
use Modules\Insurance\Domain\Models\LifePolicy;
use Modules\Insurance\Domain\Models\UnitLinkFund;
use Modules\Insurance\Domain\Models\UnitLinkHolding;
use Modules\Insurance\Domain\Models\WellnessReward;
use RuntimeException;

final class LifeHealthAuditor
{
    /**
     * Menjalankan audit dua-sumber independen untuk asuransi jiwa, kesehatan & portofolio unit link.
     *
     * @return array{
     *     status: string,
     *     total_life_policies: int,
     *     total_health_claims: int,
     *     total_funds: int,
     *     total_guarantee_letters: int,
     *     total_wellness_rewards: int,
     *     beneficiary_allocation_mismatches: int,
     *     claim_amount_mismatches: int,
     *     unit_link_aum_mismatches: int,
     *     guarantee_letter_limit_violations: int,
     *     wellness_reward_inconsistencies: int
     * }
     */
    public function audit(): array
    {
        $lifePolicies = LifePolicy::with('beneficiaries')->get();
        $beneficiaryMismatches = 0;

        foreach ($lifePolicies as $policy) {
            $sumShare = (int) $policy->beneficiaries->sum('share_percentage_bps');
            if ($sumShare !== 10000) {
                $beneficiaryMismatches++;
            }
        }

        $healthClaims = HealthClaim::all();
        $claimMismatches = 0;
        foreach ($healthClaims as $claim) {
            $sumParts = (int) ($claim->approved_amount_idr + $claim->patient_share_idr);
            if ($sumParts !== (int) $claim->incurred_amount_idr) {
                $claimMismatches++;
            }
        }

        $funds = UnitLinkFund::all();
        $unitLinkMismatches = 0;
        foreach ($funds as $fund) {
            $sumUnitsHeld = (int) UnitLinkHolding::where('fund_id', $fund->id)->sum('units_held');
            if ($sumUnitsHeld !== (int) $fund->total_units) {
                $unitLinkMismatches++;
            }
        }

        $guaranteeLetters = GuaranteeLetter::all();
        $glViolations = 0;
        foreach ($guaranteeLetters as $gl) {
            $policy = HealthPolicy::find($gl->policy_id);
            if ($policy !== null && (int) $gl->approved_limit_idr > (int) $policy->annual_limit_idr) {
                $glViolations++;
            }
        }

        $wellnessRewards = WellnessReward::all();
        $rewardInconsistencies = 0;
        foreach ($wellnessRewards as $reward) {
            if ((int) $reward->points_cost < 0) {
                $rewardInconsistencies++;
            }
        }

        if (
            $beneficiaryMismatches > 0
            || $claimMismatches > 0
            || $unitLinkMismatches > 0
            || $glViolations > 0
            || $rewardInconsistencies > 0
        ) {
            throw new RuntimeException(sprintf(
                'Audit dua-sumber Life & Health GAGAL! Mismatch: Beneficiary=%d, Claims=%d, UnitLink=%d, GL=%d, Rewards=%d',
                $beneficiaryMismatches,
                $claimMismatches,
                $unitLinkMismatches,
                $glViolations,
                $rewardInconsistencies
            ));
        }

        return [
            'status' => 'CLEAN',
            'total_life_policies' => $lifePolicies->count(),
            'total_health_claims' => $healthClaims->count(),
            'total_funds' => $funds->count(),
            'total_guarantee_letters' => $guaranteeLetters->count(),
            'total_wellness_rewards' => $wellnessRewards->count(),
            'beneficiary_allocation_mismatches' => 0,
            'claim_amount_mismatches' => 0,
            'unit_link_aum_mismatches' => 0,
            'guarantee_letter_limit_violations' => 0,
            'wellness_reward_inconsistencies' => 0,
        ];
    }
}
