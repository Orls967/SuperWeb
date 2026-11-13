<?php

declare(strict_types=1);

namespace Modules\Wms\Application\Services;

use Illuminate\Support\Facades\DB;
use Modules\Wms\Domain\Models\Warehouse;

/**
 * Pembantu ringkasan kesiapan operasional & kepatuhan slotting per gudang untuk audit berkala.
 */
final class WmsSlottingAuditCommandHelper
{
    /**
     * @return list<array{
     *     warehouse_code: string,
     *     zone_count: int,
     *     bin_count: int,
     *     unit_cost_count: int,
     *     open_exception_tasks: int
     * }>
     */
    public function collectWarehouseHealth(): array
    {
        $warehouses = Warehouse::query()->orderBy('code')->get();
        $out = [];

        foreach ($warehouses as $wh) {
            $zoneCount = (int) DB::table('wms_zones')->where('warehouse_id', $wh->id)->count();
            $binCount = (int) DB::table('wms_bins as b')
                ->join('wms_racks as r', 'r.id', '=', 'b.rack_id')
                ->join('wms_zones as z', 'z.id', '=', 'r.zone_id')
                ->where('z.warehouse_id', $wh->id)
                ->count();
            $costCount = (int) DB::table('wms_unit_costs')
                ->where('warehouse_id', $wh->id)
                ->where('is_active', true)
                ->count();
            $exceptions = (int) DB::table('wms_tasks')
                ->where('warehouse_id', $wh->id)
                ->where('status', 'exception')
                ->count();

            $out[] = [
                'warehouse_code' => (string) $wh->code,
                'zone_count' => $zoneCount,
                'bin_count' => $binCount,
                'unit_cost_count' => $costCount,
                'open_exception_tasks' => $exceptions,
            ];
        }

        return $out;
    }
}
