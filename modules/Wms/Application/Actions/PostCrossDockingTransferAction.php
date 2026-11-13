<?php

declare(strict_types=1);

namespace Modules\Wms\Application\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Modules\Core\Contracts\ClockInterface;
use Modules\Wms\Domain\Events\WarehouseTransferReceivedEvent;
use Modules\Wms\Domain\Models\BinStock;
use Modules\Wms\Domain\Models\Warehouse;
use Modules\Wms\Domain\Support\QuantityNormalizer;

/**
 * Transfer antar-gudang & cross-docking (D41-4 keluar, D41-5 masuk, D41-9 transit loss).
 */
final class PostCrossDockingTransferAction
{
    public function __construct(
        private readonly WmsLedgerPoster $poster,
        private readonly ClockInterface $clock,
    ) {}

    /**
     * @return array{
     *     transfer_ref: string,
     *     shipped_qty_base: int,
     *     received_qty_base: int,
     *     lost_qty_base: int,
     *     shipped_value_idr: int,
     *     received_value_idr: int,
     *     lost_value_idr: int
     * }
     */
    public function execute(
        int $fromWarehouseId,
        int $fromBinId,
        int $toWarehouseId,
        int $toBinId,
        int $productId,
        int $shippedQtyBase,
        int $receivedQtyBase,
        int $unitCostIdr,
        string $transferRef,
    ): array {
        return DB::transaction(function () use (
            $fromWarehouseId,
            $fromBinId,
            $toWarehouseId,
            $toBinId,
            $productId,
            $shippedQtyBase,
            $receivedQtyBase,
            $unitCostIdr,
            $transferRef
        ): array {
            $origin = Warehouse::findOrFail($fromWarehouseId);
            $dest = Warehouse::findOrFail($toWarehouseId);

            $srcStock = BinStock::where('bin_id', $fromBinId)->where('product_id', $productId)->firstOrFail();
            $srcBase = (int) ($srcStock->qty_base ?? 0);
            $srcStock->qty_base = max(0, $srcBase - $shippedQtyBase);
            $srcStock->qty = $srcStock->qty_base;
            $srcStock->save();

            $dstStock = BinStock::firstOrNew(['bin_id' => $toBinId, 'product_id' => $productId]);
            $dstStock->qty_base = (int) ($dstStock->qty_base ?? 0) + $receivedQtyBase;
            $dstStock->qty = $dstStock->qty_base;
            $dstStock->stock_status = $dstStock->stock_status ?: 'available';
            $dstStock->save();

            $lostQty = max(0, $shippedQtyBase - $receivedQtyBase);
            $shippedValue = QuantityNormalizer::computeValueIdr($shippedQtyBase, $unitCostIdr);
            $receivedValue = QuantityNormalizer::computeValueIdr($receivedQtyBase, $unitCostIdr);
            $lostValue = QuantityNormalizer::computeValueIdr($lostQty, $unitCostIdr);

            $this->poster->postTransferOut((string) $origin->code, $shippedValue, $transferRef);
            $this->poster->postTransferIn((string) $dest->code, $receivedValue, $transferRef);
            if ($lostValue > 0) {
                $this->poster->postTransitLoss($lostValue, $transferRef);
            }

            Event::dispatch(new WarehouseTransferReceivedEvent(
                transferId: crc32($transferRef),
                fromWarehouseId: (int) $origin->id,
                toWarehouseId: (int) $dest->id,
                totalValueIdr: $receivedValue,
                occurredAt: $this->clock->now()->toIso8601String(),
            ));

            return [
                'transfer_ref' => $transferRef,
                'shipped_qty_base' => $shippedQtyBase,
                'received_qty_base' => $receivedQtyBase,
                'lost_qty_base' => $lostQty,
                'shipped_value_idr' => $shippedValue,
                'received_value_idr' => $receivedValue,
                'lost_value_idr' => $lostValue,
            ];
        });
    }
}
