<?php

declare(strict_types=1);

namespace Modules\ControlTower\Application\Services;

use Modules\ControlTower\Domain\Models\EchelonStock;
use Modules\Wms\Contracts\StockAvailabilityReader;

final class ControlTowerWmsSyncService
{
    public function __construct(
        private readonly StockAvailabilityReader $wmsStockReader,
    ) {}

    public function syncWarehouseDcStock(string|int $warehouseId, int $productId, string $itemCode, string $itemName): EchelonStock
    {
        $available = $this->wmsStockReader->getAvailableQuantity($warehouseId, $productId);

        return EchelonStock::query()->updateOrCreate(
            ['item_code' => $itemCode, 'echelon_node' => 'DC'],
            [
                'item_name' => $itemName,
                'on_hand_qty' => $available,
                'in_transit_qty' => 0,
                'reserved_qty' => 0,
                'safety_stock_qty' => max(1, intdiv($available, 10)),
                'abc_class' => 'A',
                'xyz_class' => 'X',
            ],
        );
    }
}
