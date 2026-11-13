<?php

declare(strict_types=1);

namespace Modules\Wms\Application\Services;

use Modules\Inventory\Contracts\InventoryBalanceProvider;
use Modules\Logistics\Contracts\CarrierCutoffProvider;
use Modules\Logistics\Contracts\InTransitShipmentProvider;
use Modules\Wms\Contracts\WarehouseValuationReader;

/**
 * Orkestrator sinkronisasi status lintas modul (WMS <-> Inventory <-> Logistics).
 */
final class WmsCrossModuleSyncService
{
    public function __construct(
        private readonly InventoryBalanceProvider $inventoryProvider,
        private readonly InTransitShipmentProvider $inTransitProvider,
        private readonly CarrierCutoffProvider $cutoffProvider,
        private readonly WarehouseValuationReader $valuationReader,
    ) {}

    /**
     * @return array{
     *     warehouse_code: string,
     *     valuation: array<string, mixed>,
     *     in_transit_count: int,
     *     overdue_in_transit_count: int,
     *     open_carrier_cutoffs: int,
     *     tracked_inventory_skus: int
     * }
     */
    public function snapshotForWarehouse(string|int $warehouseId, string $warehouseCode): array
    {
        $valuation = $this->valuationReader->getWarehouseValuationIdr($warehouseId);
        $inTransit = $this->inTransitProvider->getInTransitForWarehouse($warehouseCode);
        $overdue = $this->inTransitProvider->getOverdueInTransitShipments(72);
        $cutoffs = $this->cutoffProvider->getCutoffsForWarehouse($warehouseCode);
        $invBalances = $this->inventoryProvider->getAllProductBalances();

        $openCutoffs = 0;
        foreach ($cutoffs as $c) {
            if (! $c['is_closed']) {
                $openCutoffs++;
            }
        }

        return [
            'warehouse_code' => $warehouseCode,
            'valuation' => $valuation,
            'in_transit_count' => count($inTransit),
            'overdue_in_transit_count' => count($overdue),
            'open_carrier_cutoffs' => $openCutoffs,
            'tracked_inventory_skus' => count($invBalances),
        ];
    }
}
