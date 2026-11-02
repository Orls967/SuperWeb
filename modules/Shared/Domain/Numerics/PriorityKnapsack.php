<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Numerics;

use InvalidArgumentException;

/**
 * Priority Knapsack Solver for Microgrid Islanding load shedding.
 *
 * Solves 0/1 knapsack per lexicographically ordered priority group (e.g. Hospital > Factory > Mall)
 * to maximize served kW using remaining grid capacity.
 *
 * Complexity: O(G * N * C) where G is groups, N is loads per group, C is capacity kW.
 */
final class PriorityKnapsack
{
    /**
     * @param  int  $capacityKW  Total available grid capacity in kW (>= 0)
     * @param list<array{
     *     group: string,
     *     loads: list<array{id: string|int, kw: int}>
     * }> $priorityGroups Groups in descending order of priority (first group has highest priority)
     * @return array{
     *     total_capacity_kw: int,
     *     served_kw: int,
     *     remaining_kw: int,
     *     served_loads: list<array{group: string, id: string|int, kw: int}>,
     *     shed_loads: list<array{group: string, id: string|int, kw: int}>,
     *     certificate: array{
     *         capacity_kw: int,
     *         served_kw: int,
     *         remaining_kw: int,
     *         served_count: int,
     *         shed_count: int
     *     }
     * }
     *
     * @throws InvalidArgumentException
     */
    public static function solve(int $capacityKW, array $priorityGroups): array
    {
        if ($capacityKW < 0) {
            throw new InvalidArgumentException("Capacity cannot be negative: {$capacityKW}");
        }

        $remainingCap = $capacityKW;
        $allServed = [];
        $allShed = [];
        $totalServedKW = 0;

        foreach ($priorityGroups as $groupData) {
            $groupName = $groupData['group'];
            $loads = $groupData['loads'];

            foreach ($loads as $load) {
                if ($load['kw'] <= 0) {
                    throw new InvalidArgumentException("Load '{$load['id']}' in group '{$groupName}' has non-positive kW: {$load['kw']}");
                }
            }

            if (empty($loads)) {
                continue;
            }

            // 0/1 Knapsack DP to maximize served kW
            $servedIndices = self::solveKnapsackGroup($loads, $remainingCap);
            $servedSet = array_flip($servedIndices);
            $groupServedKW = 0;

            foreach ($loads as $idx => $load) {
                if (isset($servedSet[$idx])) {
                    $allServed[] = [
                        'group' => $groupName,
                        'id' => $load['id'],
                        'kw' => $load['kw'],
                    ];
                    $groupServedKW += $load['kw'];
                } else {
                    $allShed[] = [
                        'group' => $groupName,
                        'id' => $load['id'],
                        'kw' => $load['kw'],
                    ];
                }
            }

            $remainingCap -= $groupServedKW;
            $totalServedKW += $groupServedKW;
        }

        $certificate = [
            'capacity_kw' => $capacityKW,
            'served_kw' => $totalServedKW,
            'remaining_kw' => $remainingCap,
            'served_count' => count($allServed),
            'shed_count' => count($allShed),
        ];

        $result = [
            'total_capacity_kw' => $capacityKW,
            'served_kw' => $totalServedKW,
            'remaining_kw' => $remainingCap,
            'served_loads' => $allServed,
            'shed_loads' => $allShed,
            'certificate' => $certificate,
        ];

        PriorityKnapsackVerifier::verify($capacityKW, $priorityGroups, $result);

        return $result;
    }

    /**
     * @param  list<array{id: string|int, kw: int}>  $loads
     * @return list<int> Selected load indices
     */
    public static function solveKnapsackGroup(array $loads, int $capacity): array
    {
        $n = count($loads);
        if ($n === 0 || $capacity <= 0) {
            return [];
        }

        // dp[w] = max kW achieved
        // taken[i][w] = bool whether item i was taken for weight w
        $dp = array_fill(0, $capacity + 1, 0);
        $taken = [];

        for ($i = 0; $i < $n; $i++) {
            $kw = $loads[$i]['kw'];
            $taken[$i] = [];

            for ($w = $capacity; $w >= $kw && $w <= $capacity; $w--) {
                $candidate = $dp[$w - $kw] + $kw;
                if ($candidate > $dp[$w]) {
                    $dp[$w] = $candidate;
                    $taken[$i][$w] = true;
                }
            }
        }

        // Backtrack optimal solution
        $bestW = 0;
        for ($w = 1; $w <= $capacity; $w++) {
            if ($dp[$w] > $dp[$bestW]) {
                $bestW = $w;
            }
        }

        $selected = [];
        $currW = $bestW;
        for ($i = $n - 1; $i >= 0 && $currW > 0; $i--) {
            if (isset($taken[$i][$currW])) {
                $selected[] = $i;
                $currW -= $loads[$i]['kw'];
            }
        }

        return array_reverse($selected);
    }
}
