<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Numerics;

use UnexpectedValueException;

/**
 * Production verifier for PriorityKnapsack certificates (PR2).
 * Confirms capacity conservation, group-by-group resolution, and that no shed load
 * could fit in the final remaining capacity.
 */
final class PriorityKnapsackVerifier
{
    /**
     * @param  list<array{group: string, loads: list<array{id: string|int, kw: int}>}>  $priorityGroups
     * @param  array<string, mixed>  $result
     *
     * @throws UnexpectedValueException
     */
    public static function verify(int $capacityKW, array $priorityGroups, array $result): bool
    {
        $served = $result['served_loads'] ?? [];
        $shed = $result['shed_loads'] ?? [];

        $servedKW = 0;
        foreach ($served as $s) {
            $servedKW += $s['kw'];
        }

        if ($servedKW !== $result['served_kw']) {
            throw new UnexpectedValueException("Served kW sum mismatch: calculated {$servedKW}, recorded {$result['served_kw']}.");
        }

        if ($servedKW + $result['remaining_kw'] !== $capacityKW) {
            throw new UnexpectedValueException("Capacity conservation violated: served {$servedKW} + remaining {$result['remaining_kw']} !== capacity {$capacityKW}.");
        }

        // Verify that NO shed load fits in the final remaining capacity
        $remaining = $result['remaining_kw'];
        foreach ($shed as $s) {
            if ($s['kw'] <= $remaining) {
                throw new UnexpectedValueException("Shed load '{$s['id']}' ({$s['kw']} kW) fits in final remaining capacity ({$remaining} kW).");
            }
        }

        // Re-solve per group with remaining capacity (PR2 requirement)
        $simCap = $capacityKW;
        $servedByGroup = [];
        foreach ($served as $s) {
            $servedByGroup[$s['group']][] = $s['id'];
        }

        foreach ($priorityGroups as $group) {
            $loads = $group['loads'];
            $groupName = $group['group'];
            $actualServedIds = $servedByGroup[$groupName] ?? [];

            $expectedIndices = PriorityKnapsack::solveKnapsackGroup($loads, $simCap);
            $expectedGroupKW = 0;
            foreach ($expectedIndices as $idx) {
                $expectedGroupKW += $loads[$idx]['kw'];
            }

            $actualGroupKW = 0;
            foreach ($loads as $l) {
                if (in_array($l['id'], $actualServedIds, true)) {
                    $actualGroupKW += $l['kw'];
                }
            }

            if ($actualGroupKW !== $expectedGroupKW) {
                throw new UnexpectedValueException("Group '{$groupName}' served kW {$actualGroupKW} does not match expected re-solved {$expectedGroupKW}.");
            }

            $simCap -= $actualGroupKW;
        }

        return true;
    }
}
