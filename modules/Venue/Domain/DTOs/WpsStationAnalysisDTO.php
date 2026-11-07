<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\DTOs;

final readonly class WpsStationAnalysisDTO
{
    public function __construct(
        public int $stationNumber,
        public int $zoneId,
        public int $activeServers,
        public int $utilizationBps,
        public int $averageWaitMinutes,
        public int $wpsScore,
        public bool $reinforcementNeeded,
        public int $recommendedServersToAdd
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'station_number' => $this->stationNumber,
            'zone_id' => $this->zoneId,
            'active_servers' => $this->activeServers,
            'utilization_bps' => $this->utilizationBps,
            'average_wait_minutes' => $this->averageWaitMinutes,
            'wps_score' => $this->wpsScore,
            'reinforcement_needed' => $this->reinforcementNeeded,
            'recommended_servers_to_add' => $this->recommendedServersToAdd,
        ];
    }
}
