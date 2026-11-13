<?php

declare(strict_types=1);

namespace Modules\Wms\Application\Actions;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Core\Contracts\ClockInterface;
use Modules\Wms\Domain\Models\UnitCost;
use Modules\Wms\Domain\Models\Warehouse;
use Modules\Wms\Domain\Support\QuantityNormalizer;

/**
 * Revaluasi harga pokok standar produk & posting selisih ke buku besar (D41-10 / D41-11).
 */
final class RevalueInventoryCostAction
{
    public function __construct(
        private readonly WmsLedgerPoster $poster,
        private readonly ClockInterface $clock,
    ) {}

    /**
     * @return array{
     *     product_id: int,
     *     warehouse_id: int,
     *     old_cost_idr: int,
     *     new_cost_idr: int,
     *     qty_base: int,
     *     delta_value_idr: int,
     *     direction: string
     * }
     */
    public function execute(
        int $productId,
        int $warehouseId,
        int $newCostIdr,
        string $method = 'std',
        ?int $actorUserId = null,
        ?string $idempotencyKey = null,
    ): array {
        if ($newCostIdr < 0) {
            throw new InvalidArgumentException('Biaya standar baru tidak boleh negatif.');
        }

        return DB::transaction(function () use ($productId, $warehouseId, $newCostIdr, $method, $actorUserId, $idempotencyKey): array {
            $warehouse = Warehouse::findOrFail($warehouseId);
            $now = $this->clock->now();

            $current = UnitCost::where('product_id', $productId)
                ->where('warehouse_id', $warehouseId)
                ->where('is_active', true)
                ->orderByDesc('effective_date')
                ->first();

            $oldCostIdr = (int) ($current?->cost_idr ?? 0);

            UnitCost::where('product_id', $productId)
                ->where('warehouse_id', $warehouseId)
                ->where('is_active', true)
                ->update(['is_active' => false]);

            $created = UnitCost::updateOrCreate(
                [
                    'product_id' => $productId,
                    'warehouse_id' => $warehouseId,
                    'effective_date' => $now->toDateString(),
                    'method' => $method,
                ],
                [
                    'cost_idr' => $newCostIdr,
                    'created_by_user_id' => $actorUserId,
                    'is_active' => true,
                ]
            );

            $qtyBase = $this->sumWarehouseProductQty($warehouseId, $productId);
            $diffPerUnit = $newCostIdr - $oldCostIdr;
            $deltaValueIdr = abs($diffPerUnit) * $qtyBase;
            $refId = "{$warehouse->code}-P{$productId}-{$created->id}";
            $direction = $diffPerUnit > 0 ? 'markup' : ($diffPerUnit < 0 ? 'markdown' : 'unchanged');

            if ($deltaValueIdr > 0) {
                if ($diffPerUnit > 0) {
                    $this->poster->postCostRevaluationMarkUp((string) $warehouse->code, $deltaValueIdr, $refId, $idempotencyKey);
                } else {
                    $this->poster->postCostRevaluationMarkDown((string) $warehouse->code, $deltaValueIdr, $refId, $idempotencyKey);
                }
            }

            return [
                'product_id' => $productId,
                'warehouse_id' => $warehouseId,
                'old_cost_idr' => $oldCostIdr,
                'new_cost_idr' => $newCostIdr,
                'qty_base' => $qtyBase,
                'delta_value_idr' => $deltaValueIdr,
                'direction' => $direction,
            ];
        });
    }

    private function sumWarehouseProductQty(int $warehouseId, int $productId): int
    {
        $rows = DB::table('wms_bin_stocks as bs')
            ->join('wms_bins as b', 'b.id', '=', 'bs.bin_id')
            ->join('wms_racks as r', 'r.id', '=', 'b.rack_id')
            ->join('wms_zones as z', 'z.id', '=', 'r.zone_id')
            ->where('z.warehouse_id', $warehouseId)
            ->where('bs.product_id', $productId)
            ->select('bs.qty_base', 'bs.qty')
            ->get();

        $sum = 0;
        foreach ($rows as $row) {
            $sum += (int) ($row->qty_base ?: QuantityNormalizer::fromDecimalString((string) $row->qty));
        }

        return $sum;
    }
}
