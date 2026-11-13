<?php

declare(strict_types=1);

namespace Modules\ControlTower\Domain\Services;

final class SlowMovingDeadStockDetector
{
    /**
     * @return array{slow_moving: bool, dead_stock: bool, coverage_days: int}
     */
    public function classify(int $onHandQty, int $avgDailySalesQty, int $daysSinceLastMove): array
    {
        if ($onHandQty <= 0) {
            return ['slow_moving' => false, 'dead_stock' => false, 'coverage_days' => 0];
        }

        $coverageDays = $avgDailySalesQty > 0 ? intdiv($onHandQty, $avgDailySalesQty) : 9999;
        $deadStock = $daysSinceLastMove >= 180 || ($avgDailySalesQty === 0 && $daysSinceLastMove >= 90);
        $slowMoving = ! $deadStock && ($coverageDays >= 90 || $daysSinceLastMove >= 60);

        return [
            'slow_moving' => $slowMoving,
            'dead_stock' => $deadStock,
            'coverage_days' => $coverageDays,
        ];
    }
}
