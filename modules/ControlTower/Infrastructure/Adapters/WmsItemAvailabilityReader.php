<?php

declare(strict_types=1);

namespace Modules\ControlTower\Infrastructure\Adapters;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Schema;
use Modules\ControlTower\Contracts\ItemAvailabilityReader;
use Modules\ControlTower\Domain\Models\EchelonStock;
use Modules\Logistics\Contracts\InTransitShipmentProvider;
use Modules\Store\Contracts\Product;
use Modules\Wms\Contracts\StockAvailabilityReader;
use Modules\Wms\Contracts\WarehouseValuationReader;

/**
 * Pembaca ketersediaan stok per item via kontrak Store, WMS, Logistics,
 * dengan fallback ke EchelonStock jika SKU belum terdaftar di store_products.
 */
final class WmsItemAvailabilityReader implements ItemAvailabilityReader
{
    public function __construct(
        private readonly StockAvailabilityReader $wmsStockReader,
        private readonly InTransitShipmentProvider $inTransitProvider,
        private readonly ?WarehouseValuationReader $warehouseValuationReader = null,
    ) {}

    public function positionFor(string $itemCode, string $asOfDate): array
    {
        $asOf = CarbonImmutable::parse($asOfDate);
        $product = Schema::hasTable('store_products')
            ? Product::query()->where('sku', $itemCode)->first()
            : null;

        if ($product !== null) {
            return $this->positionFromWms((int) $product->getKey(), $asOf);
        }

        return $this->positionFromEchelonFallback($itemCode, $asOf);
    }

    /**
     * @return array{on_hand:int, available:int, quarantine:int, hold:int, oldest_lot_age_days:int}
     */
    private function positionFromWms(int $productId, CarbonImmutable $asOf): array
    {
        $warehouses = $this->warehouseValuationReader?->getAllWarehousesValuationIdr() ?? [];
        $onHand = 0;
        $quarantine = 0;
        $hold = 0;
        $available = 0;
        $maxTransitHours = 0;

        if ($warehouses !== []) {
            foreach ($warehouses as $wh) {
                $breakdown = $this->wmsStockReader->getBreakdown($productId, $wh['warehouse_id']);
                $onHand += max(0, (int) $breakdown['on_hand_base']);
                $quarantine += max(0, (int) $breakdown['quarantined_base']);
                $hold += max(0, (int) $breakdown['reserved_base']);
                $available += max(0, (int) $breakdown['available_base']);

                $whCode = (string) ($wh['warehouse_code'] ?? '');
                if ($whCode !== '') {
                    foreach ($this->inTransitProvider->getInTransitForWarehouse($whCode) as $shipment) {
                        $maxTransitHours = max($maxTransitHours, (int) ($shipment['hours_in_transit'] ?? 0));
                    }
                }
            }
        } else {
            $available = max(0, $this->wmsStockReader->getAvailableQty($productId));
            $onHand = $available;
            foreach ($this->inTransitProvider->getOverdueInTransitShipments() as $shipment) {
                $maxTransitHours = max($maxTransitHours, (int) ($shipment['hours_in_transit'] ?? 0));
            }
        }

        // Pastikan konsistensi non-available tidak melebihi on_hand saat agregasi lintas gudang.
        $available = min($available, max(0, $onHand - $quarantine - $hold));
        $oldestAgeDays = $maxTransitHours > 0 ? intdiv($maxTransitHours, 24) : 0;

        return [
            'on_hand' => $onHand,
            'available' => $available,
            'quarantine' => $quarantine,
            'hold' => $hold,
            'oldest_lot_age_days' => max(0, $oldestAgeDays + ($asOf->isFuture() ? 0 : 0)),
        ];
    }

    /**
     * @return array{on_hand:int, available:int, quarantine:int, hold:int, oldest_lot_age_days:int}
     */
    private function positionFromEchelonFallback(string $itemCode, CarbonImmutable $asOf): array
    {
        $rows = EchelonStock::query()->where('item_code', $itemCode)->get();
        $onHand = max(0, (int) $rows->sum('on_hand_qty'));
        $hold = max(0, (int) $rows->sum('reserved_qty'));
        $quarantine = 0;
        $available = max(0, $onHand - $hold - $quarantine);

        $maxTransitHours = 0;
        foreach ($rows as $row) {
            foreach ($this->inTransitProvider->getInTransitForWarehouse((string) $row->echelon_node) as $shipment) {
                $maxTransitHours = max($maxTransitHours, (int) ($shipment['hours_in_transit'] ?? 0));
            }
        }

        $oldestAgeDays = $maxTransitHours > 0 ? intdiv($maxTransitHours, 24) : 0;

        return [
            'on_hand' => $onHand,
            'available' => $available,
            'quarantine' => $quarantine,
            'hold' => $hold,
            'oldest_lot_age_days' => max(0, $oldestAgeDays),
        ];
    }
}
