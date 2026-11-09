<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Pipeline;

final class DeploymentTopologyPlanner
{
    /**
     * Determines multi-zone deployment pod replica counts and rollout pacing.
     *
     * @param  int  $targetTrafficRps  e.g. 5000 requests per second
     * @param  int  $podCapacityRps  e.g. 250 requests per second per pod
     * @return array{total_pods_required: int, zones_count: int, pods_per_zone: int, max_surge_pods: int, max_unavailable_pods: int}
     */
    public function planTopology(int $targetTrafficRps, int $podCapacityRps = 250): array
    {
        $safeCapacity = max(10, $podCapacityRps);
        $totalPods = (int) ceil($targetTrafficRps / $safeCapacity);
        // Minimum 3 pods across 3 availability zones
        $totalPods = max(3, $totalPods);

        $zonesCount = 3;
        $podsPerZone = (int) ceil($totalPods / $zonesCount);
        $adjustedTotal = $podsPerZone * $zonesCount;

        // Rolling update parameters: max surge 25%, max unavailable 0 (zero-downtime)
        $maxSurge = max(1, (int) ceil($adjustedTotal * 0.25));
        $maxUnavailable = 0;

        return [
            'total_pods_required' => $adjustedTotal,
            'zones_count' => $zonesCount,
            'pods_per_zone' => $podsPerZone,
            'max_surge_pods' => $maxSurge,
            'max_unavailable_pods' => $maxUnavailable,
        ];
    }
}
