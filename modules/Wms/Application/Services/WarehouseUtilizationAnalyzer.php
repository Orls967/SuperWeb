<?php

declare(strict_types=1);

namespace Modules\Wms\Application\Services;

use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\ClockInterface;
use Modules\Wms\Domain\Models\UtilizationSnapshot;

/**
 * X41-4: Analisis utilisasi bin, deteksi dead stock, dan snapshot kapasitas (integer bps).
 */
final class WarehouseUtilizationAnalyzer
{
    public function __construct(
        private readonly ClockInterface $clock,
    ) {}

    /**
     * @return array{
     *     warehouse_id: int,
     *     total_bins: int,
     *     occupied_bins: int,
     *     utilization_bps: int,
     *     dead_stock_sku_count: int,
     *     slotting_mismatch_count: int
     * }
     */
    public function captureSnapshot(int $warehouseId, ?int $zoneId = null): array
    {
        $binsQuery = DB::table('wms_bins as b')
            ->join('wms_racks as r', 'r.id', '=', 'b.rack_id')
            ->join('wms_zones as z', 'z.id', '=', 'r.zone_id')
            ->where('z.warehouse_id', $warehouseId);

        if ($zoneId !== null) {
            $binsQuery->where('z.id', $zoneId);
        }

        $binIds = $binsQuery->pluck('b.id')->all();
        $totalBins = count($binIds);

        $occupiedBins = $totalBins === 0 ? 0 : (int) DB::table('wms_bin_stocks')
            ->whereIn('bin_id', $binIds)
            ->where(function ($q): void {
                $q->where('qty_base', '>', 0)->orWhere('qty', '>', 0);
            })
            ->distinct('bin_id')
            ->count('bin_id');

        $utilizationBps = $totalBins > 0 ? intdiv($occupiedBins * 10000, $totalBins) : 0;

        $cutoffDate = $this->clock->now()->subDays(90);
        $deadStockSkuCount = $totalBins === 0 ? 0 : (int) DB::table('wms_bin_stocks')
            ->whereIn('bin_id', $binIds)
            ->where('updated_at', '<', $cutoffDate)
            ->where(function ($q): void {
                $q->where('qty_base', '>', 0)->orWhere('qty', '>', 0);
            })
            ->distinct('product_id')
            ->count('product_id');

        $slottingMismatchCount = (int) DB::table('wms_slottings')
            ->where('warehouse_id', $warehouseId)
            ->whereColumn('current_zone_id', '!=', 'recommended_zone_id')
            ->count();

        UtilizationSnapshot::create([
            'warehouse_id' => $warehouseId,
            'zone_id' => $zoneId,
            'snapshot_date' => $this->clock->now()->toDateString(),
            'total_bins' => $totalBins,
            'occupied_bins' => $occupiedBins,
            'utilization_bps' => $utilizationBps,
            'dead_stock_sku_count' => $deadStockSkuCount,
            'slotting_mismatch_count' => $slottingMismatchCount,
        ]);

        return [
            'warehouse_id' => $warehouseId,
            'total_bins' => $totalBins,
            'occupied_bins' => $occupiedBins,
            'utilization_bps' => $utilizationBps,
            'dead_stock_sku_count' => $deadStockSkuCount,
            'slotting_mismatch_count' => $slottingMismatchCount,
        ];
    }
}
