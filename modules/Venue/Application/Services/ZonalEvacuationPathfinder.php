<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Services;

use SplPriorityQueue;

final class ZonalEvacuationPathfinder
{
    /**
     * Compute shortest safe evacuation path from a starting zone to the nearest perimeter assembly point.
     *
     * @param  array<string, list<array{to: string, distance_meters: int, is_blocked: bool}>>  $adjacencyGraph
     * @param  list<string>  $assemblyPoints
     * @param  list<string>  $lockedDownZones
     * @return array{
     *     start_zone: string,
     *     destination_assembly_point: ?string,
     *     path: list<string>,
     *     total_distance_meters: int,
     *     evacuation_safe: bool,
     *     warnings: list<string>
     * }
     */
    public function findSafestEgressPath(
        string $startZone,
        array $adjacencyGraph,
        array $assemblyPoints,
        array $lockedDownZones = []
    ): array {
        $distances = [];
        $previous = [];
        $queue = new SplPriorityQueue;

        foreach (array_keys($adjacencyGraph) as $node) {
            $distances[$node] = PHP_INT_MAX;
            $previous[$node] = null;
        }

        $distances[$startZone] = 0;
        $queue->insert($startZone, 0);

        $lockedSet = array_flip($lockedDownZones);

        while (! $queue->isEmpty()) {
            /** @var string $current */
            $current = $queue->extract();

            if (in_array($current, $assemblyPoints, true)) {
                // Reconstruct path
                $path = [];
                $curr = $current;
                while ($curr !== null) {
                    array_unshift($path, $curr);
                    $curr = $previous[$curr];
                }

                return [
                    'start_zone' => $startZone,
                    'destination_assembly_point' => $current,
                    'path' => $path,
                    'total_distance_meters' => $distances[$current],
                    'evacuation_safe' => true,
                    'warnings' => [],
                ];
            }

            foreach ($adjacencyGraph[$current] ?? [] as $edge) {
                $neighbor = $edge['to'];
                $dist = $edge['distance_meters'];

                // Hindari zona terblokir atau zona yang dilockdown
                if ($edge['is_blocked'] || isset($lockedSet[$neighbor])) {
                    continue;
                }

                $alt = $distances[$current] + $dist;
                if ($alt < ($distances[$neighbor] ?? PHP_INT_MAX)) {
                    $distances[$neighbor] = $alt;
                    $previous[$neighbor] = $current;
                    $queue->insert($neighbor, -$alt);
                }
            }
        }

        return [
            'start_zone' => $startZone,
            'destination_assembly_point' => null,
            'path' => [],
            'total_distance_meters' => 0,
            'evacuation_safe' => false,
            'warnings' => ["Rute evakuasi terputus dari zona {$startZone}. Buka gerbang darurat manual."],
        ];
    }
}
