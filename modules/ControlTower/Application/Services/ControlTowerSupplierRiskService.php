<?php

declare(strict_types=1);

namespace Modules\ControlTower\Application\Services;

use Modules\ControlTower\Domain\Services\SupplierLeadTimeVariabilityAnalyzer;

final class ControlTowerSupplierRiskService
{
    public function __construct(
        private readonly SupplierLeadTimeVariabilityAnalyzer $analyzer,
    ) {}

    /**
     * @param  list<int>  $recentLeadTimeDays
     * @return array{avg_days: int, max_days: int, reliability_bps: int, risk_tier: string}
     */
    public function assessSupplierRisk(array $recentLeadTimeDays, int $promisedDays): array
    {
        $stats = $this->analyzer->analyze($recentLeadTimeDays, $promisedDays);
        $tier = match (true) {
            $stats['reliability_bps'] < 7000 => 'HIGH',
            $stats['reliability_bps'] < 9000 => 'MEDIUM',
            default => 'LOW',
        };

        return [
            'avg_days' => $stats['avg_days'],
            'max_days' => $stats['max_days'],
            'reliability_bps' => $stats['reliability_bps'],
            'risk_tier' => $tier,
        ];
    }
}

