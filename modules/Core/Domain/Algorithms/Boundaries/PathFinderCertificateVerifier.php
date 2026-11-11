<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Algorithms\Boundaries;

/**
 * Formal certificate verifier for ModuleDependencyPathFinder.
 *
 * Verifies mathematical properties:
 * 1. Path Endpoint Validity: Every path begins at source and terminates at target
 * 2. Edge Existence: Every consecutive pair (u, v) in a path is a valid edge in the graph
 * 3. Simplicity: Every path is a simple path (no repeated nodes)
 * 4. Optimality: The shortest path hop count is <= the hop count of any other path
 * 5. Bottleneck Invariant: Every identified bottleneck node appears in all discovered paths
 */
final class PathFinderCertificateVerifier
{
    /**
     * @param  array{
     *     source: string,
     *     target: string,
     *     pathCount: int,
     *     shortestPath: list<string>,
     *     shortestHops: int,
     *     allPaths: list<list<string>>,
     *     bottleneckNodes: list<string>
     * }  $report
     * @param  array<string, list<string>>  $adjacencyList
     */
    public function verify(array $report, array $adjacencyList): bool
    {
        $source = $report['source'];
        $target = $report['target'];
        $allPaths = $report['allPaths'];
        $shortest = $report['shortestPath'];

        // 1. If no paths exist, shortestPath must be empty and hops must be -1
        if ($allPaths === []) {
            return $shortest === [] && $report['shortestHops'] === -1;
        }

        // 2. Shortest path verification
        if ($shortest === [] || $shortest[0] !== $source || end($shortest) !== $target) {
            return false;
        }

        $shortestLen = count($shortest);
        if ($report['shortestHops'] !== $shortestLen - 1) {
            return false;
        }

        // 3. Verify each discovered path
        foreach ($allPaths as $path) {
            if ($path === [] || $path[0] !== $source || end($path) !== $target) {
                return false;
            }

            // Path length must be >= shortest path length
            if (count($path) < $shortestLen) {
                return false;
            }

            // Must be simple path (no duplicate nodes)
            if (count($path) !== count(array_unique($path))) {
                return false;
            }

            // Every consecutive edge must exist in graph
            for ($i = 0; $i < count($path) - 1; $i++) {
                $u = $path[$i];
                $v = $path[$i + 1];
                $neighbors = $adjacencyList[$u] ?? [];
                if (! in_array($v, $neighbors, true)) {
                    return false;
                }
            }
        }

        // 4. Verify bottleneck nodes
        foreach ($report['bottleneckNodes'] as $bn) {
            if ($bn === $source || $bn === $target) {
                continue;
            }

            foreach ($allPaths as $path) {
                if (! in_array($bn, $path, true)) {
                    return false;
                }
            }
        }

        return true;
    }
}
