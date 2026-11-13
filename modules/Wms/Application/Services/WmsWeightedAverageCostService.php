<?php

declare(strict_types=1);

namespace Modules\Wms\Application\Services;

use InvalidArgumentException;
use Modules\Wms\Domain\Support\QuantityNormalizer;

/**
 * Menghitung biaya rata-rata tertimbang (Moving Weighted Average) persediaan per produk & gudang.
 */
final class WmsWeightedAverageCostService
{
    /**
     * Menghitung biaya unit baru setelah penerimaan stok baru (integer murni, pembulatan ke bawah).
     *
     * @return array{
     *     new_qty_base: int,
     *     new_total_value_idr: int,
     *     new_unit_cost_idr: int,
     *     rounding_residual_idr: int
     * }
     */
    public function recalculateOnReceipt(
        int $existingQtyBase,
        int $existingUnitCostIdr,
        int $incomingQtyBase,
        int $incomingUnitCostIdr,
    ): array {
        if ($existingQtyBase < 0 || $existingUnitCostIdr < 0 || $incomingQtyBase < 0 || $incomingUnitCostIdr < 0) {
            throw new InvalidArgumentException('Kuantitas dan biaya satuan tidak boleh negatif.');
        }

        $existingValue = QuantityNormalizer::computeValueIdr($existingQtyBase, $existingUnitCostIdr);
        $incomingValue = QuantityNormalizer::computeValueIdr($incomingQtyBase, $incomingUnitCostIdr);

        $newQty = $existingQtyBase + $incomingQtyBase;
        $newTotalValue = $existingValue + $incomingValue;

        if ($newQty === 0) {
            return [
                'new_qty_base' => 0,
                'new_total_value_idr' => 0,
                'new_unit_cost_idr' => 0,
                'rounding_residual_idr' => 0,
            ];
        }

        $newUnitCost = intdiv($newTotalValue, $newQty);
        $residual = $newTotalValue - ($newQty * $newUnitCost);

        return [
            'new_qty_base' => $newQty,
            'new_total_value_idr' => $newTotalValue,
            'new_unit_cost_idr' => $newUnitCost,
            'rounding_residual_idr' => $residual,
        ];
    }
}
