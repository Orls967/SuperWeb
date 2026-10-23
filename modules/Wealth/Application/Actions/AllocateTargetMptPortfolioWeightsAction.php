<?php

declare(strict_types=1);

namespace Modules\Wealth\Application\Actions;

use Modules\Shared\Application\BaseAction;
use Modules\Wealth\Domain\Models\WmPlan;
use Modules\Wealth\Domain\Models\WmProfile;

class AllocateTargetMptPortfolioWeightsAction extends BaseAction
{
    /**
     * Compute and allocate Modern Portfolio Theory target weights to WmPlan.
     *
     * @return array{plan: WmPlan, mutual_funds_pct: int, gold_pct: int, crypto_pct: int}
     */
    public function handle(int $profileId): array
    {
        return $this->transaction(function () use ($profileId) {
            $profile = WmProfile::where('id', $profileId)->firstOrFail();

            // MPT target asset allocation by risk tier
            [$mfPct, $goldPct, $cryptoPct] = match ($profile->risk_profile) {
                'aggressive' => [40, 30, 30],
                'moderate' => [60, 30, 10],
                default => [80, 20, 0],
            };

            $plan = WmPlan::updateOrCreate(
                ['profile_id' => $profileId],
                [
                    'allocation_mutual_funds_pct' => $mfPct,
                    'allocation_gold_pct' => $goldPct,
                    'allocation_crypto_pct' => $cryptoPct,
                ]
            );

            $this->audit(
                action: 'wealth.mpt.allocated',
                auditable: $plan,
                oldValues: null,
                newValues: [
                    'profile_id' => $profileId,
                    'mutual_funds_pct' => $mfPct,
                    'gold_pct' => $goldPct,
                    'crypto_pct' => $cryptoPct,
                ],
                correlationId: "wm_mpt_{$profileId}",
                impactType: 'portfolio_weighting'
            );

            return [
                'plan' => $plan,
                'mutual_funds_pct' => $mfPct,
                'gold_pct' => $goldPct,
                'crypto_pct' => $cryptoPct,
            ];
        });
    }
}
