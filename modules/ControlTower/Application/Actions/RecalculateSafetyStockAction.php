<?php

declare(strict_types=1);

namespace Modules\ControlTower\Application\Actions;

use Modules\ControlTower\Domain\Algorithms\SafetyStockAbcXyzOptimizer;
use Modules\ControlTower\Domain\Models\SafetyStockPolicy;
use Modules\ControlTower\Domain\Services\SlowMovingDeadStockDetector;

final class RecalculateSafetyStockAction
{
    public function __construct(
        private readonly SafetyStockAbcXyzOptimizer $optimizer,
        private readonly SlowMovingDeadStockDetector $detector,
    ) {}

    /**
     * @param  list<int>  $demandHistory
     */
    public function execute(
        string $itemCode,
        int $annualUsageValueIdr,
        int $totalCatalogValueIdr,
        array $demandHistory,
        int $avgLeadTimeDays,
        int $leadTimeStdDevBps,
        int $onHandQty,
        int $daysSinceLastMove,
        int $serviceLevelBps = 9500,
    ): SafetyStockPolicy {
        $opt = $this->optimizer->optimize(
            itemCode: $itemCode,
            annualUsageValueIdr: $annualUsageValueIdr,
            totalCatalogValueIdr: $totalCatalogValueIdr,
            demandHistory: $demandHistory,
            avgLeadTimeDays: $avgLeadTimeDays,
            leadTimeStdDevBps: $leadTimeStdDevBps,
            serviceLevelBps: $serviceLevelBps,
        );

        $avgDaily = count($demandHistory) > 0 ? intdiv(array_sum($demandHistory), count($demandHistory) * 30) : 0;
        $aging = $this->detector->classify($onHandQty, max(0, $avgDaily), $daysSinceLastMove);

        return SafetyStockPolicy::updateOrCreate(
            ['item_code' => $itemCode],
            [
                'abc_class' => $opt['abc_class'],
                'xyz_class' => $opt['xyz_class'],
                'service_level_bps' => $serviceLevelBps,
                'safety_stock_qty' => $opt['safety_stock_qty'],
                'reorder_point_qty' => $opt['reorder_point_qty'],
                'slow_moving_flag' => $aging['slow_moving'],
                'dead_stock_flag' => $aging['dead_stock'],
            ],
        );
    }
}
