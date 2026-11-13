<?php

declare(strict_types=1);

namespace Modules\Wms\Application\Actions;

use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Wms\Application\Services\ReceivingToleranceEvaluator;
use Modules\Wms\Domain\Models\BinStock;
use Modules\Wms\Domain\Models\ReceivingReceipt;
use Modules\Wms\Domain\Models\Warehouse;
use Modules\Wms\Domain\Support\QuantityNormalizer;

/**
 * Penerimaan inbound GRN beserta evaluasi toleransi (X41-1) & posting ledger D41-2.
 */
final class ReceiveInboundShipmentAction
{
    public function __construct(
        private readonly ReceivingToleranceEvaluator $evaluator,
        private readonly WmsLedgerPoster $poster,
    ) {}

    /**
     * @param  array{
     *     receipt_number: string,
     *     warehouse_id: int,
     *     bin_id: int,
     *     product_id: int,
     *     expected_qty_base: int,
     *     received_qty_base: int,
     *     unit_cost_idr: int,
     *     lot_number?: ?string,
     *     expiry_date?: ?string,
     *     approve_over_receipt?: bool,
     *     over_tolerance_bps?: int,
     *     short_tolerance_bps?: int,
     *     received_by_user_id?: ?int
     * }  $data
     * @return array{receipt: ReceivingReceipt, evaluation: array<string, mixed>, ledger_posted_idr: int}
     */
    public function execute(array $data): array
    {
        $eval = $this->evaluator->evaluate(
            expectedQtyBase: (int) $data['expected_qty_base'],
            receivedQtyBase: (int) $data['received_qty_base'],
            overToleranceBps: (int) ($data['over_tolerance_bps'] ?? 500),
            shortToleranceBps: (int) ($data['short_tolerance_bps'] ?? 500),
        );

        if ($eval['decision'] === ReceivingToleranceEvaluator::DECISION_OVER_RECEIPT && ! ($data['approve_over_receipt'] ?? false)) {
            throw new DomainException('Penerimaan melebihi batas toleransi over-receipt dan memerlukan persetujuan supervisor.');
        }

        return DB::transaction(function () use ($data, $eval): array {
            $warehouse = Warehouse::findOrFail((int) $data['warehouse_id']);
            $receivedQty = (int) $data['received_qty_base'];
            $unitCostIdr = (int) $data['unit_cost_idr'];

            $receipt = ReceivingReceipt::create([
                'receipt_number' => $data['receipt_number'],
                'warehouse_id' => $warehouse->id,
                'product_id' => (int) $data['product_id'],
                'expected_qty_base' => (int) $data['expected_qty_base'],
                'received_qty_base' => $receivedQty,
                'variance_bps' => $eval['variance_bps'],
                'tolerance_decision' => $eval['decision'],
                'lot_number' => $data['lot_number'] ?? null,
                'expiry_date' => $data['expiry_date'] ?? null,
                'status' => 'received',
                'received_by_user_id' => $data['received_by_user_id'] ?? null,
            ]);

            if ($receivedQty > 0) {
                $stock = BinStock::firstOrNew([
                    'bin_id' => (int) $data['bin_id'],
                    'product_id' => (int) $data['product_id'],
                    'lot_number' => $data['lot_number'] ?? null,
                ]);
                $currentBase = (int) ($stock->qty_base ?? 0);
                $newBase = $currentBase + $receivedQty;
                $stock->qty_base = $newBase;
                $stock->qty = $newBase;
                $stock->expiry_date = $data['expiry_date'] ?? $stock->expiry_date;
                $stock->stock_status = $stock->stock_status ?: 'available';
                $stock->save();
            }

            $valueIdr = QuantityNormalizer::computeValueIdr($receivedQty, $unitCostIdr);
            $this->poster->postInboundReceipt(
                warehouseCode: (string) $warehouse->code,
                amountIdr: $valueIdr,
                referenceId: (string) $receipt->receipt_number,
            );

            return [
                'receipt' => $receipt,
                'evaluation' => $eval,
                'ledger_posted_idr' => $valueIdr,
            ];
        });
    }
}
