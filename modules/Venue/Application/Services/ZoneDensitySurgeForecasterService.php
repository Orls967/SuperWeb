<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Services;

use Modules\Venue\Domain\Models\EntertainmentZone;

/**
 * Service to calculate real-time exponential smoothing and time-to-saturation
 * for venue crowd zones based on streaming telemetry.
 */
class ZoneDensitySurgeForecasterService
{
    /**
     * Smoothing factor alpha for exponential moving average.
     */
    public const ALPHA = 0.35;

    /**
     * Forecast time-to-saturation in minutes for a given zone.
     *
     * @param  array<int, int>  $historicalHeadcountsChronological
     * @return array{
     *     zone_id: int,
     *     current_headcount: int,
     *     capacity_limit: int,
     *     utilization_percent: float,
     *     velocity_people_per_min: float,
     *     estimated_minutes_to_capacity: float|null,
     *     forecast_rating: string,
     *     advisory: string
     * }
     */
    public function forecastSaturationTimeline(
        EntertainmentZone $zone,
        array $historicalHeadcountsChronological,
        int $capacityLimit
    ): array {
        $count = count($historicalHeadcountsChronological);
        $capacityLimit = max(10, $capacityLimit);

        if ($count < 2) {
            $current = $count === 1 ? $historicalHeadcountsChronological[0] : 0;
            $utilization = round(($current / $capacityLimit) * 100.0, 1);

            return [
                'zone_id' => $zone->id,
                'current_headcount' => $current,
                'capacity_limit' => $capacityLimit,
                'utilization_percent' => $utilization,
                'velocity_people_per_min' => 0.0,
                'estimated_minutes_to_capacity' => null,
                'forecast_rating' => 'INSUFFICIENT_TELEMETRY',
                'advisory' => 'AWAITING_FURTHER_STREAMING_SAMPLES',
            ];
        }

        $current = end($historicalHeadcountsChronological);
        $utilization = round(($current / $capacityLimit) * 100.0, 1);

        // Exponential smoothing velocity
        $emaVelocity = 0.0;
        for ($i = 1; $i < $count; $i++) {
            $delta = $historicalHeadcountsChronological[$i] - $historicalHeadcountsChronological[$i - 1];
            $emaVelocity = (self::ALPHA * $delta) + ((1.0 - self::ALPHA) * $emaVelocity);
        }

        $velocity = round($emaVelocity, 2);
        $remainingCapacity = max(0, $capacityLimit - $current);

        $minutesToCapacity = null;
        if ($velocity > 0.1) {
            $minutesToCapacity = round($remainingCapacity / $velocity, 1);
        }

        $rating = 'STABLE';
        $advisory = 'MONITOR_ROUTINELY';

        if ($utilization >= 95.0) {
            $rating = 'SATURATED_CRITICAL';
            $advisory = 'ENGAGE_IMMEDIATE_GATE_LOCKDOWN_AND_SLOW_ENTRY';
        } elseif ($minutesToCapacity !== null && $minutesToCapacity <= 10.0) {
            $rating = 'SURGE_IMMINENT';
            $advisory = 'ACTIVATE_SECONDARY_BYPASS_AND_RESTRICT_ENTRANCE';
        } elseif ($minutesToCapacity !== null && $minutesToCapacity <= 20.0) {
            $rating = 'SURGE_WATCH';
            $advisory = 'STAGE_SECURITY_AT_ZONE_THRESHOLD';
        }

        return [
            'zone_id' => $zone->id,
            'current_headcount' => $current,
            'capacity_limit' => $capacityLimit,
            'utilization_percent' => $utilization,
            'velocity_people_per_min' => $velocity,
            'estimated_minutes_to_capacity' => $minutesToCapacity,
            'forecast_rating' => $rating,
            'advisory' => $advisory,
        ];
    }
}
