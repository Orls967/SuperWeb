<?php

declare(strict_types=1);

namespace Modules\Wms\Http\ViewModels;

use Illuminate\Support\Facades\DB;
use Modules\Wms\Contracts\WarehouseValuationReader;
use Modules\Wms\Domain\Models\ReceivingReceipt;
use Modules\Wms\Domain\Models\Task;
use Modules\Wms\Domain\Models\UtilizationSnapshot;
use Modules\Wms\Domain\Models\Warehouse;

/**
 * ViewModel tipis untuk seluruh halaman operasional & penilaian WMS.
 */
final class WmsOperationsViewModel
{
    public function __construct(
        private readonly WarehouseValuationReader $valuationReader,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function valuationSummary(): array
    {
        return [
            'warehouses' => $this->valuationReader->getAllWarehousesValuationIdr(),
            'unit_costs' => DB::table('wms_unit_costs')->where('is_active', true)->orderByDesc('effective_date')->limit(50)->get(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function handheldTasksData(?int $warehouseId = null): array
    {
        $query = Task::query()->orderByDesc('id')->limit(50);
        if ($warehouseId !== null) {
            $query->where('warehouse_id', $warehouseId);
        }

        return [
            'warehouses' => Warehouse::orderBy('code')->get(),
            'tasks' => $query->get(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function receivingData(): array
    {
        return [
            'warehouses' => Warehouse::orderBy('code')->get(),
            'receipts' => ReceivingReceipt::orderByDesc('id')->limit(50)->get(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function quarantineData(): array
    {
        return [
            'quarantined_stocks' => DB::table('wms_bin_stocks')
                ->whereIn('stock_status', ['quarantine', 'blocked'])
                ->orderByDesc('updated_at')
                ->limit(100)
                ->get(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function utilizationData(): array
    {
        return [
            'warehouses' => Warehouse::orderBy('code')->get(),
            'snapshots' => UtilizationSnapshot::orderByDesc('id')->limit(30)->get(),
        ];
    }
}
