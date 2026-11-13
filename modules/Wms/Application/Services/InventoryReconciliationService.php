<?php

declare(strict_types=1);

namespace Modules\Wms\Application\Services;

use Illuminate\Support\Facades\DB;
use Modules\Inventory\Contracts\InventoryBalanceProvider;
use Modules\Wms\Contracts\WarehouseValuationReader;
use Modules\Wms\Domain\Support\QuantityNormalizer;

/**
 * Rekonsiliasi menyeluruh stok WMS vs InventoryBalanceProvider & WarehouseValuationReader.
 */
final class InventoryReconciliationService
{
    public function __construct(
        private readonly InventoryBalanceProvider $inventoryProvider,
        private readonly WarehouseValuationReader $valuationReader,
    ) {}

    /**
     * @return array{
     *     is_clean: bool,
     *     ledger_discrepancies: list<array<string, mixed>>,
     *     qty_anomalies: list<array{product_id: int, wms_qty: int, inventory_qty: int}>
     * }
     */
    public function reconcileAll(): array
    {
        $valuations = $this->valuationReader->getAllWarehousesValuationIdr();
        $ledgerDiscrepancies = array_values(array_filter(
            $valuations,
            static fn (array $v): bool => $v['discrepancy_idr'] !== 0
        ));

        $wmsRows = DB::table('wms_bin_stocks')
            ->select('product_id', 'qty_base', 'qty')
            ->get();

        $wmsByProduct = [];
        foreach ($wmsRows as $row) {
            $pid = (int) $row->product_id;
            $q = (int) ($row->qty_base ?: QuantityNormalizer::fromDecimalString((string) $row->qty));
            $wmsByProduct[$pid] = ($wmsByProduct[$pid] ?? 0) + $q;
        }

        $invBalances = $this->inventoryProvider->getAllProductBalances();
        $qtyAnomalies = [];

        foreach ($wmsByProduct as $pid => $wmsQty) {
            $invQty = $invBalances[$pid] ?? 0;
            if ($wmsQty < 0 || ($invQty > 0 && $wmsQty > $invQty)) {
                $qtyAnomalies[] = [
                    'product_id' => $pid,
                    'wms_qty' => $wmsQty,
                    'inventory_qty' => $invQty,
                ];
            }
        }

        return [
            'is_clean' => $ledgerDiscrepancies === [] && $qtyAnomalies === [],
            'ledger_discrepancies' => $ledgerDiscrepancies,
            'qty_anomalies' => $qtyAnomalies,
        ];
    }
}
