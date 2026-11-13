<?php

declare(strict_types=1);

namespace Modules\Wms\Infrastructure\Adapters;

use Illuminate\Support\Facades\DB;
use Modules\Wms\Application\Actions\WmsLedgerPoster;
use Modules\Wms\Contracts\WarehouseValuationReader;
use Modules\Wms\Domain\Models\Warehouse;
use Modules\Wms\Domain\Support\QuantityNormalizer;
use Modules\Wms\Ledger\WmsAccounts;

/**
 * Adaptor pembaca penilaian persediaan gudang & rekonsiliasi saldo buku besar.
 */
final class WarehouseValuationReaderAdapter implements WarehouseValuationReader
{
    public function __construct(
        private readonly WmsLedgerPoster $poster,
    ) {}

    public function getWarehouseValuationIdr(string|int $warehouseId): array
    {
        $warehouse = Warehouse::findOrFail($warehouseId);
        $code = (string) $warehouse->code;
        $accountCode = WmsAccounts::inventoryForWarehouse($code);

        $rows = DB::table('wms_bin_stocks as bs')
            ->join('wms_bins as b', 'b.id', '=', 'bs.bin_id')
            ->join('wms_racks as r', 'r.id', '=', 'b.rack_id')
            ->join('wms_zones as z', 'z.id', '=', 'r.zone_id')
            ->where('z.warehouse_id', $warehouse->id)
            ->select('bs.product_id', 'bs.qty_base', 'bs.qty')
            ->get();

        $subledgerValue = 0;
        foreach ($rows as $row) {
            $qtyBase = (int) ($row->qty_base ?: QuantityNormalizer::fromDecimalString((string) $row->qty));
            if ($qtyBase <= 0) {
                continue;
            }
            $cost = $this->resolveUnitCost((int) $row->product_id, $warehouse->id);
            $subledgerValue += QuantityNormalizer::computeValueIdr($qtyBase, $cost);
        }

        $ledgerBalance = $this->poster->getWarehouseLedgerBalanceIdr($code);

        return [
            'warehouse_id' => $warehouse->id,
            'warehouse_code' => $code,
            'account_code' => $accountCode,
            'subledger_value_idr' => $subledgerValue,
            'ledger_balance_idr' => $ledgerBalance,
            'discrepancy_idr' => $subledgerValue - $ledgerBalance,
        ];
    }

    public function getAllWarehousesValuationIdr(): array
    {
        $warehouses = Warehouse::query()->orderBy('id')->pluck('id');
        $out = [];
        foreach ($warehouses as $id) {
            $out[] = $this->getWarehouseValuationIdr($id);
        }

        return $out;
    }

    private function resolveUnitCost(int $productId, string|int $warehouseId): int
    {
        $cost = DB::table('wms_unit_costs')
            ->where('product_id', $productId)
            ->where('is_active', true)
            ->where(function ($q) use ($warehouseId): void {
                $q->where('warehouse_id', $warehouseId)->orWhereNull('warehouse_id');
            })
            ->orderByRaw('CASE WHEN warehouse_id IS NOT NULL THEN 0 ELSE 1 END')
            ->orderByDesc('effective_date')
            ->value('cost_idr');

        return (int) ($cost ?? 0);
    }
}
