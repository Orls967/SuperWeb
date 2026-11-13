<?php

declare(strict_types=1);

namespace Modules\Wms\Domain\Algorithms;

/**
 * Detektor siklus wait-for graph untuk mencegah gridlock antar robot di lorong gudang.
 */
final class TrafficDeadlockDetector
{
    /**
     * @param  array<string, string>  $waitForEdges  Peta robot_code => target_robot_code yang ditunggu
     * @return array{has_deadlock: bool, cycle_nodes: list<string>}
     */
    public function detectCycle(array $waitForEdges): array
    {
        $state = []; // 0/unset = unvisited, 1 = visiting, 2 = visited
        $parent = [];

        foreach (array_keys($waitForEdges) as $startNode) {
            if (($state[$startNode] ?? 0) === 2) {
                continue;
            }

            $current = $startNode;
            $path = [];

            while ($current !== null) {
                if (($state[$current] ?? 0) === 2) {
                    break;
                }

                if (($state[$current] ?? 0) === 1) {
                    $cycleStartIdx = array_search($current, $path, true);
                    $cycleNodes = $cycleStartIdx !== false
                        ? array_values(array_slice($path, $cycleStartIdx))
                        : [$current];

                    return [
                        'has_deadlock' => true,
                        'cycle_nodes' => $cycleNodes,
                    ];
                }

                $state[$current] = 1;
                $path[] = $current;
                $next = $waitForEdges[$current] ?? null;
                $parent[$current] = $next;
                $current = $next;
            }

            foreach ($path as $node) {
                $state[$node] = 2;
            }
        }

        return [
            'has_deadlock' => false,
            'cycle_nodes' => [],
        ];
    }
}
