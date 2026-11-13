<?php

declare(strict_types=1);

namespace Modules\Wms\Console\Commands;

use Illuminate\Console\Command;
use Modules\Wms\Application\Actions\RevalueInventoryCostAction;

class RevalueCostCommand extends Command
{
    protected $signature = 'wms:revalue-cost {product_id} {warehouse_id} {new_cost_idr} {--method=std}';

    protected $description = 'Melakukan revaluasi harga pokok standar produk & posting selisih ke ledger (D41-10/11)';

    public function handle(RevalueInventoryCostAction $action): int
    {
        $res = $action->execute(
            productId: (int) $this->argument('product_id'),
            warehouseId: (int) $this->argument('warehouse_id'),
            newCostIdr: (int) $this->argument('new_cost_idr'),
            method: (string) $this->option('method'),
        );

        $this->info("Revaluasi {$res['direction']}: delta Rp ".number_format($res['delta_value_idr']));

        return self::SUCCESS;
    }
}
