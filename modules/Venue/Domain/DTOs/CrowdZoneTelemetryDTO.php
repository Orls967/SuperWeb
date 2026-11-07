<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\DTOs;

final readonly class CrowdZoneTelemetryDTO
{
    public function __construct(
        public int $zoneId,
        public string $zoneCode,
        public string $zoneName,
        public int $currentHeadcount,
        public int $predictedHeadcount15m,
        public int $capacityLimit,
        public int $occupancyRateBps,
        public int $densitySqmBps,
        public string $recommendedAction,
        public bool $gateRestricted,
        public string $svgHeatColor
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'zone_id' => $this->zoneId,
            'zone_code' => $this->zoneCode,
            'zone_name' => $this->zoneName,
            'current_headcount' => $this->currentHeadcount,
            'predicted_headcount_15m' => $this->predictedHeadcount15m,
            'capacity_limit' => $this->capacityLimit,
            'occupancy_rate_bps' => $this->occupancyRateBps,
            'density_sqm_bps' => $this->densitySqmBps,
            'recommended_action' => $this->recommendedAction,
            'gate_restricted' => $this->gateRestricted,
            'svg_heat_color' => $this->svgHeatColor,
        ];
    }
}
