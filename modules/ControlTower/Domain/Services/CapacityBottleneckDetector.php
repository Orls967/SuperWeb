<?php

declare(strict_types=1);

namespace Modules\ControlTower\Domain\Services;

final class CapacityBottleneckDetector
{
    /**
     * @param  array<string, array{load_units: int, capacity_units: int}>  $workCenters
     * @return list<array{work_center: string, utilization_bps: int, overload_units: int}>
     */
    public function detect(array $workCenters, int $thresholdBps = 9000): array
    {
        $bottlenecks = [];

        foreach ($workCenters as $name => $stats) {
            $cap = max(1, $stats['capacity_units']);
            $utilBps = intdiv($stats['load_units'] * 10_000, $cap);
            if ($utilBps >= $thresholdBps) {
                $bottlenecks[] = [
                    'work_center' => $name,
                    'utilization_bps' => $utilBps,
                    'overload_units' => max(0, $stats['load_units'] - $stats['capacity_units']),
                ];
            }
        }

        return $bottlenecks;
    }
}
