<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Application\Listeners;

use Modules\Manufacturing\Application\Services\CostingService;
use Modules\Manufacturing\Domain\Models\LotSale;
use Modules\Manufacturing\Domain\Models\Material;
use Modules\Manufacturing\Domain\Models\MaterialLot;
use Modules\Store\Contracts\StoreOrderReaderInterface;
use Modules\Store\Domain\Events\OrderPaid;

/**
 * 38.5 COGS saat barang jadi terjual: dengarkan event Store `OrderPaid`
 * (dipublish `Order::onPaymentCaptured`) dan posting HPP per baris item
 * yang SKU-nya cocok dengan kode material pabrik. Idempoten per
 * (order, item) via key ledger `mfg:cogs:{order}:{item}`.
 */
class PostSaleCogsListener
{
    public function __construct(private readonly CostingService $costing) {}

    public function handle(OrderPaid $event): void
    {
        // 39.5 Jejak maju: alokasi penjualan item → lot FIFO (trace forward).
        $this->recordLotSales($event->orderId, $event->userId);

        // 38.5 HPP penjualan.
        $this->costing->postOrderCogs($event->orderId);
    }

    /** Alokasi FIFO per SKU → mfg_lot_sales; idempoten per item order. */
    private function recordLotSales(int $orderId, int $userId): void
    {
        $items = app(StoreOrderReaderInterface::class)->getOrderItemsWithProduct($orderId);

        foreach ($items as $item) {
            if ($item->sku === null || $item->sku === '') {
                continue;
            }
            if (LotSale::where('store_order_item_id', $item->item_id)->exists()) {
                continue; // replay aman
            }

            $material = Material::where('code', $item->sku)->first();
            if ($material === null) {
                continue;
            }

            $remaining = (int) $item->qty;
            foreach (MaterialLot::where('material_id', $material->id)
                ->whereIn('status', ['active', 'blocked'])
                ->where('qty', '>', 0)
                ->orderBy('produced_at')->get() as $lot) {
                if ($remaining <= 0) {
                    break;
                }
                $take = min((int) ceil((float) $lot->qty), $remaining);
                LotSale::create([
                    'lot_id' => $lot->id,
                    'store_order_item_id' => (int) $item->item_id,
                    'store_order_id' => $orderId,
                    'user_id' => $userId,
                    'qty' => $take,
                    'cost_idr' => $take * (int) $lot->unit_cost_idr,
                ]);
                $remaining -= $take;
            }
        }
    }
}
