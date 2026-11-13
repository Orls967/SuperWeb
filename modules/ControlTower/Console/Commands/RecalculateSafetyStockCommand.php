<?php

declare(strict_types=1);

namespace Modules\ControlTower\Console\Commands;

use Illuminate\Console\Command;
use Modules\ControlTower\Application\Actions\RecalculateSafetyStockAction;
use Modules\ControlTower\Domain\Models\EchelonStock;

final class RecalculateSafetyStockCommand extends Command
{
    protected $signature = 'tower:safety-stock-recalc';

    protected $description = 'Recalculate ABC/XYZ classification and Dynamic Safety Stock policies';

    public function handle(RecalculateSafetyStockAction $action): int
    {
        $count = 0;
        foreach (EchelonStock::query()->select('item_code', 'on_hand_qty')->distinct()->get() as $stock) {
            $action->execute(
                itemCode: $stock->item_code,
                annualUsageValueIdr: 250_000_000,
                totalCatalogValueIdr: 1_000_000_000,
                demandHistory: [100, 105, 98, 102, 101, 99],
                avgLeadTimeDays: 7,
                leadTimeStdDevBps: 1500,
                onHandQty: (int) $stock->on_hand_qty,
                daysSinceLastMove: 10,
            );
            $count++;
        }

        $this->info("Recalculated safety stock policies for {$count} item(s).");

        return self::SUCCESS;
    }
}
