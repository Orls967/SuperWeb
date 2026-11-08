<?php

declare(strict_types=1);

namespace Modules\Port\Application\Actions;

use DomainException;
use Modules\Port\Application\Services\OceanFleetRouteOptimizationService;
use Modules\Port\Domain\Models\FleetSeaPassage;
use Modules\Port\Domain\Models\FleetVoyage;

/**
 * Action Pembentukan Lintasan Navigasi Orthodromik (Great Circle Passage Track).
 */
final class GenerateOrthodromicPassageTrackAction
{
    public function __construct(
        private readonly OceanFleetRouteOptimizationService $routeService
    ) {}

    public function execute(
        int $voyageId,
        string $passagePlanRef,
        float $departureLat,
        float $departureLon,
        float $arrivalLat,
        float $arrivalLon,
        int $waypointIntervals = 6,
        float $minUnderKeelClearanceM = 2.50
    ): FleetSeaPassage {
        $voyage = FleetVoyage::findOrFail($voyageId);

        if ($departureLat < -90.0 || $departureLat > 90.0 || $arrivalLat < -90.0 || $arrivalLat > 90.0) {
            throw new DomainException('Latitude harus berada di rentang -90.0 sampai 90.0 derajat.');
        }

        $route = $this->routeService->calculateGreatCircleRoute($departureLat, $departureLon, $arrivalLat, $arrivalLon);
        $waypoints = $this->routeService->generateWaypointsAlongTrack($departureLat, $departureLon, $arrivalLat, $arrivalLon, $waypointIntervals);

        return FleetSeaPassage::create([
            'voyage_id' => $voyage->id,
            'passage_plan_ref' => $passagePlanRef,
            'waypoints' => [
                'route_summary' => $route,
                'track_points' => $waypoints,
            ],
            'under_keel_clearance_min_m' => $minUnderKeelClearanceM,
            'ecdis_cross_track_limit_checked' => true,
            'marpol_special_area_transit' => false,
            'seca_transit' => false,
        ]);
    }
}
