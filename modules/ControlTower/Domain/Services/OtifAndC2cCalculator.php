<?php

declare(strict_types=1);

namespace Modules\ControlTower\Domain\Services;

use Modules\ControlTower\Domain\ValueObjects\SupplyChainKpiBundle;

final class OtifAndC2cCalculator
{
    public function calculate(
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
    ): SupplyChainKpiBundle {
        $otifBps = $totalDeliveredOrders > 0
            ? intdiv($onTimeInFullOrders * 10_000, $totalDeliveredOrders)
            : 10_000;

        $c2cDays = $dioDays + $dsoDays - $dpoDays;
        $dosDays = $dailyDemandUnits > 0 ? intdiv($endingInventoryUnits, $dailyDemandUnits) : 0;
        $avgLeadHours = $totalDeliveredOrders > 0 ? intdiv($totalLeadTimeHours, $totalDeliveredOrders) : 0;
        $logisticsRatioBps = $netSalesIdr > 0 ? intdiv($logisticsCostIdr * 10_000, $netSalesIdr) : 0;

        return new SupplyChainKpiBundle(
            otifBps: $otifBps,
            cashToCashDays: $c2cDays,
            inventoryDosDays: $dosDays,
            fulfillmentLeadTimeHours: $avgLeadHours,
            logisticsCostRatioBps: $logisticsRatioBps,
        );
    }
}
