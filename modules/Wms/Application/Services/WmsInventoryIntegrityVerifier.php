<?php

declare(strict_types=1);

namespace Modules\Wms\Application\Services;

use Illuminate\Support\Facades\DB;
use Modules\Wms\Contracts\WarehouseValuationReader;
use Modules\Wms\Domain\Support\QuantityNormalizer;

/**
 * Verifikator integritas data lintas tabel WMS (bin, stok, reservasi, & buku besar).
 */
final class WmsInventoryIntegrityVerifier
{
    public function __construct(
        private readonly WarehouseValuationReader $valuationReader,
    ) {}

    /**
     * @return array{
     *     negative_qty_count: int,
     *     over_reserved_count: int,
     *     missing_cost_products: list<int>,
     *     ledger_mismatches: list<array<string, mixed>>,
     *     is_healthy: bool
     * }
     */
    public function verify(): array
    {
        $negativeCount = (int) DB::table('wms_bin_stocks')
            ->where(function ($q): void {
                $q->where('qty', '<', 0)->orWhere('qty_base', '<', 0);
            })
            ->count();

        $overReserved = 0;
        $activeRes = DB::table('wms_stock_reservations')
            ->where('status', 'active')
            ->select('warehouse_id', 'product_id', DB::raw('SUM(qty_base) as res_qty'))
            ->groupBy('warehouse_id', 'product_id')
            ->get();

        foreach ($activeRes as $res) {
            $onHand = (int) DB::table('wms_bin_stocks as bs')
                ->join('wms_bins as b', 'b.id', '=', 'bs.bin_id')
                ->join('wms_racks as r', 'r.id', '=', 'b.rack_id')
                ->join('wms_zones as z', 'z.id', '=', 'r.zone_id')
                ->where('z.warehouse_id', $res->warehouse_id)
                ->where('bs.product_id', $res->product_id)
                ->where('bs.stock_status', 'available')
                ->sum('bs.qty_base');

            if ((int) $res->res_qty > $onHand) {
                $overReserved++;
            }
        }

        $stockProducts = DB::table('wms_bin_stocks')
            ->where('qty_base', '>', 0)
            ->distinct()
            ->pluck('product_id')
            ->all();

        $missingCosts = [];
        foreach ($stockProducts as $pid) {
            $hasCost = DB::table('wms_unit_costs')
                ->where('product_id', $pid)
                ->where('is_active', true)
                ->exists();
            if (! $hasCost) {
                $missingCosts[] = (int) $pid;
            }
        }

        $valuations = $this->valuationReader->getAllWarehousesValuationIdr();
        $ledgerMismatches = array_values(array_filter(
            $valuations,
            static fn (array $v): bool => $v['discrepancy_idr'] !== 0
        ));

        return [
            'negative_qty_count' => $negativeCount,
            'over_reserved_count' => $overReserved,
            'missing_cost_products' => $missingCosts,
            'ledger_mismatches' => $ledgerMismatches,
            'is_healthy' => $negativeCount === 0
                && $overReserved === 0
                && $ledgerMismatches === [],
        ];
    }

    /**
     * Menghitung total nilai stok fisik seluruh gudang dalam IDR minor units.
     */
    public function computeGlobalSubledgerValueIdr(): int
    {
        $rows = DB::table('wms_bin_stocks as bs')
            ->join('wms_bins as b', 'b.id', '=', 'bs.bin_id')
            ->join('wms_racks as r', 'r.id', '=', 'b.rack_id')
            ->join('wms_zones as z', 'z.id', '=', 'r.zone_id')
            ->where('bs.qty_base', '>', 0)
            ->select('z.warehouse_id', 'bs.product_id', 'bs.qty_base')
            ->get();

        $total = 0;
        foreach ($rows as $row) {
            $cost = (int) (DB::table('wms_unit_costs')
                ->where('product_id', $row->product_id)
                ->where('is_active', true)
                ->where(function ($q) use ($row): void {
                    $q->where('warehouse_id', $row->warehouse_id)->orWhereNull('warehouse_id');
                })
                ->orderByRaw('CASE WHEN warehouse_id IS NOT NULL THEN 0 ELSE 1 END')
                ->orderByDesc('effective_date')
                ->value('cost_idr') ?? 0);

            $total += QuantityNormalizer::computeValueIdr((int) $row->qty_base, $cost);
        }

        return $total;
    }
}
