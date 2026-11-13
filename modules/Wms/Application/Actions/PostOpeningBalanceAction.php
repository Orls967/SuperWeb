<?php

declare(strict_types=1);

namespace Modules\Wms\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Wms\Domain\Models\Warehouse;
use Modules\Wms\Domain\Support\QuantityNormalizer;

/**
 * Posting saldo awal persediaan gudang ke buku besar secara idempoten (D41-1).
 */
final class PostOpeningBalanceAction
{
    public function __construct(
        private readonly WmsLedgerPoster $poster,
    ) {}

    /**
     * @return array{
     *     warehouse_id: string|int,
     *     warehouse_code: string,
     *     total_value_idr: int,
     *     already_posted: bool
     * }
     */
    public function execute(string|int $warehouseId, ?int $actorUserId = null, ?string $idempotencyKey = null): array
    {
        $warehouse = Warehouse::findOrFail($warehouseId);
        $key = $idempotencyKey ?? "IDEM:WMS:OPENING:{$warehouse->code}";

        $rows = DB::table('wms_bin_stocks as bs')
            ->join('wms_bins as b', 'b.id', '=', 'bs.bin_id')
            ->join('wms_racks as r', 'r.id', '=', 'b.rack_id')
            ->join('wms_zones as z', 'z.id', '=', 'r.zone_id')
            ->where('z.warehouse_id', $warehouse->id)
            ->select('bs.product_id', 'bs.qty_base', 'bs.qty')
            ->get();

        $totalValueIdr = 0;
        foreach ($rows as $row) {
            $qtyBase = (int) ($row->qty_base ?: QuantityNormalizer::fromDecimalString((string) $row->qty));
            if ($qtyBase <= 0) {
                continue;
            }
            $unitCost = $this->resolveActiveUnitCost((int) $row->product_id, (string) $warehouse->id);
            $totalValueIdr += QuantityNormalizer::computeValueIdr($qtyBase, $unitCost);
        }

        $posted = $this->poster->postOpeningBalance(
            warehouseCode: (string) $warehouse->code,
            amountIdr: $totalValueIdr,
            referenceId: (string) $warehouse->code,
            idempotencyKey: $key,
        );

        return [
            'warehouse_id' => $warehouse->id,
            'warehouse_code' => (string) $warehouse->code,
            'total_value_idr' => $totalValueIdr,
            'already_posted' => ! $posted && $totalValueIdr > 0,
        ];
    }

    private function resolveActiveUnitCost(int $productId, string|int $warehouseId): int
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
