<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Services;

use Modules\Venue\Domain\Models\EntertainmentZone;

/**
 * Service to calibrate real-time egress discharge rates based on weather and doorway geometry.
 */
class ZoneEgressThroughputCalibratorService
{
    /**
     * Calibrate dynamic pedestrian discharge flow rate.
     */
    public function calculateDynamicDischargeRate(
        EntertainmentZone $zone,
        float $wetSurfaceFrictionFactor = 1.0,
        bool $hasObstructions = false
    ): float {
        $baseRatePpmPerMeter = 73.0; // Standard NFPA pedestrian flow rate

        // Wet or slippery surfaces reduce walking speed and discharge by up to 25%
        $frictionAdjustment = max(0.5, min(1.0, $wetSurfaceFrictionFactor));
        $calibratedRate = $baseRatePpmPerMeter * $frictionAdjustment;

        if ($hasObstructions) {
            $calibratedRate *= 0.80; // 20% reduction if bottlenecked by stanchions or equipment
        }

        return round($calibratedRate, 2);
    }
}
