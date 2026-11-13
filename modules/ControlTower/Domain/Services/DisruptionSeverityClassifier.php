<?php

declare(strict_types=1);

namespace Modules\ControlTower\Domain\Services;

final class DisruptionSeverityClassifier
{
    public function classify(int $affectedOrdersCount, int $estimatedDelayDays, int $revenueAtRiskIdr): string
    {
        if ($affectedOrdersCount >= 20 || $estimatedDelayDays >= 10 || $revenueAtRiskIdr >= 500_000_000) {
            return 'CRITICAL';
        }

        if ($affectedOrdersCount >= 5 || $estimatedDelayDays >= 4 || $revenueAtRiskIdr >= 100_000_000) {
            return 'HIGH';
        }

        if ($affectedOrdersCount >= 1) {
            return 'MEDIUM';
        }

        return 'LOW';
    }
}
