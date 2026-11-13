<?php

declare(strict_types=1);

namespace Modules\ControlTower\Domain\Services;

final class SopFinancialBalancer
{
    /**
     * @return array{
     *     projected_revenue_idr: int,
     *     projected_cogs_idr: int,
     *     gross_margin_idr: int,
     *     working_capital_delta_idr: int
     * }
     */
    public function balance(
        int $demandQty,
        int $supplyQty,
        int $avgSellingPriceIdr,
        int $unitCostIdr,
        int $holdingCostPerUnitIdr,
    ): array {
        $fulfilledQty = min($demandQty, $supplyQty);
        $excessQty = max(0, $supplyQty - $demandQty);
        $revenue = $fulfilledQty * $avgSellingPriceIdr;
        $cogs = $fulfilledQty * $unitCostIdr;
        $holdingPenalty = $excessQty * $holdingCostPerUnitIdr;

        return [
            'projected_revenue_idr' => $revenue,
            'projected_cogs_idr' => $cogs,
            'gross_margin_idr' => $revenue - $cogs - $holdingPenalty,
            'working_capital_delta_idr' => -($excessQty * $unitCostIdr),
        ];
    }
}
