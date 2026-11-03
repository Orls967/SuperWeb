<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Math;

use InvalidArgumentException;

/**
 * Traveling Salesperson Problem (TSP) Route Optimizer with exact Held-Karp and Heuristic 2-opt.
 *
 * Guarantees:
 * - n <= 12: Globally optimal Held-Karp dynamic programming O(n^2 * 2^n)
 * - n > 12: Nearest Neighbor + 2-opt with Prim 1-tree lower bound and reported gap BPS
 * - Strict determinism with ascending index tie-breaking
 * - Route certificate verification in production (PR2)
 *
 * Complexity:
 * - Time: O(n^2 * 2^n) for n <= 12; O(n^2 * iterations) for n > 12
 * - Space: O(n * 2^n) for n <= 12; O(n) for n > 12
 */
final class RouteOptimizer
{
    public const int EXACT_THRESHOLD = 12;

    /**
     * @param  array<int, array<int, int>>  $dist  Symmetric integer distance matrix
     *
     * @throws InvalidArgumentException
     */
    public static function optimize(array $dist): RouteResult
    {
        $n = count($dist);
        if ($n === 0) {
            throw new InvalidArgumentException('Distance matrix cannot be empty');
        }

        // Validate square and symmetric matrix
        for ($i = 0; $i < $n; $i++) {
            if (! isset($dist[$i]) || count($dist[$i]) !== $n) {
                throw new InvalidArgumentException("Distance matrix must be square of size {$n}x{$n}");
            }
            if ($dist[$i][$i] !== 0) {
                throw new InvalidArgumentException("Self-distance at [{$i}][{$i}] must be 0");
            }
            for ($j = 0; $j < $n; $j++) {
                $d = $dist[$i][$j];
                if ($d < 0) {
                    throw new InvalidArgumentException("Distance at [{$i}][{$j}] cannot be negative: {$d}");
                }
                if ($d !== $dist[$j][$i]) {
                    throw new InvalidArgumentException("Distance matrix must be symmetric: [{$i}][{$j}] != [{$j}][{$i}]");
                }
            }
        }

        if ($n === 1) {
            $cert = new RouteCertificate(1, [0, 0], 0, 0, 0, true, $dist);
            RouteCertificateVerifier::verify($cert);

            return new RouteResult([0, 0], 0, 0, 0, true, $cert);
        }

        if ($n <= self::EXACT_THRESHOLD) {
            return self::solveHeldKarp($dist, $n);
        }

        return self::solveHeuristic($dist, $n);
    }

    /**
     * Exact Held-Karp algorithm.
     *
     * @param  array<int, array<int, int>>  $dist
     */
    private static function solveHeldKarp(array $dist, int $n): RouteResult
    {
        $numMasks = 1 << $n;
        $inf = PHP_INT_MAX;

        // Group masks by popcount for topological ordering
        /** @var array<int, list<int>> $masksByPopcount */
        $masksByPopcount = array_fill(1, $n, []);
        for ($mask = 1; $mask < $numMasks; $mask++) {
            if (($mask & 1) !== 1) {
                continue; // Tour must start at 0
            }
            $pop = self::popcount($mask);
            $masksByPopcount[$pop][] = $mask;
        }

        /** @var array<int, array<int, int>> $dp */
        $dp = [];
        /** @var array<int, array<int, int>> $parent */
        $parent = [];

        $dp[1][0] = 0;

        for ($k = 1; $k < $n && $k > 0; $k++) {
            foreach ($masksByPopcount[$k] as $mask) {
                if (! isset($dp[$mask])) {
                    continue;
                }
                foreach ($dp[$mask] as $u => $currentCost) {
                    for ($v = 0; $v < $n && $v >= 0; $v++) {
                        if (($mask & (1 << $v)) === 0) {
                            $nextMask = $mask | (1 << $v);
                            $newCost = $currentCost + $dist[$u][$v];

                            if (! isset($dp[$nextMask][$v]) || $newCost < $dp[$nextMask][$v]) {
                                $dp[$nextMask][$v] = $newCost;
                                $parent[$nextMask][$v] = $u;
                            } elseif ($newCost === $dp[$nextMask][$v] && isset($parent[$nextMask][$v]) && $u < $parent[$nextMask][$v]) {
                                // Tie-break ascending index
                                $parent[$nextMask][$v] = $u;
                            }
                        }
                    }
                }
            }
        }

        $fullMask = $numMasks - 1;
        $bestCost = $inf;
        $lastNode = -1;

        for ($u = 1; $u < $n && $u > 0; $u++) {
            if (isset($dp[$fullMask][$u])) {
                $cost = $dp[$fullMask][$u] + $dist[$u][0];
                if ($cost < $bestCost || ($cost === $bestCost && $u < $lastNode)) {
                    $bestCost = $cost;
                    $lastNode = $u;
                }
            }
        }

        // Reconstruct path backwards from parent pointers
        $currMask = $fullMask;
        $currNode = $lastNode;
        $reversedPath = [];
        $reconstructSteps = 0;

        while ($currNode !== 0 && ++$reconstructSteps <= $n) {
            $reversedPath[] = $currNode;
            $prev = $parent[$currMask][$currNode] ?? 0;
            $currMask ^= (1 << $currNode);
            $currNode = $prev;
        }

        $tour = [0, ...array_reverse($reversedPath), 0];

        // Canonical orientation tie-breaker (PR3 Determinism): first step must be <= last step before 0
        if ($n > 2 && $tour[1] > $tour[$n - 1]) {
            $inner = array_slice($tour, 1, $n - 1);
            $tour = [0, ...array_reverse($inner), 0];
        }

        $cert = new RouteCertificate($n, $tour, $bestCost, $bestCost, 0, true, $dist);
        RouteCertificateVerifier::verify($cert);

        return new RouteResult($tour, $bestCost, $bestCost, 0, true, $cert);
    }

