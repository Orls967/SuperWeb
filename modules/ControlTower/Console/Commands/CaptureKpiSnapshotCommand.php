<?php

declare(strict_types=1);

namespace Modules\ControlTower\Console\Commands;

use Illuminate\Console\Command;
use Modules\ControlTower\Application\Actions\CaptureControlTowerKpiSnapshotAction;

final class CaptureKpiSnapshotCommand extends Command
{
    protected $signature = 'tower:kpi-snapshot {--otif-orders=96} {--total-orders=100}';

    protected $description = 'Capture daily Supply Chain Control Tower KPI snapshot (OTIF, C2C, DOS)';

    public function handle(CaptureControlTowerKpiSnapshotAction $action): int
    {
        $snapshot = $action->execute(
            onTimeInFullOrders: (int) $this->option('otif-orders'),
            totalDeliveredOrders: (int) $this->option('total-orders'),
            dioDays: 28,
            dsoDays: 21,
            dpoDays: 30,
            endingInventoryUnits: 14_000,
            dailyDemandUnits: 500,
            totalLeadTimeHours: 3_600,
            logisticsCostIdr: 45_000_000,
            netSalesIdr: 1_200_000_000,
        );

        $this->info("Captured KPI snapshot #{$snapshot->id} (OTIF {$snapshot->otif_bps} bps, C2C {$snapshot->cash_to_cash_days} days).");

        return self::SUCCESS;
    }
}
