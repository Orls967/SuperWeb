<?php

declare(strict_types=1);

namespace Modules\Wms\Application\Services;

use Illuminate\Support\Facades\DB;
use Modules\Wms\Contracts\WarehouseValuationReader;

/**
 * Mengekspor snapshot persediaan & valuasi gudang dalam format terstruktur.
 */
final class WmsInventorySnapshotExporter
{
    public function __construct(
        private readonly WarehouseValuationReader $valuationReader,
    ) {}

    /**
     * @return array{
     *     valuations: list<array<string, mixed>>,
     *     bin_stock_count: int,
     *     total_qty_base: int
     * }
     */
    public function exportSummary(): array
    {
        $valuations = $this->valuationReader->getAllWarehousesValuationIdr();
        $count = (int) DB::table('wms_bin_stocks')->count();
        $totalQty = (int) DB::table('wms_bin_stocks')->sum('qty_base');

        return [
            'valuations' => $valuations,
            'bin_stock_count' => $count,
            'total_qty_base' => $totalQty,
        ];
    }
}
