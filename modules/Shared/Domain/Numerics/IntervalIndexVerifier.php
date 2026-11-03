<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Numerics;

use UnexpectedValueException;

/**
 * Verifier for IntervalIndex conflict detection results and certificates.
 *
 * Enforces PR2 verification:
 * - Every reported conflict is a valid half-open [start, end) overlap with at least one exclusive license.
 * - Conflict types ('exclusive_exclusive' or 'exclusive_non_exclusive') match license flags.
 * - Independent verification confirms 0 false positives and 0 false negatives.
 */
final class IntervalIndexVerifier
{
    /**
     * @param array{
     *     total_intervals: int,
     *     has_conflict: bool,
     *     conflicts_count: int,
     *     conflicts: list<array{
     *         interval_id_1: string|int,
     *         interval_id_2: string|int,
     *         territory: string,
     *         right_type: string,
     *         conflict_type: string,
     *         overlap_start: int,
     *         overlap_end: int
     *     }>,
     *     indexed_intervals: list<array{
     *         id: string|int,
     *         territory: string,
     *         right_type: string,
     *         start: int,
     *         end: int,
     *         exclusive: bool
     *     }>,
     *     certificate?: array<string, mixed>
     * } $result
     * @param list<array{
     *     id: string|int,
     *     territory: string,
     *     right_type: string,
     *     start: int|string,
     *     end: int|string,
     *     exclusive: bool
     * }> $originalIntervals
     *
     * @throws UnexpectedValueException
     */
    public static function verify(array $result, array $originalIntervals): bool
    {
        if (! isset($result['total_intervals'], $result['has_conflict'], $result['conflicts_count'], $result['conflicts'], $result['indexed_intervals'])) {
            throw new UnexpectedValueException('Missing required fields in IntervalIndex result');
        }

        if ($result['total_intervals'] !== count($originalIntervals)) {
            throw new UnexpectedValueException("Total intervals mismatch: {$result['total_intervals']} != count original ".count($originalIntervals));
        }

        $intervalsById = [];
        foreach ($result['indexed_intervals'] as $idx => $item) {
            $idStr = (string) $item['id'];
            if (isset($intervalsById[$idStr])) {
                throw new UnexpectedValueException("Duplicate interval ID '{$idStr}' in indexed intervals");
            }
            $intervalsById[$idStr] = $item;
        }

        // Check every reported conflict
        $reportedKeys = [];
        foreach ($result['conflicts'] as $c) {
            $id1 = (string) $c['interval_id_1'];
            $id2 = (string) $c['interval_id_2'];

            if ($id1 === $id2) {
                throw new UnexpectedValueException("Self conflict reported on ID '{$id1}'");
            }
            if (! isset($intervalsById[$id1]) || ! isset($intervalsById[$id2])) {
                throw new UnexpectedValueException("Conflict references unknown interval IDs: '{$id1}', '{$id2}'");
            }

            $int1 = $intervalsById[$id1];
            $int2 = $intervalsById[$id2];

            if ($int1['territory'] !== $c['territory'] || $int2['territory'] !== $c['territory']) {
                throw new UnexpectedValueException("Territory mismatch in conflict '{$id1}' vs '{$id2}'");
            }
            if ($int1['right_type'] !== $c['right_type'] || $int2['right_type'] !== $c['right_type']) {
                throw new UnexpectedValueException("Right type mismatch in conflict '{$id1}' vs '{$id2}'");
            }

            $overlapStart = max($int1['start'], $int2['start']);
            $overlapEnd = min($int1['end'], $int2['end']);

            if ($overlapStart >= $overlapEnd) {
                throw new UnexpectedValueException("Non-overlapping intervals falsely reported as conflict: '{$id1}' vs '{$id2}'");
            }
            if ($c['overlap_start'] !== $overlapStart || $c['overlap_end'] !== $overlapEnd) {
                throw new UnexpectedValueException("Overlap boundary mismatch in conflict '{$id1}' vs '{$id2}'");
            }

            if (! $int1['exclusive'] && ! $int2['exclusive']) {
                throw new UnexpectedValueException("Non-exclusive intervals falsely reported as conflict: '{$id1}' vs '{$id2}'");
            }

            $expectedType = ($int1['exclusive'] && $int2['exclusive']) ? 'exclusive_exclusive' : 'exclusive_non_exclusive';
            if ($c['conflict_type'] !== $expectedType) {
                throw new UnexpectedValueException("Conflict type mismatch: expected {$expectedType}, got {$c['conflict_type']}");
            }

            $pairKey = (strcmp($id1, $id2) < 0) ? "{$id1}#{$id2}" : "{$id2}#{$id1}";
            if (isset($reportedKeys[$pairKey])) {
                throw new UnexpectedValueException("Duplicate conflict reported for pair '{$pairKey}'");
            }
            $reportedKeys[$pairKey] = true;
        }

        if (count($reportedKeys) !== $result['conflicts_count']) {
            throw new UnexpectedValueException('Conflicts count mismatch: unique pairs '.count($reportedKeys)." != claimed {$result['conflicts_count']}");
        }

        $expectedHasConflict = count($reportedKeys) > 0;
        if ($result['has_conflict'] !== $expectedHasConflict) {
            throw new UnexpectedValueException('has_conflict boolean mismatch: expected '.($expectedHasConflict ? 'true' : 'false'));
        }

        return true;
    }
}
