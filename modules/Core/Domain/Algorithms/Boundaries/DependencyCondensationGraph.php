<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Algorithms\Boundaries;

/**
 * Topological ordering, critical path, and structural analysis on module condensation DAGs.
 *
 * Implements deterministic Kahn's topological sort, longest path (critical path) computation,
 * and fan-in/fan-out degree distribution.
 */
final class DependencyCondensationGraph
{
    /**
     * Compute topological sort and graph structural metrics.
     *
     * @param  list<string>  $nodes  Module names sorted alphabetically
     * @param  array<string, list<string>>  $adjacencyList  directed edges: u => [v1, v2, ...]
     * @return array{
     *     isDag: bool,
     *     topologicalOrder: list<string>,
     *     criticalPathLength: int,
     *     longestPath: list<string>,
     *     rootModules: list<string>,
     *     leafModules: list<string>,
     *     inDegrees: array<string, int>,
     *     outDegrees: array<string, int>
     * }
     */
    public function analyze(array $nodes, array $adjacencyList): array
    {
        // 1. Initialize in-degrees and out-degrees
        $inDegrees = [];
        $outDegrees = [];
        foreach ($nodes as $n) {
            $inDegrees[$n] = 0;
            $outDegrees[$n] = count($adjacencyList[$n] ?? []);
        }

        foreach ($nodes as $u) {
            foreach ($adjacencyList[$u] ?? [] as $v) {
                if (isset($inDegrees[$v])) {
                    $inDegrees[$v]++;
                }
            }
        }

        // 2. Kahn's Algorithm for Topological Sort using deterministic PriorityQueue / sorted array
        $zeroInDegree = [];
        foreach ($nodes as $n) {
            if ($inDegrees[$n] === 0) {
                $zeroInDegree[] = $n;
            }
        }
        sort($zeroInDegree, SORT_STRING);

        $rootModules = $zeroInDegree;
        $tempInDegrees = $inDegrees;
        $topologicalOrder = [];

        while ($zeroInDegree !== []) {
            $u = array_shift($zeroInDegree);
            $topologicalOrder[] = $u;

            $targets = $adjacencyList[$u] ?? [];
            sort($targets, SORT_STRING);

            foreach ($targets as $v) {
                if (isset($tempInDegrees[$v])) {
                    $tempInDegrees[$v]--;
                    if ($tempInDegrees[$v] === 0) {
                        $zeroInDegree[] = $v;
                        sort($zeroInDegree, SORT_STRING);
                    }
                }
            }
        }

        $isDag = count($topologicalOrder) === count($nodes);

        // 3. Find Leaf Modules (out-degree == 0)
        $leafModules = [];
        foreach ($nodes as $n) {
            if ($outDegrees[$n] === 0) {
                $leafModules[] = $n;
            }
        }
        sort($leafModules, SORT_STRING);

        // 4. Critical path (longest path) in DAG via dynamic programming over topological order
        $longestDist = [];
        $predecessor = [];
        foreach ($nodes as $n) {
            $longestDist[$n] = 0;
            $predecessor[$n] = null;
        }

        if ($isDag) {
            foreach ($topologicalOrder as $u) {
                $uDist = $longestDist[$u];
                foreach ($adjacencyList[$u] ?? [] as $v) {
                    if ($uDist + 1 > $longestDist[$v]) {
                        $longestDist[$v] = $uDist + 1;
                        $predecessor[$v] = $u;
                    }
                }
            }
        }

        // Find endpoint with maximum distance
        $maxDist = 0;
        $maxEndNode = $nodes[0] ?? '';
        foreach ($longestDist as $node => $dist) {
            if ($dist > $maxDist) {
                $maxDist = $dist;
                $maxEndNode = $node;
            }
        }

        // Reconstruct longest path backwards
        $longestPath = [];
        if ($maxDist > 0 && $maxEndNode !== '') {
            $curr = $maxEndNode;
            while ($curr !== null) {
                $longestPath[] = $curr;
                $curr = $predecessor[$curr] ?? null;
            }
            $longestPath = array_reverse($longestPath);
        }

        return [
            'isDag' => $isDag,
            'topologicalOrder' => $topologicalOrder,
            'criticalPathLength' => $maxDist,
            'longestPath' => $longestPath,
            'rootModules' => $rootModules,
            'leafModules' => $leafModules,
            'inDegrees' => $inDegrees,
            'outDegrees' => $outDegrees,
        ];
    }
}
