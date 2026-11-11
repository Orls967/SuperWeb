<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Algorithms\Boundaries;

/**
 * Deterministic Warshall's Algorithm for Transitive Closure of Module Dependencies.
 *
 * Computes:
 * - Full Reachability Matrix: T[i][j] = true iff module i can reach module j via >= 1 hops
 * - Reflexive Self-Reachability: T[i][i] = true iff module i belongs to a cyclic dependency
 * - Reachability Density Permille: Ratio of reachable pairs to total possible pairs
 */
final class DependencyMatrixTransitiveClosure
{
    public function __construct(
        private readonly TransitiveClosureCertificateVerifier $verifier = new TransitiveClosureCertificateVerifier,
    ) {}

    /**
     * Compute transitive closure reachability matrix.
     *
     * @param  list<string>  $nodes  List of module names (ordered deterministically)
     * @param  array<string, list<string>>  $adjacencyList  Direct edges: u => [v]
     * @return array{
     *     nodes: list<string>,
     *     matrix: array<string, array<string, bool>>,
     *     cyclicModules: list<string>,
     *     reachablePairsCount: int,
     *     totalPossiblePairs: int,
     *     densityPermille: int
     * }
     */
    public function compute(array $nodes, array $adjacencyList): array
    {
        $n = count($nodes);
        sort($nodes, SORT_STRING);

        // 1. Initialize matrix: matrix[u][v] = true if direct edge (u, v) exists
        $matrix = [];
        foreach ($nodes as $u) {
            $matrix[$u] = [];
            foreach ($nodes as $v) {
                $matrix[$u][$v] = false;
            }
        }

        foreach ($nodes as $u) {
            foreach ($adjacencyList[$u] ?? [] as $v) {
                if (isset($matrix[$u][$v])) {
                    $matrix[$u][$v] = true;
                }
            }
        }

        // 2. Warshall's Algorithm: O(N^3) boolean matrix multiplication
        foreach ($nodes as $k) {
            foreach ($nodes as $i) {
                if ($matrix[$i][$k]) {
                    foreach ($nodes as $j) {
                        if ($matrix[$i][$j] || $matrix[$k][$j]) {
                            $matrix[$i][$j] = true;
                        }
                    }
                }
            }
        }

        // 3. Extract cyclic modules (nodes that can reach themselves: matrix[i][i] == true)
        $cyclicModules = [];
        foreach ($nodes as $node) {
            if ($matrix[$node][$node]) {
                $cyclicModules[] = $node;
            }
        }
        sort($cyclicModules, SORT_STRING);

        // 4. Calculate reachability density permille
        $reachableCount = 0;
        foreach ($nodes as $u) {
            foreach ($nodes as $v) {
                if ($matrix[$u][$v] && $u !== $v) {
                    $reachableCount++;
                }
            }
        }

        $possiblePairs = max(1, $n * ($n - 1));
        $densityPermille = (int) floor(($reachableCount * 1000) / $possiblePairs);
        $densityPermille = min(1000, $densityPermille);

        $report = [
            'nodes' => $nodes,
            'matrix' => $matrix,
            'cyclicModules' => $cyclicModules,
            'reachablePairsCount' => $reachableCount,
            'totalPossiblePairs' => $possiblePairs,
            'densityPermille' => $densityPermille,
        ];

        $this->verifier->verify($report, $adjacencyList);

        return $report;
    }
}

