<?php

declare(strict_types=1);

namespace Modules\Wms\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Wms\Domain\Models\Replenishment;

/**
 * Pengisian ulang pick-face dari zona reserve ketika stok pick-face di bawah minimum.
 */
final class ReplenishPickFaceAction
{
    /**
     * @return list<array{warehouse_id: int, product_id: int, replenishment_id: int, qty_requested: int}>
     */
    public function execute(?int $warehouseId = null, int $minThreshold = 20, int $targetQty = 100): array
    {
        $query = DB::table('wms_bin_stocks as bs')
            ->join('wms_bins as b', 'b.id', '=', 'bs.bin_id')
            ->join('wms_racks as r', 'r.id', '=', 'b.rack_id')
            ->join('wms_zones as z', 'z.id', '=', 'r.zone_id')
            ->where('z.kind', 'pick')
            ->where('bs.qty_base', '<', $minThreshold)
            ->select('z.warehouse_id', 'bs.bin_id', 'bs.product_id', 'bs.qty_base');

        if ($warehouseId !== null) {
            $query->where('z.warehouse_id', $warehouseId);
        }

        $lowRows = $query->get();
        $created = [];

        foreach ($lowRows as $row) {
            $needed = max(1, $targetQty - (int) $row->qty_base);
            $rep = Replenishment::create([
                'warehouse_id' => (int) $row->warehouse_id,
                'product_id' => (int) $row->product_id,
                'to_bin_id' => (int) $row->bin_id,
                'qty' => $needed,
                'status' => 'pending',
            ]);
            $created[] = [
                'warehouse_id' => (int) $row->warehouse_id,
                'product_id' => (int) $row->product_id,
                'replenishment_id' => (int) $rep->id,
                'qty_requested' => $needed,
            ];
        }

        return $created;
    }
}
