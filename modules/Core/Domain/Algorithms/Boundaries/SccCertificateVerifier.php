<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Algorithms\Boundaries;

/**
 * Formal certificate verifier for Tarjan Strongly Connected Components.
 * Proves that components partition the graph and condensation is acyclic (R4-ALG-01).
 */
final class SccCertificateVerifier
{
    /**
     * Verify that components form a complete partition and condensation graph is a DAG.
     *
     * @param  list<list<string>>  $components
     * @param  array<int, list<int>>  $condensationDag
     * @param  list<string>  $originalNodes
     */
    public function verify(array $components, array $condensationDag, array $originalNodes): bool
    {
        // 1. Verify exact partition of original nodes
        $seen = [];
        $totalNodes = 0;
        foreach ($components as $comp) {
            if ($comp === []) {
                return false;
            }
            foreach ($comp as $node) {
                if (isset($seen[$node])) {
                    return false; // Duplicate node across components
                }
                $seen[$node] = true;
                $totalNodes++;
            }
        }

        $expectedNodes = array_unique($originalNodes);
        if ($totalNodes !== count($expectedNodes)) {
            return false;
        }

        foreach ($expectedNodes as $node) {
            if (! isset($seen[$node])) {
                return false; // Missing node
            }
        }

        // 2. Verify condensation graph is acyclic using Kahn's topological sort
        $inDegree = [];
        $numSccs = count($components);
        for ($i = 0; $i < $numSccs; $i++) {
            $inDegree[$i] = 0;
        }

        foreach ($condensationDag as $u => $edges) {
            foreach ($edges as $v) {
                if ($u === $v) {
                    return false; // Self-loop in condensation
                }
                if (! isset($inDegree[$v])) {
                    return false; // Invalid node reference
                }
                $inDegree[$v]++;
            }
        }

        $queue = [];
        for ($i = 0; $i < $numSccs; $i++) {
            if ($inDegree[$i] === 0) {
                $queue[] = $i;
            }
        }

        $visitedCount = 0;
        while ($queue !== []) {
            $curr = array_shift($queue);
            $visitedCount++;

            foreach ($condensationDag[$curr] ?? [] as $neighbor) {
                $inDegree[$neighbor]--;
                if ($inDegree[$neighbor] === 0) {
                    $queue[] = $neighbor;
                }
            }
        }

        return $visitedCount === $numSccs;
    }
}
