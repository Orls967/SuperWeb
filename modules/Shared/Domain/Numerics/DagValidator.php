<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Numerics;

use InvalidArgumentException;
use UnexpectedValueException;

/**
 * Directed Acyclic Graph (DAG) Validator & Topological Engine.
 *
 * Implements:
 * 1. Cycle detection with deterministic cycle path certificate.
 * 2. Kahn's topological sort with deterministic lexical tie-breaking.
 * 3. Transitive closure computation for prerequisite chains.
 *
 * Complexity: O(V + E) where V is the number of vertices and E is the number of edges.
 */
final class DagValidator
{
    /**
     * Finds a cycle in a directed graph.
     *
     * @param  array<string|int, list<string|int>>  $adjList  Node => list of target nodes (u -> v)
     * @return list<string>|null Cycle path e.g. ['A', 'B', 'C', 'A'] or null if acyclic
     */
    public static function findCycle(array $adjList): ?array
    {
        $adj = self::normalizeAdjList($adjList);
        $nodes = array_keys($adj);
        sort($nodes);

        $color = []; // 0 = white, 1 = gray, 2 = black
        $parent = [];
        foreach ($nodes as $node) {
            $color[$node] = 0;
            $parent[$node] = null;
        }

        foreach ($nodes as $node) {
            if ($color[$node] === 0) {
                $cycle = self::dfsCycle($node, $adj, $color, $parent);
                if ($cycle !== null) {
                    return $cycle;
                }
            }
        }

        return null;
    }

    /**
     * Asserts that the graph is acyclic. Throws UnexpectedValueException with the cycle path if not.
     *
     * @param  array<string|int, list<string|int>>  $adjList
     */
    public static function assertAcyclic(array $adjList): void
    {
        $cycle = self::findCycle($adjList);
        if ($cycle !== null) {
            throw new UnexpectedValueException('Cycle detected in graph: '.implode(' -> ', $cycle));
        }
    }

    /**
     * Kahn's algorithm for deterministic topological sort.
     *
     * @param  array<string|int, list<string|int>>  $adjList  u -> v edges
     * @return list<string>
     */
    public static function topologicalSort(array $adjList): array
    {
        $adj = self::normalizeAdjList($adjList);
        $nodes = array_keys($adj);
        sort($nodes);

        // Compute in-degrees
        $inDegree = array_fill_keys($nodes, 0);
        foreach ($adj as $u => $neighbors) {
            foreach ($neighbors as $v) {
                $inDegree[$v]++;
            }
        }

        // Min-priority queue (sorted array) of nodes with in-degree 0
        $queue = [];
        foreach ($nodes as $node) {
            if ($inDegree[$node] === 0) {
                $queue[] = $node;
            }
        }
        sort($queue);

        $order = [];
        while (! empty($queue)) {
            $u = array_shift($queue);
            $order[] = $u;

            foreach ($adj[$u] as $v) {
                $inDegree[$v]--;
                if ($inDegree[$v] === 0) {
                    $queue[] = $v;
                    sort($queue); // Preserve deterministic lexical tie-breaking
                }
            }
        }

        if (count($order) !== count($nodes)) {
            $cycle = self::findCycle($adjList);
            $cycleStr = $cycle !== null ? implode(' -> ', $cycle) : 'unknown cycle';
            throw new UnexpectedValueException("Graph contains a cycle and cannot be topologically sorted: {$cycleStr}");
        }

        return $order;
    }

    /**
     * Computes the transitive closure of prerequisites for a given target node.
     *
     * @param  array<string|int, list<string|int>>  $prereqMap  node => direct prerequisites
     * @return list<string> Sorted list of all transitive prerequisites
     */
    public static function transitivePrerequisites(string|int $targetNode, array $prereqMap): array
    {
        $target = (string) $targetNode;
        $normalizedMap = self::normalizeAdjList($prereqMap);

        if (! isset($normalizedMap[$target])) {
            $normalizedMap[$target] = [];
        }

        $visited = [];
        $visiting = []; // Path detection for cycles

        self::dfsPrerequisites($target, $normalizedMap, $visited, $visiting);

        // Remove target itself from prerequisites
        unset($visited[$target]);

        $result = array_keys($visited);
        sort($result);

        return $result;
    }

