<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Services;

use Modules\Venue\Domain\Algorithms\Bundling\DynamicBundleInput;
use Modules\Venue\Domain\Algorithms\Bundling\DynamicBundlePricer;
use Modules\Venue\Domain\Algorithms\Bundling\DynamicBundleResult;

final class DynamicBundleMarginCalculator
{
    public function __construct(
        private readonly DynamicBundlePricer $pricer
    ) {}

    /**
     * Hitung analisis marjin paket bundling multi-komponen (tiket konser, hotel, shuttle, F&B).
     *
     * @param  array<int, array{name: string, type: string, cost_price_idr: int, retail_price_idr: int}>  $components
     * @return array{
     *   total_retail_price_idr: int,
     *   total_cost_price_idr: int,
     *   final_bundle_price_idr: int,
     *   gross_profit_idr: int,
     *   gross_margin_bps: int,
     *   customer_savings_idr: int,
     *   is_margin_healthy: bool,
     *   pricing_result: DynamicBundleResult
     * }
     */
    public function calculatePackageMargins(
        array $components,
        int $requestedDiscountBps,
        int $floorPriceIdr
    ): array {
        $totalRetail = 0;
        $totalCost = 0;

        foreach ($components as $comp) {
            $totalRetail += $comp['retail_price_idr'];
            $totalCost += $comp['cost_price_idr'];
        }

        $input = new DynamicBundleInput($totalRetail, $requestedDiscountBps, $floorPriceIdr);
        $result = $this->pricer->calculate($input);

        $bundlePrice = $result->bundlePriceIdr;
        $grossProfit = $bundlePrice - $totalCost;

        $grossMarginBps = $bundlePrice > 0
            ? (int) intdiv($grossProfit * 10000, $bundlePrice)
            : 0;

        // Marjin sehat bila laba kotor >= 15% dari harga jual bundle (1500 bps)
        $isMarginHealthy = $grossMarginBps >= 1500 && $grossProfit > 0;

        return [
            'total_retail_price_idr' => $totalRetail,
            'total_cost_price_idr' => $totalCost,
            'final_bundle_price_idr' => $bundlePrice,
            'gross_profit_idr' => $grossProfit,
            'gross_margin_bps' => $grossMarginBps,
            'customer_savings_idr' => $result->totalSavingsIdr,
            'is_margin_healthy' => $isMarginHealthy,
            'pricing_result' => $result,
        ];
    }
}
