<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Algorithms\Boundaries;

/**
 * Formal certificate verifier for Kahn's topological sort and DAG condensation.
 *
 * Proves that for every directed edge (u, v) in the graph, index(u) < index(v)
 * in the produced topological ordering.
 */
final class TopologicalCertificateVerifier
{
    /**
     * @param  list<string>  $nodes
     * @param  array<string, list<string>>  $adjacencyList
     * @param  list<string>  $topologicalOrder
     */
    public function verify(array $nodes, array $adjacencyList, array $topologicalOrder): bool
    {
        // 1. Order must contain exactly the same nodes without duplicates
        if (count($topologicalOrder) !== count($nodes)) {
            return false;
        }

        $positions = [];
        foreach ($topologicalOrder as $index => $node) {
            if (isset($positions[$node])) {
                return false; // duplicate found
            }
            $positions[$node] = $index;
        }

        foreach ($nodes as $n) {
            if (! isset($positions[$n])) {
                return false; // missing node
            }
        }

        // 2. For every directed edge u -> v, u must precede v in the ordering
        foreach ($nodes as $u) {
            $uPos = $positions[$u];
            foreach ($adjacencyList[$u] ?? [] as $v) {
                if (! isset($positions[$v])) {
                    continue;
                }
                $vPos = $positions[$v];
                if ($uPos >= $vPos) {
                    return false; // topological order violated
                }
            }
        }

        return true;
    }
}

