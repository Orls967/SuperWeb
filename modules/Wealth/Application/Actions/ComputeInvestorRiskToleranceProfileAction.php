<?php

declare(strict_types=1);

namespace Modules\Wealth\Application\Actions;

use Modules\Shared\Application\BaseAction;
use Modules\Wealth\Domain\Models\WmProfile;

class ComputeInvestorRiskToleranceProfileAction extends BaseAction
{
    /**
     * Compute investor risk tolerance profile and emergency fund baseline.
     *
     * @return array{profile: WmProfile, risk_tier: string, emergency_fund_target_idr: int}
     */
    public function handle(
        int $userId,
        int $monthlySpendBaselineIdr,
        int $riskSurveyScore, // 0 to 100
        int $emergencyBufferMonths = 6
    ): array {
        return $this->transaction(function () use (
            $userId,
            $monthlySpendBaselineIdr,
            $riskSurveyScore,
            $emergencyBufferMonths
        ) {
            $riskTier = match (true) {
                $riskSurveyScore >= 70 => 'aggressive',
                $riskSurveyScore >= 40 => 'moderate',
                default => 'conservative',
            };

            $emergencyFundTargetIdr = $monthlySpendBaselineIdr * $emergencyBufferMonths;

            $profile = WmProfile::updateOrCreate(
                ['user_id' => $userId],
                [
                    'risk_profile' => $riskTier,
                    'emergency_fund_target_idr' => $emergencyFundTargetIdr,
                    'monthly_spend_baseline_idr' => $monthlySpendBaselineIdr,
                    'auto_invest_enabled' => true,
                ]
            );

            $this->audit(
                action: 'wealth.profile.computed',
                auditable: $profile,
                oldValues: null,
                newValues: [
                    'user_id' => $userId,
                    'risk_tier' => $riskTier,
                    'emergency_target_idr' => $emergencyFundTargetIdr,
                ],
                correlationId: "wm_prof_{$userId}",
                impactType: 'robo_advisor_profiling'
            );

            return [
                'profile' => $profile,
                'risk_tier' => $riskTier,
                'emergency_fund_target_idr' => $emergencyFundTargetIdr,
            ];
        });
    }
}
