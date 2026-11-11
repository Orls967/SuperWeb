<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Algorithms\Boundaries;

/**
 * Deterministic path finder between modules across the architecture dependency graph.
 *
 * Implements:
 * - All simple paths enumeration between source and target module (bounded depth DFS)
 * - Shortest dependency path (BFS)
 * - Articulation point and bottleneck identification along dependency routes
 */
final class ModuleDependencyPathFinder
{
    /**
     * Find all simple paths from $source to $target within $maxDepth hops.
     *
     * @param  array<string, list<string>>  $adjacencyList  directed graph
     * @param  int  $maxDepth  maximum path length in hops (defaults to 6)
     * @param  int  $maxPaths  maximum number of paths to return (defaults to 20)
     * @return array{
     *     source: string,
     *     target: string,
     *     pathCount: int,
     *     shortestPath: list<string>,
     *     shortestHops: int,
     *     allPaths: list<list<string>>,
     *     bottleneckNodes: list<string>
     * }
     */
    public function findPaths(
        string $source,
        string $target,
        array $adjacencyList,
        int $maxDepth = 6,
        int $maxPaths = 20
    ): array {
        if ($source === $target) {
            return [
                'source' => $source,
                'target' => $target,
                'pathCount' => 1,
                'shortestPath' => [$source],
                'shortestHops' => 0,
                'allPaths' => [[$source]],
                'bottleneckNodes' => [$source],
            ];
        }

        // 1. BFS for shortest path
        $shortestPath = $this->findShortestPath($source, $target, $adjacencyList);
        $shortestHops = $shortestPath !== [] ? count($shortestPath) - 1 : -1;

        // 2. DFS for all simple paths bounded by maxDepth and maxPaths
        $allPaths = [];
        $visited = [$source => true];
        $currentPath = [$source];

        $this->dfsPaths(
            $source,
            $target,
            $adjacencyList,
            $maxDepth,
            $maxPaths,
            $visited,
            $currentPath,
            $allPaths
        );

        // Sort all paths deterministically: by hop length ascending, then lexicographically
        usort($allPaths, static function (array $a, array $b): int {
            if (count($a) !== count($b)) {
                return count($a) <=> count($b);
            }

            return implode('->', $a) <=> implode('->', $b);
        });

        // 3. Find bottleneck nodes (nodes present in EVERY path from source to target)
        $bottlenecks = [];
        if ($allPaths !== []) {
            $candidateNodes = $allPaths[0];
            foreach ($candidateNodes as $candidate) {
                if ($candidate === $source || $candidate === $target) {
                    continue;
                }

                $presentInAll = true;
                foreach ($allPaths as $path) {
                    if (! in_array($candidate, $path, true)) {
                        $presentInAll = false;
                        break;
                    }
                }

                if ($presentInAll) {
                    $bottlenecks[] = $candidate;
                }
            }
        }

        sort($bottlenecks, SORT_STRING);

        return [
            'source' => $source,
            'target' => $target,
            'pathCount' => count($allPaths),
            'shortestPath' => $shortestPath,
            'shortestHops' => $shortestHops,
            'allPaths' => $allPaths,
            'bottleneckNodes' => $bottlenecks,
        ];
    }

    /**
     * @param  array<string, list<string>>  $adjacencyList
     * @return list<string>
     */
    private function findShortestPath(string $source, string $target, array $adjacencyList): array
    {
        $queue = [[$source]];
        $visited = [$source => true];

        while ($queue !== []) {
            $path = array_shift($queue);
            $last = end($path);

            if ($last === $target) {
                return $path;
            }

            $neighbors = $adjacencyList[$last] ?? [];
            sort($neighbors, SORT_STRING);

            foreach ($neighbors as $neighbor) {
                if (! isset($visited[$neighbor])) {
                    $visited[$neighbor] = true;
                    $queue[] = [...$path, $neighbor];
                }
            }
        }

        return [];
    }

    /**
     * @param  array<string, list<string>>  $adjacencyList
     * @param  array<string, bool>  $visited
     * @param  list<string>  $currentPath
     * @param  list<list<string>>  $allPaths
     */
    private function dfsPaths(
        string $current,
        string $target,
        array $adjacencyList,
        int $maxDepth,
        int $maxPaths,
        array &$visited,
        array &$currentPath,
        array &$allPaths
    ): void {
        if (count($allPaths) >= $maxPaths) {
            return;
        }

        if (count($currentPath) - 1 >= $maxDepth) {
            return;
        }

        $neighbors = $adjacencyList[$current] ?? [];
        sort($neighbors, SORT_STRING);

        foreach ($neighbors as $neighbor) {
            if ($neighbor === $target) {
                $allPaths[] = [...$currentPath, $target];
                if (count($allPaths) >= $maxPaths) {
                    return;
                }

                continue;
            }

            if (! isset($visited[$neighbor])) {
                $visited[$neighbor] = true;
                $currentPath[] = $neighbor;

                $this->dfsPaths(
                    $neighbor,
                    $target,
                    $adjacencyList,
                    $maxDepth,
                    $maxPaths,
                    $visited,
                    $currentPath,
                    $allPaths
                );

                array_pop($currentPath);
                unset($visited[$neighbor]);
            }
        }
    }
}
