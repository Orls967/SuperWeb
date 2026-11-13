<?php

declare(strict_types=1);

namespace Modules\Wms\Console\Commands;

use Illuminate\Console\Command;
use Modules\Wms\Application\Services\WarehouseUtilizationAnalyzer;
use Modules\Wms\Domain\Models\Warehouse;

class UtilizationSnapshotCommand extends Command
{
    protected $signature = 'wms:utilization-snapshot {--warehouse_id= : ID gudang opsional}';

    protected $description = 'Mengambil snapshot utilisasi bin & deteksi dead stock gudang';

    public function handle(WarehouseUtilizationAnalyzer $analyzer): int
    {
        $whId = $this->option('warehouse_id');
        $ids = $whId !== null ? [(int) $whId] : Warehouse::pluck('id')->all();

        foreach ($ids as $id) {
            $snap = $analyzer->captureSnapshot((int) $id);
            $this->line("WH #{$snap['warehouse_id']}: {$snap['occupied_bins']}/{$snap['total_bins']} ({$snap['utilization_bps']} bps)");
        }

        return self::SUCCESS;
    }
}
