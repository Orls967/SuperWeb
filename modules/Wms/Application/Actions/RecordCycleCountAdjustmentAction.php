<?php

declare(strict_types=1);

namespace Modules\Wms\Application\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Modules\Core\Contracts\ClockInterface;
use Modules\Wms\Domain\Events\WarehouseStockAdjustedEvent;
use Modules\Wms\Domain\Models\BinStock;
use Modules\Wms\Domain\Models\Warehouse;
use Modules\Wms\Domain\Support\QuantityNormalizer;

/**
 * Mencatat penyesuaian cycle count (gain D41-6 / shrinkage D41-7) & mempublish event domain.
 */
final class RecordCycleCountAdjustmentAction
{
    public function __construct(
        private readonly WmsLedgerPoster $poster,
        private readonly ClockInterface $clock,
    ) {}

    /**
     * @return array{
     *     warehouse_id: int,
     *     product_id: int,
     *     variance_qty_base: int,
     *     variance_value_idr: int,
     *     kind: string
     * }
     */
    public function execute(
        int $warehouseId,
        int $binId,
        int $productId,
        int $countedQtyBase,
        int $unitCostIdr,
        string $cycleCountRef,
    ): array {
        return DB::transaction(function () use (
            $warehouseId,
            $binId,
            $productId,
            $countedQtyBase,
            $unitCostIdr,
            $cycleCountRef
        ): array {
            $warehouse = Warehouse::findOrFail($warehouseId);
            $stock = BinStock::firstOrNew([
                'bin_id' => $binId,
                'product_id' => $productId,
            ]);

            $systemQty = (int) ($stock->qty_base ?? 0);
            $variance = $countedQtyBase - $systemQty;

            $stock->qty_base = $countedQtyBase;
            $stock->qty = $countedQtyBase;
            $stock->stock_status = $stock->stock_status ?: 'available';
            $stock->save();

            $absValue = QuantityNormalizer::computeValueIdr(abs($variance), $unitCostIdr);
            $kind = $variance > 0 ? 'gain' : ($variance < 0 ? 'shrinkage' : 'matched');

            if ($absValue > 0) {
                if ($variance > 0) {
                    $this->poster->postCycleCountGain((string) $warehouse->code, $absValue, $cycleCountRef);
                } else {
                    $this->poster->postCycleCountShrinkage((string) $warehouse->code, $absValue, $cycleCountRef);
                }
            }

            Event::dispatch(new WarehouseStockAdjustedEvent(
                warehouseId: (int) $warehouse->id,
                productId: $productId,
                deltaQtyBase: $variance,
                deltaValueIdr: $variance >= 0 ? $absValue : -$absValue,
                reason: "cycle_count:{$cycleCountRef}",
                occurredAt: $this->clock->now()->toIso8601String(),
            ));

            return [
                'warehouse_id' => (int) $warehouse->id,
                'product_id' => $productId,
                'variance_qty_base' => $variance,
                'variance_value_idr' => $absValue,
                'kind' => $kind,
            ];
        });
    }
}
