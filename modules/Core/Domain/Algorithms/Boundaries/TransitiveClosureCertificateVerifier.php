<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Algorithms\Boundaries;

/**
 * Formal certificate verifier for Warshall's Transitive Closure Algorithm.
 *
 * Verifies:
 * 1. Base Invariant: If (u, v) is in adjacency list, then matrix[u][v] == true
 * 2. Transitive Closure Invariant: If matrix[u][k] && matrix[k][v], then matrix[u][v] == true
 * 3. Self-Reachability Invariant: cyclicModules contains node u iff matrix[u][u] == true
 * 4. Density Bounds: densityPermille in [0, 1000]
 */
final class TransitiveClosureCertificateVerifier
{
    /**
     * @param  array{
     *     nodes: list<string>,
     *     matrix: array<string, array<string, bool>>,
     *     cyclicModules: list<string>,
     *     reachablePairsCount: int,
     *     totalPossiblePairs: int,
     *     densityPermille: int
     * }  $report
     * @param  array<string, list<string>>  $adjacencyList
     */
    public function verify(array $report, array $adjacencyList): bool
    {
        $nodes = $report['nodes'];
        $matrix = $report['matrix'];

        // 1. Direct edge check
        foreach ($nodes as $u) {
            foreach ($adjacencyList[$u] ?? [] as $v) {
                if (! ($matrix[$u][$v] ?? false)) {
                    return false; // Direct edge missing from transitive closure
                }
            }
        }

        // 2. Transitivity check (sample check across nodes)
        foreach ($nodes as $u) {
            foreach ($nodes as $k) {
                if ($matrix[$u][$k] ?? false) {
                    foreach ($nodes as $v) {
                        if ($matrix[$k][$v] ?? false) {
                            if (! ($matrix[$u][$v] ?? false)) {
                                return false; // Transitivity violated
                            }
                        }
                    }
                }
            }
        }

        // 3. Cyclic modules invariant
        foreach ($nodes as $node) {
            $isSelfReachable = $matrix[$node][$node] ?? false;
            $inCyclicList = in_array($node, $report['cyclicModules'], true);
            if ($isSelfReachable !== $inCyclicList) {
                return false;
            }
        }

        // 4. Density bounds check
        if ($report['densityPermille'] < 0 || $report['densityPermille'] > 1000) {
            return false;
        }

        return true;
    }
}

