<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Services;

use Modules\Venue\Domain\Models\EntertainmentEvent;

final class VipBottleSurgePricingService
{
    public const BASE_MINIMUM_SPEND_IDR = 5000000; // Rp 5.000.000
    public const PEAK_HOUR_MULTIPLIER_BPS = 12500;  // 125% (1.25x)
    public const SOLD_OUT_SURGE_BPS = 15000;        // 150% (1.50x)

    /**
     * Calculate dynamic minimum spend for VIP table bookings based on event parameters and demand.
     *
     * @return array{
     *     base_spend_idr: int,
     *     surge_multiplier_bps: int,
     *     final_minimum_spend_idr: int,
     *     required_deposit_idr: int,
     *     tier_name: string
     * }
     */
    public function calculateDynamicMinimumSpend(
        EntertainmentEvent $event,
        string $tableTier = 'VIP_SOFA',
        int $occupiedTables = 0,
        int $totalTables = 10
    ): array {
        $baseSpend = match ($tableTier) {
            'VVIP_CABANA' => 15000000,
            'VIP_SOFA' => 8000000,
            'HIGH_TOP_COCKTAIL' => 3000000,
            default => self::BASE_MINIMUM_SPEND_IDR,
        };

        $multiplierBps = 10000; // 100%

        // Factor 1: Event ticket sellout pressure
        if ($event->sold_tickets_count !== null && $event->max_capacity !== null && $event->max_capacity > 0) {
            $soldRatio = $event->sold_tickets_count / $event->max_capacity;
            if ($soldRatio >= 0.90) {
                $multiplierBps += 3000; // +30%
            } elseif ($soldRatio >= 0.70) {
                $multiplierBps += 1500; // +15%
            }
        }

        // Factor 2: VIP table scarcity
        if ($totalTables > 0) {
            $tableOccupancyRatio = $occupiedTables / $totalTables;
            if ($tableOccupancyRatio >= 0.80) {
                $multiplierBps += 2500; // +25%
            } elseif ($tableOccupancyRatio >= 0.50) {
                $multiplierBps += 1000; // +10%
            }
        }

        $calculatedSpend = (int) floor(($baseSpend * $multiplierBps) / 10000);
        // Bulatkan ke bawah ke Rp 100.000
        $finalSpend = (int) (floor($calculatedSpend / 100000) * 100000);

        // Deposit adalah 50% dari minimum spend
        $deposit = (int) floor($finalSpend * 0.50);

        return [
            'base_spend_idr' => $baseSpend,
            'surge_multiplier_bps' => $multiplierBps,
            'final_minimum_spend_idr' => $finalSpend,
            'required_deposit_idr' => $deposit,
            'tier_name' => $tableTier,
        ];
    }

    /**
     * Validate bottle price markup and minimum spend quota fulfillment.
     */
    public function evaluateBottleFulfillment(int $minimumSpendIdr, int $currentOrderSubtotalIdr): array
    {
        $shortfall = max(0, $minimumSpendIdr - $currentOrderSubtotalIdr);
        $isFulfilled = $shortfall === 0;
        $fulfillmentPercent = $minimumSpendIdr > 0
            ? min(100.0, round(($currentOrderSubtotalIdr / $minimumSpendIdr) * 100, 1))
            : 100.0;

        return [
            'minimum_spend_idr' => $minimumSpendIdr,
            'current_order_idr' => $currentOrderSubtotalIdr,
            'shortfall_idr' => $shortfall,
            'fulfillment_percent' => $fulfillmentPercent,
            'is_fulfilled' => $isFulfilled,
        ];
    }
}
