<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Services;

use Modules\Venue\Domain\Models\EntertainmentEvent;
use Modules\Venue\Domain\Models\EntertainmentVenue;
use Modules\Venue\Domain\ValueObjects\CrowdEvacuationPlan;

/**
 * Discrete-event micro-simulation for emergency venue evacuation and throughput modeling.
 * Implements Fruin's Pedestrian Planning standards and NFPA 101 exit discharge rates.
 */
class VenueCrowdSimulationEngine
{
    /**
     * Standard egress discharge rate: persons per meter of clear width per minute (NFPA standard: 73 ppm/m).
     */
    public const DISCHARGE_RATE_PERSONS_PER_METER_PER_MIN = 73.0;

    /**
     * Run evacuation simulation for an entire venue under panic or controlled egress.
     *
     * @param  array<int, array{zone_id: int, occupants: int, clear_exit_width_meters: float, distance_to_safety_meters: float}>  $zoneParameters
     */
    public function simulateEvacuation(
        EntertainmentVenue $venue,
        EntertainmentEvent $event,
        array $zoneParameters
    ): CrowdEvacuationPlan {
        $totalEvacuees = 0;
        $maxClearanceTimeMinutes = 0.0;
        $zoneResults = [];
        $criticalBottlenecks = [];

        foreach ($zoneParameters as $param) {
            $occupants = (int) $param['occupants'];
            $exitWidth = max(1.0, (float) $param['clear_exit_width_meters']);
            $distance = max(5.0, (float) $param['distance_to_safety_meters']);
            $zoneId = (int) $param['zone_id'];

            $totalEvacuees += $occupants;

            // Flow rate in persons per minute through available exit width
            $flowRatePpm = $exitWidth * self::DISCHARGE_RATE_PERSONS_PER_METER_PER_MIN;

            // Egress traversal time (walking speed: 1.2 m/s = 72 m/min)
            $walkingSpeedMpm = 72.0;
            $transitTimeMinutes = $distance / $walkingSpeedMpm;

            // Queue clearance time at door chokepoint
            $queuingTimeMinutes = $flowRatePpm > 0 ? ($occupants / $flowRatePpm) : 999.0;

            // Total evacuation time for this zone
            $totalZoneEvacTimeMinutes = round($transitTimeMinutes + $queuingTimeMinutes, 2);

            if ($totalZoneEvacTimeMinutes > $maxClearanceTimeMinutes) {
                $maxClearanceTimeMinutes = $totalZoneEvacTimeMinutes;
            }

            $isBottleneck = $queuingTimeMinutes > 8.0;
            if ($isBottleneck) {
                $criticalBottlenecks[] = [
                    'zone_id' => $zoneId,
                    'occupants' => $occupants,
                    'available_width_m' => $exitWidth,
                    'estimated_queue_minutes' => $queuingTimeMinutes,
                    'recommendation' => 'DEPLOY_SECONDARY_EGRESS_ROUTER',
                ];
            }

            $zoneResults[$zoneId] = [
                'occupants' => $occupants,
                'flow_rate_ppm' => round($flowRatePpm, 1),
                'transit_time_min' => round($transitTimeMinutes, 2),
                'queuing_time_min' => round($queuingTimeMinutes, 2),
                'total_clearance_min' => $totalZoneEvacTimeMinutes,
                'is_bottleneck' => $isBottleneck,
            ];
        }

        // Regulatory benchmark: Venue must fully evacuate within 8 minutes (NFPA standard)
        $safetyComplianceStatus = $maxClearanceTimeMinutes <= 8.0 ? 'COMPLIANT' : 'NON_COMPLIANT_EXCEEDS_REGULATORY_CAP';

        return new CrowdEvacuationPlan(
            venueId: $venue->id,
            eventId: $event->id,
            totalEvacuees: $totalEvacuees,
            estimatedTotalMinutes: $maxClearanceTimeMinutes,
            complianceStatus: $safetyComplianceStatus,
            zoneSimulations: $zoneResults,
            bottlenecks: $criticalBottlenecks
        );
    }

    /**
     * Calculate optimal additional gate width needed to bring evacuation under safe threshold.
     */
    public function calculateRequiredGateExpansion(int $occupants, float $targetClearanceMinutes = 8.0): float
    {
        if ($occupants <= 0 || $targetClearanceMinutes <= 0.0) {
            return 0.0;
        }

        $requiredPpm = $occupants / $targetClearanceMinutes;
        $requiredWidthMeters = $requiredPpm / self::DISCHARGE_RATE_PERSONS_PER_METER_PER_MIN;

        return round(max(0.0, $requiredWidthMeters), 2);
    }
}
