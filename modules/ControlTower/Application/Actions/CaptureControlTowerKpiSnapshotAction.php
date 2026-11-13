<?php

declare(strict_types=1);

namespace Modules\ControlTower\Application\Actions;

use Modules\ControlTower\Domain\Models\ControlTowerKpiSnapshot;
use Modules\ControlTower\Domain\Services\OtifAndC2cCalculator;
use Modules\Core\Contracts\ClockInterface;

final class CaptureControlTowerKpiSnapshotAction
{
    public function __construct(
        private readonly OtifAndC2cCalculator $calculator,
        private readonly ClockInterface $clock,
    ) {}

    public function execute(
        int $onTimeInFullOrders,
        int $totalDeliveredOrders,
        int $dioDays,
        int $dsoDays,
        int $dpoDays,
        int $endingInventoryUnits,
        int $dailyDemandUnits,
        int $totalLeadTimeHours,
        int $logisticsCostIdr,
        int $netSalesIdr,
    ): ControlTowerKpiSnapshot {
        $bundle = $this->calculator->calculate(
            onTimeInFullOrders: $onTimeInFullOrders,
            totalDeliveredOrders: $totalDeliveredOrders,
            dioDays: $dioDays,
            dsoDays: $dsoDays,
            dpoDays: $dpoDays,
            endingInventoryUnits: $endingInventoryUnits,
            dailyDemandUnits: $dailyDemandUnits,
            totalLeadTimeHours: $totalLeadTimeHours,
            logisticsCostIdr: $logisticsCostIdr,
            netSalesIdr: $netSalesIdr,
        );

        return ControlTowerKpiSnapshot::create([
            'snapshot_date' => $this->clock->now()->toDateString(),
            'otif_bps' => $bundle->otifBps,
            'cash_to_cash_days' => $bundle->cashToCashDays,
            'inventory_dos_days' => $bundle->inventoryDosDays,
            'fulfillment_lead_time_hours' => $bundle->fulfillmentLeadTimeHours,
            'logistics_cost_ratio_bps' => $bundle->logisticsCostRatioBps,
        ]);
    }
}