    /**
     * Generates a DAG verification certificate.
     *
     * @param  array<string|int, list<string|int>>  $adjList
     * @return array{
     *     is_acyclic: bool,
     *     nodes_count: int,
     *     edges_count: int,
     *     topological_order: list<string>|null,
     *     cycle_path: list<string>|null
     * }
     */
    public static function certificate(array $adjList): array
    {
        $cycle = self::findCycle($adjList);
        $nodes = array_keys(self::normalizeAdjList($adjList));
        $edgeCount = 0;
        foreach (self::normalizeAdjList($adjList) as $neighbors) {
            $edgeCount += count($neighbors);
        }

        if ($cycle !== null) {
            $cert = [
                'is_acyclic' => false,
                'nodes_count' => count($nodes),
                'edges_count' => $edgeCount,
                'topological_order' => null,
                'cycle_path' => $cycle,
            ];
        } else {
            $cert = [
                'is_acyclic' => true,
                'nodes_count' => count($nodes),
                'edges_count' => $edgeCount,
                'topological_order' => self::topologicalSort($adjList),
                'cycle_path' => null,
            ];
        }

        DagValidatorVerifier::verifyCertificate($cert, $adjList);

        return $cert;
    }

    /**
     * @param  array<string|int, list<string|int>>  $adjList
     * @return array<string, list<string>>
     */
    public static function normalizeAdjList(array $adjList): array
    {
        $normalized = [];
        $allNodes = [];

        foreach ($adjList as $u => $neighbors) {
            if (! is_array($neighbors)) {
                throw new InvalidArgumentException("Neighbors for node '{$u}' must be a list.");
            }
            $uKey = (string) $u;
            $allNodes[$uKey] = true;
            $cleanNeighbors = [];
            foreach ($neighbors as $v) {
                $vKey = (string) $v;
                $cleanNeighbors[] = $vKey;
                $allNodes[$vKey] = true;
            }
            $normalized[$uKey] = array_values(array_unique($cleanNeighbors));
            sort($normalized[$uKey]);
        }

        foreach (array_keys($allNodes) as $nodeKey) {
            if (! isset($normalized[$nodeKey])) {
                $normalized[$nodeKey] = [];
            }
        }

        ksort($normalized);

        return $normalized;
    }

    /**
     * @param  array<string, list<string>>  $adj
     * @param  array<string, int>  $color
     * @param  array<string, string|null>  $parent
     * @return list<string>|null
     */
    private static function dfsCycle(string $u, array $adj, array &$color, array &$parent): ?array
    {
        $color[$u] = 1; // Gray

        foreach ($adj[$u] as $v) {
            if ($color[$v] === 1) {
                // Back-edge found: cycle exists from v to u, then v!
                $cycle = [$v];
                $curr = $u;
                while ($curr !== $v && $curr !== null) {
                    $cycle[] = $curr;
                    $curr = $parent[$curr] ?? null;
                }
                $cycle[] = $v;

                return array_reverse($cycle);
            }

            if ($color[$v] === 0) {
                $parent[$v] = $u;
                $cycle = self::dfsCycle($v, $adj, $color, $parent);
                if ($cycle !== null) {
                    return $cycle;
                }
            }
        }

        $color[$u] = 2; // Black

        return null;
    }

    /**
     * @param  array<string, list<string>>  $map
     * @param  array<string, bool>  $visited
     * @param  array<string, bool>  $visiting
     */
    private static function dfsPrerequisites(string $node, array $map, array &$visited, array &$visiting): void
    {
        if (isset($visiting[$node])) {
            throw new UnexpectedValueException("Cycle detected in prerequisite chain involving node '{$node}'.");
        }
        if (isset($visited[$node])) {
            return;
        }

        $visiting[$node] = true;

        foreach ($map[$node] ?? [] as $prereq) {
            self::dfsPrerequisites($prereq, $map, $visited, $visiting);
        }

        unset($visiting[$node]);
        $visited[$node] = true;
    }
}
