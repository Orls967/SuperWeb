<?php

declare(strict_types=1);

namespace Modules\ControlTower\Domain\Services;

final class ShelfLifeExpiryRiskEvaluator
{
    /**
     * @return array{risk_level: string, days_remaining: int, action_required: bool}
     */
    public function evaluate(int $daysRemaining, int $expectedSellThroughDays): array
    {
        if ($daysRemaining <= 0) {
            return ['risk_level' => 'EXPIRED', 'days_remaining' => $daysRemaining, 'action_required' => true];
        }

        if ($daysRemaining < $expectedSellThroughDays) {
            return ['risk_level' => 'HIGH', 'days_remaining' => $daysRemaining, 'action_required' => true];
        }

        if ($daysRemaining <= 30) {
            return ['risk_level' => 'MEDIUM', 'days_remaining' => $daysRemaining, 'action_required' => true];
        }

        return ['risk_level' => 'LOW', 'days_remaining' => $daysRemaining, 'action_required' => false];
    }
}
