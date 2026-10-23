<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Actions;

use Modules\Shared\Application\BaseAction;

class ApplyDynamicBottleSurgePricingAction extends BaseAction
{
    /**
     * Compute dynamic VIP bottle service surge price based on crowd occupancy and peak hours.
     *
     * @return array{bottle_sku: string, base_price_idr: int, surge_multiplier: float, final_price_idr: int, surge_tier: string}
     */
    public function handle(
        string $bottleSku,
        int $basePriceIdr,
        float $venueOccupancyPct,
        int $hourOfDay
    ): array {
        $isPeakHour = ($hourOfDay >= 22 || $hourOfDay <= 3);

        [$surgeMultiplier, $surgeTier] = match (true) {
            $venueOccupancyPct >= 95.0 && $isPeakHour => [1.50, 'PEAK_SURGE_150'],
            $venueOccupancyPct >= 80.0 && $isPeakHour => [1.30, 'SURGE_130'],
            $venueOccupancyPct >= 80.0 => [1.15, 'MODERATE_SURGE_115'],
            $isPeakHour => [1.10, 'LATE_NIGHT_BASE_110'],
            default => [1.00, 'STANDARD_MENU'],
        };

        // Round to nearest 50,000 IDR
        $rawPrice = $basePriceIdr * $surgeMultiplier;
        $finalPriceIdr = (int) (round($rawPrice / 50000) * 50000);

        $this->audit(
            action: 'venue.bottle.surge_priced',
            auditable: null,
            oldValues: ['base_price_idr' => $basePriceIdr],
            newValues: [
                'bottle_sku' => $bottleSku,
                'surge_multiplier' => $surgeMultiplier,
                'final_price_idr' => $finalPriceIdr,
                'surge_tier' => $surgeTier,
            ],
            correlationId: "ven_surge_{$bottleSku}_".date('YmdH'),
            impactType: 'dynamic_bottle_pricing'
        );

        return [
            'bottle_sku' => $bottleSku,
            'base_price_idr' => $basePriceIdr,
            'surge_multiplier' => $surgeMultiplier,
            'final_price_idr' => $finalPriceIdr,
            'surge_tier' => $surgeTier,
        ];
    }
}
