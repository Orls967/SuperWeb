<?php

declare(strict_types=1);

namespace Modules\Wms\Application\Services;

use Modules\Wms\Application\Actions\ReplenishPickFaceAction;
use Modules\Wms\Domain\Algorithms\ReplenishmentMinMaxCalculator;

/**
 * Orkestrasi evaluasi min-max pick-face dan eksekusi permintaan replenishment.
 */
final class WmsReplenishmentOrchestratorService
{
    public function __construct(
        private readonly ReplenishmentMinMaxCalculator $calculator,
        private readonly ReplenishPickFaceAction $action,
    ) {}

    /**
     * @param  list<array{product_id: int, current_qty: int, avg_daily_demand: int, lead_time_days: int}>  $pickFaces
     * @return array{evaluations: list<array<string, mixed>>, db_replenishments_created: int}
     */
    public function runCycle(?int $warehouseId = null, array $pickFaces = []): array
    {
        $evals = [];
        foreach ($pickFaces as $face) {
            $res = $this->calculator->evaluate(
                currentPickFaceQty: (int) $face['current_qty'],
                avgDailyDemand: (int) $face['avg_daily_demand'],
                leadTimeDays: (int) $face['lead_time_days'],
                safetyDays: 3,
                maxDaysCover: 14,
            );
            $evals[] = array_merge(['product_id' => (int) $face['product_id']], $res);
        }

        $created = $this->action->execute($warehouseId);

        return [
            'evaluations' => $evals,
            'db_replenishments_created' => count($created),
        ];
    }
}
