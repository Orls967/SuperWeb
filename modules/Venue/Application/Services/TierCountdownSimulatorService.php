<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Services;

use Modules\Venue\Domain\Algorithms\TierCountdownPricer;
use Modules\Venue\Domain\Models\EntertainmentEvent;

final class TierCountdownSimulatorService
{
    public function __construct(
        private readonly TierCountdownPricer $pricer
    ) {}

    /**
     * Run simulated multi-period price countdown trajectories for planning event ticketing revenue.
     *
     * @return array{
     *     event_code: string,
     *     floor_price_idr: int,
     *     ceiling_price_idr: int,
     *     simulation_points: list<array{
     *         days_until_event: int,
     *         tier_type: string,
     *         demand_ratio_bps: int,
     *         rain_probability_bps: int,
     *         calculated_price_idr: int,
     *         effective_discount_percent: float
     *     }>,
     *     projected_gross_revenue_idr: int
     * }
     */
    public function simulateCountdownCurves(EntertainmentEvent $event): array
    {
        $floor = (int) ($event->ticket_floor_price_idr ?? 250000);
        $ceiling = (int) ($event->ticket_ceiling_price_idr ?? 750000);
        $base = (int) ($event->current_ticket_price_idr ?? 450000);

        $scenarios = [
            ['days' => 45, 'tier' => 'EARLY_BIRD', 'demand' => 2000, 'rain' => 1000],
            ['days' => 30, 'tier' => 'EARLY_BIRD', 'demand' => 4500, 'rain' => 1500],
            ['days' => 20, 'tier' => 'GENERAL_ADMISSION', 'demand' => 5500, 'rain' => 2000],
            ['days' => 14, 'tier' => 'GENERAL_ADMISSION', 'demand' => 6500, 'rain' => 3000],
            ['days' => 7, 'tier' => 'GENERAL_ADMISSION', 'demand' => 8200, 'rain' => 2500],
            ['days' => 3, 'tier' => 'LAST_CHANCE', 'demand' => 9100, 'rain' => 4000],
            ['days' => 1, 'tier' => 'DOOR', 'demand' => 9500, 'rain' => 6500],
            ['days' => 0, 'tier' => 'DOOR', 'demand' => 9900, 'rain' => 7000],
        ];

        $points = [];
        $totalProjectedRevenue = 0;
        $ticketsPerPoint = max(10, (int) floor(($event->max_capacity ?? 1000) / count($scenarios)));

        foreach ($scenarios as $sc) {
            $price = $this->pricer->calculate(
                basePriceIdr: $base,
                tierType: $sc['tier'],
                demandRatioBps: $sc['demand'],
                rainProbabilityBps: $sc['rain'],
                floorPriceIdr: $floor,
                ceilingPriceIdr: $ceiling
            );

            $discount = $ceiling > 0 ? round((($ceiling - $price) / $ceiling) * 100, 1) : 0.0;
            $points[] = [
                'days_until_event' => $sc['days'],
                'tier_type' => $sc['tier'],
                'demand_ratio_bps' => $sc['demand'],
                'rain_probability_bps' => $sc['rain'],
                'calculated_price_idr' => $price,
                'effective_discount_percent' => $discount,
            ];

            $totalProjectedRevenue += ($price * $ticketsPerPoint);
        }

        return [
            'event_code' => $event->event_code,
            'floor_price_idr' => $floor,
            'ceiling_price_idr' => $ceiling,
            'simulation_points' => $points,
            'projected_gross_revenue_idr' => $totalProjectedRevenue,
        ];
    }
}
