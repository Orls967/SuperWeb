<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\DTOs;

final class IncidentEgressRouteDTO
{
    /**
     * @param  array<int, string>  $intermediateWaypoints
     */
    public function __construct(
        public readonly int $sourceZoneId,
        public readonly string $sourceZoneName,
        public readonly string $designatedExitGate,
        public readonly float $egressDistanceMeters,
        public readonly int $estimatedTransitSeconds,
        public readonly string $routeSafetyStatus,
        public readonly array $intermediateWaypoints
    ) {}

    public function toArray(): array
    {
        return [
            'source_zone_id' => $this->sourceZoneId,
            'source_zone_name' => $this->sourceZoneName,
            'designated_exit_gate' => $this->designatedExitGate,
            'egress_distance_meters' => $this->egressDistanceMeters,
            'estimated_transit_seconds' => $this->estimatedTransitSeconds,
            'route_safety_status' => $this->routeSafetyStatus,
            'waypoints' => $this->intermediateWaypoints,
        ];
    }
}
