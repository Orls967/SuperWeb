<?php

declare(strict_types=1);

namespace Modules\Wms\Application\Services;

use Illuminate\Support\Facades\DB;

/**
 * Menghitung peta panas (heatmap) kepadatan aktivitas & okupansi per zona gudang.
 */
final class WmsZoneHeatmapService
{
    /**
     * @return list<array{
     *     zone_id: string,
     *     zone_code: string,
     *     zone_kind: string,
     *     bin_count: int,
     *     occupied_bin_count: int,
     *     occupancy_bps: int,
     *     congestion_level: string
     * }>
     */
    public function computeHeatmap(string|int $warehouseId): array
    {
        $zones = DB::table('wms_zones')
            ->where('warehouse_id', $warehouseId)
            ->orderBy('code')
            ->get();

        $out = [];
        foreach ($zones as $zone) {
            $binIds = DB::table('wms_bins as b')
                ->join('wms_racks as r', 'r.id', '=', 'b.rack_id')
                ->where('r.zone_id', $zone->id)
                ->pluck('b.id')
                ->all();

            $binCount = count($binIds);
            $occupied = $binCount === 0 ? 0 : (int) DB::table('wms_bin_stocks')
                ->whereIn('bin_id', $binIds)
                ->where('qty_base', '>', 0)
                ->distinct('bin_id')
                ->count('bin_id');

            $bps = $binCount > 0 ? intdiv($occupied * 10000, $binCount) : 0;
            $level = match (true) {
                $bps >= 8500 => 'HIGH',
                $bps >= 6000 => 'MEDIUM',
                default => 'LOW',
            };

            $out[] = [
                'zone_id' => (string) $zone->id,
                'zone_code' => (string) $zone->code,
                'zone_kind' => (string) $zone->kind,
                'bin_count' => $binCount,
                'occupied_bin_count' => $occupied,
                'occupancy_bps' => $bps,
                'congestion_level' => $level,
            ];
        }

        return $out;
    }
}
