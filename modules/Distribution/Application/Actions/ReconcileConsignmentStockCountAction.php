<?php

declare(strict_types=1);

namespace Modules\Distribution\Application\Actions;

use Modules\Distribution\Domain\Models\ConsignmentSale;
use Modules\Distribution\Domain\Models\ConsignmentStock;

class ReconcileConsignmentStockCountAction
{
    /**
     * Reconcile physical consignment count against system stock ledger.
     * Discrepancies (physical < system) are converted to unbilled consignment sales.
     *
     * @return array{stock: ConsignmentStock, variance_qty: float, sale: ?ConsignmentSale}
     */
    public function execute(
        ConsignmentStock $consignmentStock,
        float $physicalCountQty,
        ?int $userId = null
    ): array {
        $systemQty = (float) $consignmentStock->qty;
        $varianceQty = max(0.0, $systemQty - $physicalCountQty);
        $sale = null;

        if ($varianceQty > 0.0) {
            $unitPriceIdr = $systemQty > 0
                ? (int) round(((int) $consignmentStock->value_idr) / $systemQty)
                : 50000;

            $newSoldUnbilled = (float) $consignmentStock->qty_sold_unbilled + $varianceQty;
            $newValueIdr = (int) round($physicalCountQty * $unitPriceIdr);

            $sale = ConsignmentSale::create([
                'consignment_id' => (string) $consignmentStock->id,
                'sold_at' => now()->toDateString(),
                'qty' => $varianceQty,
                'unit_price_idr' => $unitPriceIdr,
                'status' => 'unbilled',
                'reported_by_user_id' => $userId,
            ]);

            $consignmentStock->update([
                'qty' => $physicalCountQty,
                'qty_sold_unbilled' => $newSoldUnbilled,
                'value_idr' => $newValueIdr,
                'last_reconciled_at' => now()->toDateString(),
            ]);
        } else {
            $consignmentStock->update([
                'last_reconciled_at' => now()->toDateString(),
            ]);
        }

        return [
            'stock' => $consignmentStock->fresh(),
            'variance_qty' => $varianceQty,
            'sale' => $sale,
        ];
    }
}
