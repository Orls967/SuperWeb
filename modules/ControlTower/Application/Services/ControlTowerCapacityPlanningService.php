<?php

declare(strict_types=1);

namespace Modules\ControlTower\Application\Services;

use Modules\ControlTower\Domain\Services\CapacityBottleneckDetector;

final class ControlTowerCapacityPlanningService
{
    public function __construct(
        private readonly CapacityBottleneckDetector $detector,
    ) {}

    /**
     * @param  array<string, array{load_units: int, capacity_units: int}>  $workCenters
     * @return array{bottlenecks: list<array{work_center: string, utilization_bps: int, overload_units: int}>, requires_ctp_reroute: bool}
     */
    public function evaluatePlantCapacity(array $workCenters, int $thresholdBps = 9000): array
    {
        $bottlenecks = $this->detector->detect($workCenters, $thresholdBps);

        return [
            'bottlenecks' => $bottlenecks,
            'requires_ctp_reroute' => $bottlenecks !== [],
        ];
    }
}

