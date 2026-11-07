<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\DTOs;

final class BarQueueMetricsDTO
{
    public function __construct(
        public readonly float $meanWpsScore,
        public readonly int $longestWaitSeconds,
        public readonly int $saturatedStationsCount,
        public readonly int $totalActiveStaff,
        public readonly string $healthRating
    ) {}

    public function requiresImmediateStaffIntervention(): bool
    {
        return $this->saturatedStationsCount > 0 || $this->healthRating === 'POOR';
    }

    public function toArray(): array
    {
        return [
            'mean_wps_score' => $this->meanWpsScore,
            'longest_wait_seconds' => $this->longestWaitSeconds,
            'saturated_stations_count' => $this->saturatedStationsCount,
            'total_active_staff' => $this->totalActiveStaff,
            'health_rating' => $this->healthRating,
            'requires_intervention' => $this->requiresImmediateStaffIntervention(),
        ];
    }
}
