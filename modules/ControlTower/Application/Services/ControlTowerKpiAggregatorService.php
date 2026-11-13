<?php

declare(strict_types=1);

namespace Modules\ControlTower\Application\Services;

use Modules\ControlTower\Application\Actions\CaptureControlTowerKpiSnapshotAction;
use Modules\ControlTower\Domain\Models\ControlTowerKpiSnapshot;
use Modules\ControlTower\Domain\Models\DisruptionAlert;
use Modules\ControlTower\Domain\Models\OrderPromise;

final class ControlTowerKpiAggregatorService
{
    public function __construct(
        private readonly CaptureControlTowerKpiSnapshotAction $captureAction,
    ) {}

    public function recordDailySnapshot(
        int $dioDays = 25,
        int $dsoDays = 20,
        int $dpoDays = 30,
        int $endingInventoryUnits = 10_000,
        int $dailyDemandUnits = 400,
    ): ControlTowerKpiSnapshot {
        $totalOrders = max(1, OrderPromise::query()->count());
        $confirmedOrders = OrderPromise::query()->where('promise_status', 'confirmed')->count();

        return $this->captureAction->execute(
            onTimeInFullOrders: $confirmedOrders,
            totalDeliveredOrders: $totalOrders,
            dioDays: $dioDays,
            dsoDays: $dsoDays,
            dpoDays: $dpoDays,
            endingInventoryUnits: $endingInventoryUnits,
            dailyDemandUnits: $dailyDemandUnits,
            totalLeadTimeHours: $totalOrders * 36,
            logisticsCostIdr: 40_000_000,
            netSalesIdr: 1_000_000_000,
        );
    }

    /**
     * @return array<string, int>
     */
    public function latestExecutiveMetrics(): array
    {
        $latest = ControlTowerKpiSnapshot::query()->latest('id')->first();

        return [
            'otif_bps' => (int) ($latest?->otif_bps ?? 0),
            'cash_to_cash_days' => (int) ($latest?->cash_to_cash_days ?? 0),
            'inventory_dos_days' => (int) ($latest?->inventory_dos_days ?? 0),
            'active_disruptions' => DisruptionAlert::query()->where('status', 'active')->count(),
        ];
    }
}
