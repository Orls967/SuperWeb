<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\Actions;

use Modules\Pricing\Domain\Models\PriceWaterfallLog;

class EvaluateWaterfallPromoStackingAction
{
    /**
     * Evaluate stacked price waterfall discounts, enforcing margin guardrails.
     *
     * @return array{
     *     sku: string,
     *     base_hpp_idr: int,
     *     list_price_idr: int,
     *     promo_discount_idr: int,
     *     volume_discount_idr: int,
     *     cash_discount_idr: int,
     *     pocket_price_idr: int,
     *     margin_pct: float,
     *     waterfall_log: PriceWaterfallLog
     * }
     */
    public function execute(
        string $sku,
        string $channel,
        int $listPriceIdr,
        int $baseHppIdr,
        int $promoBps = 500,     // 5% promo discount
        int $volumeBps = 300,    // 3% volume discount
        int $cashDiscountBps = 200, // 2% cash discount
        float $minMarginThresholdPct = 15.0
    ): array {
        // Step 1: List price
        $priceAfterPromo = $listPriceIdr - (int) intdiv($listPriceIdr * $promoBps, 10000);
        $promoDiscountIdr = $listPriceIdr - $priceAfterPromo;

        // Step 2: Volume discount
        $volumeDiscountIdr = (int) intdiv($priceAfterPromo * $volumeBps, 10000);
        $priceAfterVolume = $priceAfterPromo - $volumeDiscountIdr;

        // Step 3: Cash discount
        $cashDiscountIdr = (int) intdiv($priceAfterVolume * $cashDiscountBps, 10000);
        $tentativePocketPrice = $priceAfterVolume - $cashDiscountIdr;

        // Step 4: Guardrail clamp against minimum margin floor
        $minAllowedPrice = (int) round($baseHppIdr * (1 + ($minMarginThresholdPct / 100.0)));
        $clampDeltaIdr = 0;
        $finalPocketPrice = $tentativePocketPrice;

        if ($finalPocketPrice < $minAllowedPrice) {
            $clampDeltaIdr = $minAllowedPrice - $finalPocketPrice;
            $finalPocketPrice = $minAllowedPrice;
        }

        $marginPct = $finalPocketPrice > 0
            ? round((($finalPocketPrice - $baseHppIdr) / $finalPocketPrice) * 100, 2)
            : 0.0;

        $log = PriceWaterfallLog::create([
            'sku' => $sku,
            'channel' => $channel,
            'base_hpp_idr' => $baseHppIdr,
            'demand_surge_delta_idr' => 0,
            'contract_override_delta_idr' => -($promoDiscountIdr + $volumeDiscountIdr + $cashDiscountIdr),
            'guardrail_clamp_delta_idr' => $clampDeltaIdr,
            'final_price_idr' => $finalPocketPrice,
            'applied_driver' => $clampDeltaIdr > 0 ? 'guardrail_floor_clamped' : 'waterfall_stacked_promo',
            'created_at' => now(),
        ]);

        return [
            'sku' => $sku,
            'base_hpp_idr' => $baseHppIdr,
            'list_price_idr' => $listPriceIdr,
            'promo_discount_idr' => $promoDiscountIdr,
            'volume_discount_idr' => $volumeDiscountIdr,
            'cash_discount_idr' => $cashDiscountIdr,
            'pocket_price_idr' => $finalPocketPrice,
            'margin_pct' => $marginPct,
            'waterfall_log' => $log,
        ];
    }
}
