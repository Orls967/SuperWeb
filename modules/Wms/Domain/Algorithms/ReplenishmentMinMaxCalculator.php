<?php

declare(strict_types=1);

namespace Modules\Wms\Domain\Algorithms;

use InvalidArgumentException;

/**
 * Kalkulator Min-Max & Safety Stock untuk replenishment pick-face (integer murni).
 */
final class ReplenishmentMinMaxCalculator
{
    /**
     * @return array{
     *     safety_stock: int,
     *     reorder_point: int,
     *     max_level: int,
     *     suggested_replenish_qty: int,
     *     needs_replenishment: bool
     * }
     */
    public function evaluate(
        int $currentPickFaceQty,
        int $avgDailyDemand,
        int $leadTimeDays,
        int $safetyDays,
        int $maxDaysCover,
    ): array {
        if ($avgDailyDemand < 0 || $leadTimeDays < 0 || $safetyDays < 0 || $maxDaysCover <= 0) {
            throw new InvalidArgumentException('Parameter min-max replenishment tidak valid.');
        }

        $safetyStock = $avgDailyDemand * $safetyDays;
        $reorderPoint = ($avgDailyDemand * $leadTimeDays) + $safetyStock;
        $maxLevel = ($avgDailyDemand * $maxDaysCover) + $safetyStock;

        $needs = $currentPickFaceQty <= $reorderPoint;
        $suggested = $needs ? max(0, $maxLevel - $currentPickFaceQty) : 0;

        return [
            'safety_stock' => $safetyStock,
            'reorder_point' => $reorderPoint,
            'max_level' => $maxLevel,
            'suggested_replenish_qty' => $suggested,
            'needs_replenishment' => $needs,
        ];
    }
}
