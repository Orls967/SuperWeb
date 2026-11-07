<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

use InvalidArgumentException;

class HotelBanquetDynamicMarginPricer
{
    /**
     * Menghitung penawaran harga per pax optimal untuk mencapai target margin kotor yang ditetapkan manajemen hotel.
     *
     * @return array{target_margin_pct: float, calculated_hpp_per_pax_idr: int, recommended_pax_price_idr: int, net_profit_per_pax_idr: int, break_even_pax: int}
     */
    public function computeOptimalPaxPrice(
        int $foodCostPerPaxIdr,
        int $laborCostPerPaxIdr,
        int $fixedHallRentalCostIdr = 25000000,
        float $targetGrossMarginPct = 45.0
    ): array {
        if ($foodCostPerPaxIdr <= 0 || $laborCostPerPaxIdr <= 0) {
            throw new InvalidArgumentException('Biaya makanan dan tenaga kerja harus lebih dari nol.');
        }

        $variableCostPerPax = $foodCostPerPaxIdr + $laborCostPerPaxIdr;
        $marginDecimal = $targetGrossMarginPct / 100.0;

        if ($marginDecimal >= 1.0) {
            throw new InvalidArgumentException('Target margin kotor tidak boleh 100% atau lebih.');
        }

        // Harga Jual = Biaya Variabel / (1 - Target Margin)
        $recommendedPrice = (int) ceil($variableCostPerPax / (1.0 - $marginDecimal));
        $profitPerPax = $recommendedPrice - $variableCostPerPax;

        // Break-even pax = Fixed Costs / Profit per Pax
        $breakEvenPax = $profitPerPax > 0 ? (int) ceil($fixedHallRentalCostIdr / $profitPerPax) : 0;

        return [
            'target_margin_pct' => $targetGrossMarginPct,
            'calculated_hpp_per_pax_idr' => $variableCostPerPax,
            'recommended_pax_price_idr' => $recommendedPrice,
            'net_profit_per_pax_idr' => $profitPerPax,
            'break_even_pax' => $breakEvenPax,
        ];
    }
}
