<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Change;

final class ChangeRiskMatrixLookupService
{
    /**
     * Standard risk matrix combining likelihood (1..5) and impact (1..5).
     * Returns risk score (1..25) and categorization.
     *
     * @return array{risk_value: int, level: string, approval_authority: string}
     */
    public function evaluateMatrix(int $likelihood, int $impact): array
    {
        $clampedLikelihood = max(1, min(5, $likelihood));
        $clampedImpact = max(1, min(5, $impact));

        $riskValue = $clampedLikelihood * $clampedImpact;

        if ($riskValue >= 16) {
            return [
                'risk_value' => $riskValue,
                'level' => 'EXTREME',
                'approval_authority' => 'CTO_AND_CAB_CHAIR',
            ];
        }

        if ($riskValue >= 10) {
            return [
                'risk_value' => $riskValue,
                'level' => 'HIGH',
                'approval_authority' => 'FULL_CAB_COMMITTEE',
            ];
        }

        if ($riskValue >= 5) {
            return [
                'risk_value' => $riskValue,
                'level' => 'MODERATE',
                'approval_authority' => 'RELEASE_MANAGER',
            ];
        }

        return [
            'risk_value' => $riskValue,
            'level' => 'LOW',
            'approval_authority' => 'PEER_REVIEWER_AUTONOMOUS',
        ];
    }
}
