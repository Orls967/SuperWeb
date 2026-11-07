<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Services;

use Modules\Venue\Domain\Algorithms\CrowdSafety\CrowdDensitySurgePredictor;
use Modules\Venue\Domain\DTOs\CrowdZoneTelemetryDTO;
use Modules\Venue\Domain\Models\EntertainmentEvent;
use Modules\Venue\Domain\Models\EntertainmentZone;
use Modules\Venue\Domain\ValueObjects\OpticalFlowVector;

/**
 * Service to ingest IoT sensor streams (LiDAR, overhead stereoscopic cameras)
 * and calculate localized optical flow vectors and crowd velocity.
 */
class CrowdDensityTelemetryCollectorService
{
    public const CRITICAL_DENSITY_PER_SQM = 4.5;

    public const TURBULENT_FLOW_THRESHOLD = 0.75;

    public function __construct(
        protected CrowdDensitySurgePredictor $surgePredictor
    ) {}

    /**
     * Ingest and evaluate optical flow and density sensor frame for a zone.
     *
     * @param  array<int, array{x: float, y: float, vx: float, vy: float}>  $flowPoints
     * @return array{
     *     zone_id: int,
     *     density_sqm: float,
     *     flow_turbulence: float,
     *     mean_velocity_mps: float,
     *     predicted_surge_15m: bool,
     *     recommended_action: string,
     *     optical_flow: OpticalFlowVector
     * }
     */
    public function ingestZoneOpticalFlow(
        EntertainmentZone $zone,
        EntertainmentEvent $event,
        array $flowPoints,
        int $detectedHeadcount
    ): array {
        $zoneAreaSqm = max(20.0, (float) ($zone->capacity_limit * 0.8)); // standard area estimate
        $densitySqm = round($detectedHeadcount / $zoneAreaSqm, 2);

        $totalVx = 0.0;
        $totalVy = 0.0;
        $count = count($flowPoints);

        if ($count === 0) {
            $flowObj = new OpticalFlowVector(0.0, 0.0, 0.0, 'STATIC_OR_OFFLINE');

            return [
                'zone_id' => $zone->id,
                'density_sqm' => $densitySqm,
                'flow_turbulence' => 0.0,
                'mean_velocity_mps' => 0.0,
                'predicted_surge_15m' => false,
                'recommended_action' => 'NORMAL',
                'optical_flow' => $flowObj,
            ];
        }

        foreach ($flowPoints as $p) {
            $totalVx += (float) $p['vx'];
            $totalVy += (float) $p['vy'];
        }

        $meanVx = $totalVx / $count;
        $meanVy = $totalVy / $count;
        $meanSpeed = sqrt(($meanVx * $meanVx) + ($meanVy * $meanVy));

        // Calculate variance / turbulence
        $varianceSum = 0.0;
        foreach ($flowPoints as $p) {
            $dx = (float) $p['vx'] - $meanVx;
            $dy = (float) $p['vy'] - $meanVy;
            $varianceSum += ($dx * $dx) + ($dy * $dy);
        }
        $turbulence = round($varianceSum / $count, 3);

        // Run through certified surge predictor V14
        $telemetry = new CrowdZoneTelemetryDTO(
            zoneId: $zone->id,
            currentDensitySqm: $densitySqm,
            recentIngressRatePerMinute: 45.0,
            recentEgressRatePerMinute: 15.0,
            weatherFactor: 1.0,
            venueMaxCapacity: (int) $zone->capacity_limit,
            currentHeadcount: $detectedHeadcount
        );

        $prediction = $this->surgePredictor->predictSurge($telemetry);
        $surgePredicted = (bool) $prediction['surge_predicted_15m'];

        $flowStatus = 'LAMINAR';
        if ($turbulence > self::TURBULENT_FLOW_THRESHOLD && $densitySqm > 3.0) {
            $flowStatus = 'TURBULENT_CROWD_CRUSH_PRECURSOR';
        } elseif ($meanSpeed < 0.2 && $densitySqm > 3.5) {
            $flowStatus = 'STAGNANT_CHOKEPOINT';
        }

        $opticalFlow = new OpticalFlowVector(
            meanVelocityX: round($meanVx, 2),
            meanVelocityY: round($meanVy, 2),
            turbulenceIndex: $turbulence,
            flowRegime: $flowStatus
        );

        $action = (string) $prediction['recommended_action'];
        if ($flowStatus === 'TURBULENT_CROWD_CRUSH_PRECURSOR') {
            $action = 'DISPATCH_EMERGENCY_SECURITY_OPEN_BYPASS_EGRESS';
        }

        return [
            'zone_id' => $zone->id,
            'density_sqm' => $densitySqm,
            'flow_turbulence' => $turbulence,
            'mean_velocity_mps' => round($meanSpeed, 2),
            'predicted_surge_15m' => $surgePredicted,
            'recommended_action' => $action,
            'optical_flow' => $opticalFlow,
        ];
    }
}
