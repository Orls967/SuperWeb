<?php

declare(strict_types=1);

namespace Modules\Wms\Application\Services;

use Modules\Wms\Application\Actions\WmsLedgerPoster;
use Modules\Wms\Domain\Models\BinStock;
use Modules\Wms\Domain\Models\Warehouse;
use Modules\Wms\Domain\Services\ReturnGoodsInspectionService;
use Modules\Wms\Domain\Support\QuantityNormalizer;

/**
 * Orkestrasi disposisi barang retur (RMA) ke bin gudang & posting ledger terkait.
 */
final class WmsReturnDispositionWorkflowService
{
    public function __construct(
        private readonly ReturnGoodsInspectionService $inspectionService,
        private readonly WmsLedgerPoster $poster,
    ) {}

    /**
     * @return array{disposition: string, qty_processed: int, ledger_amount_idr: int}
     */
    public function processReturn(
        string|int $warehouseId,
        string|int $binId,
        int $productId,
        int $returnedQtyBase,
        int $unitCostIdr,
        string $rmaRef,
        bool $sealIntact,
        bool $packagingDamaged,
        int $remainingShelfLifeDays,
    ): array {
        $warehouse = Warehouse::findOrFail($warehouseId);
        $decision = $this->inspectionService->inspect(
            returnedQty: $returnedQtyBase,
            sealIntact: $sealIntact,
            packagingDamaged: $packagingDamaged,
            remainingShelfLifeDays: $remainingShelfLifeDays,
        );

        $valueIdr = QuantityNormalizer::computeValueIdr($returnedQtyBase, $unitCostIdr);

        if ($decision['disposition'] === ReturnGoodsInspectionService::DISPOSITION_RESTOCK) {
            $stock = BinStock::firstOrNew(['bin_id' => $binId, 'product_id' => $productId]);
            $stock->qty_base = (int) ($stock->qty_base ?? 0) + $returnedQtyBase;
            $stock->qty = $stock->qty_base;
            $stock->stock_status = 'available';
            $stock->save();

            $this->poster->postInboundReceipt((string) $warehouse->code, $valueIdr, "RMA-{$rmaRef}");
        } elseif ($decision['disposition'] === ReturnGoodsInspectionService::DISPOSITION_QUARANTINE) {
            $stock = BinStock::firstOrNew(['bin_id' => $binId, 'product_id' => $productId]);
            $stock->qty_base = (int) ($stock->qty_base ?? 0) + $returnedQtyBase;
            $stock->qty = $stock->qty_base;
            $stock->stock_status = 'quarantine';
            $stock->quarantine_reason = $decision['reason'];
            $stock->save();
        }

        return [
            'disposition' => $decision['disposition'],
            'qty_processed' => $returnedQtyBase,
            'ledger_amount_idr' => $decision['disposition'] === ReturnGoodsInspectionService::DISPOSITION_RESTOCK ? $valueIdr : 0,
        ];
    }
}