    /**
     * Nearest Neighbor + 2-opt with 1-tree Prim lower bound for n > 12.
     *
     * @param  array<int, array<int, int>>  $dist
     */
    private static function solveHeuristic(array $dist, int $n): RouteResult
    {
        $lowerBound = self::compute1TreeLowerBound($dist, $n);

        // Nearest neighbor construction starting at 0
        $visited = array_fill(0, $n, false);
        $tour = [0];
        $visited[0] = true;
        $curr = 0;

        for ($step = 1; $step < $n && $step > 0; $step++) {
            $next = -1;
            $minDist = PHP_INT_MAX;
            for ($v = 0; $v < $n && $v >= 0; $v++) {
                if (! $visited[$v]) {
                    $d = $dist[$curr][$v];
                    if ($d < $minDist || ($d === $minDist && ($next === -1 || $v < $next))) {
                        $minDist = $d;
                        $next = $v;
                    }
                }
            }
            $tour[] = $next;
            $visited[$next] = true;
            $curr = $next;
        }
        $tour[] = 0;

        // 2-opt local search optimization
        $improved = true;
        $maxSwaps = $n * 50;
        $swaps = 0;
        while ($improved && ++$swaps <= $maxSwaps) {
            $improved = false;
            for ($i = 1; $i < $n - 1; $i++) {
                for ($j = $i + 1; $j < $n; $j++) {
                    $u1 = $tour[$i - 1];
                    $v1 = $tour[$i];
                    $u2 = $tour[$j];
                    $v2 = $tour[$j + 1];

                    $currentDist = $dist[$u1][$v1] + $dist[$u2][$v2];
                    $newDist = $dist[$u1][$u2] + $dist[$v1][$v2];

                    if ($newDist < $currentDist) {
                        // Reverse tour[i..j]
                        $segment = array_slice($tour, $i, $j - $i + 1);
                        array_splice($tour, $i, $j - $i + 1, array_reverse($segment));
                        $improved = true;
                        break 2;
                    }
                }
            }
        }

        // Recompute actual cost
        $cost = 0;
        for ($i = 0; $i < $n; $i++) {
            $cost += $dist[$tour[$i]][$tour[$i + 1]];
        }

        $gapBps = ($lowerBound === 0)
            ? 0
            : intdiv(($cost - $lowerBound) * 10_000, $lowerBound);

        $cert = new RouteCertificate($n, $tour, $cost, $lowerBound, $gapBps, false, $dist);
        RouteCertificateVerifier::verify($cert);

        return new RouteResult($tour, $cost, $lowerBound, $gapBps, false, $cert);
    }

    /**
     * Computes the 1-tree MST lower bound using Prim's algorithm on vertices 1..n-1
     * plus the two cheapest edges connecting vertex 0 to 1..n-1.
     *
     * @param  array<int, array<int, int>>  $dist
     */
    public static function compute1TreeLowerBound(array $dist, int $n): int
    {
        if ($n < 3) {
            return ($n === 2) ? $dist[0][1] * 2 : 0;
        }

        // Prim MST on vertices 1..n-1
        $inMst = array_fill(1, $n - 1, false);
        $minEdge = array_fill(1, $n - 1, PHP_INT_MAX);

        $inMst[1] = true;
        for ($v = 2; $v < $n && $v >= 2; $v++) {
            $minEdge[$v] = $dist[1][$v];
        }

        $mstCost = 0;
        for ($step = 1; $step < $n - 1 && $step > 0; $step++) {
            $bestV = -1;
            $bestDist = PHP_INT_MAX;
            for ($v = 2; $v < $n && $v >= 2; $v++) {
                if (! $inMst[$v] && $minEdge[$v] < $bestDist) {
                    $bestDist = $minEdge[$v];
                    $bestV = $v;
                }
            }
            if ($bestV === -1) {
                break;
            }
            $mstCost += $bestDist;
            $inMst[$bestV] = true;

            for ($v = 2; $v < $n && $v >= 2; $v++) {
                if (! $inMst[$v] && $dist[$bestV][$v] < $minEdge[$v]) {
                    $minEdge[$v] = $dist[$bestV][$v];
                }
            }
        }

        // Two cheapest edges from vertex 0 to {1..n-1}
        $edgesFrom0 = [];
        for ($v = 1; $v < $n && $v >= 1; $v++) {
            $edgesFrom0[] = $dist[0][$v];
        }
        sort($edgesFrom0);

        return $mstCost + $edgesFrom0[0] + $edgesFrom0[1];
    }

    private static function popcount(int $mask): int
    {
        return substr_count(decbin($mask), '1');
    }
}
