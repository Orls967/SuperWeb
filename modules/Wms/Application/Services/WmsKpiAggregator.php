<?php

declare(strict_types=1);

namespace Modules\Wms\Application\Services;

use Illuminate\Support\Facades\DB;

/**
 * Agregator KPI operasional gudang (integer & basis points murni).
 */
final class WmsKpiAggregator
{
    /**
     * @return array{
     *     total_tasks: int,
     *     closed_tasks: int,
     *     exception_tasks: int,
     *     completion_rate_bps: int,
     *     active_reservations: int,
     *     quarantined_bins: int
     * }
     */
    public function summarize(string|int $warehouseId): array
    {
        $totalTasks = (int) DB::table('wms_tasks')->where('warehouse_id', $warehouseId)->count();
        $closedTasks = (int) DB::table('wms_tasks')->where('warehouse_id', $warehouseId)->where('status', 'closed')->count();
        $exceptionTasks = (int) DB::table('wms_tasks')->where('warehouse_id', $warehouseId)->where('status', 'exception')->count();

        $completionBps = $totalTasks > 0 ? intdiv($closedTasks * 10000, $totalTasks) : 0;

        $activeReservations = (int) DB::table('wms_stock_reservations')
            ->where('warehouse_id', $warehouseId)
            ->where('status', 'active')
            ->count();

        $quarantinedBins = (int) DB::table('wms_bin_stocks as bs')
            ->join('wms_bins as b', 'b.id', '=', 'bs.bin_id')
            ->join('wms_racks as r', 'r.id', '=', 'b.rack_id')
            ->join('wms_zones as z', 'z.id', '=', 'r.zone_id')
            ->where('z.warehouse_id', $warehouseId)
            ->where('bs.stock_status', 'quarantine')
            ->count();

        return [
            'total_tasks' => $totalTasks,
            'closed_tasks' => $closedTasks,
            'exception_tasks' => $exceptionTasks,
            'completion_rate_bps' => $completionBps,
            'active_reservations' => $activeReservations,
            'quarantined_bins' => $quarantinedBins,
        ];
    }
}
