<?php

declare(strict_types=1);

namespace Modules\ControlTower\Application\Services;

use Modules\ControlTower\Domain\Models\SafetyStockPolicy;
use Modules\ControlTower\Domain\Services\SlowMovingDeadStockDetector;

final class SafetyStockGovernanceService
{
    public function __construct(
        private readonly SlowMovingDeadStockDetector $detector,
    ) {}

    /**
     * @return array{total_policies: int, slow_moving_count: int, dead_stock_count: int, class_a_count: int}
     */
    public function summarizeCatalogHealth(): array
    {
        $policies = SafetyStockPolicy::query()->get();

        return [
            'total_policies' => $policies->count(),
            'slow_moving_count' => $policies->where('slow_moving_flag', true)->count(),
            'dead_stock_count' => $policies->where('dead_stock_flag', true)->count(),
            'class_a_count' => $policies->where('abc_class', 'A')->count(),
        ];
    }

    /**
     * @return array{slow_moving: bool, dead_stock: bool, coverage_days: int}
     */
    public function inspectItemAging(int $onHandQty, int $avgDailySalesQty, int $daysSinceLastMove): array
    {
        return $this->detector->classify($onHandQty, $avgDailySalesQty, $daysSinceLastMove);
    }
}
