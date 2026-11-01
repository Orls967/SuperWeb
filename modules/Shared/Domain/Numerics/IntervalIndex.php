<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Numerics;

use InvalidArgumentException;

/**
 * Multi-dimensional Half-Open Interval Index and License Conflict Detector.
 *
 * Checks time conflicts on half-open [start, end) intervals partitioned by
 * composite dimensions (e.g. territory + right_type):
 * - Exclusive licenses cannot overlap with ANY license (exclusive or non-exclusive) on the same key.
 * - Non-exclusive licenses may overlap with other non-exclusive licenses, but NOT with exclusive ones.
 * - Half-open intervals [start, end) overlap if max(start1, start2) < min(end1, end2).
 *
 * Complexity: O(N log N) sweep-line interval sorting and conflict detection.
 */
final class IntervalIndex
{
    /**
     * @param list<array{
     *     id: string|int,
     *     territory: string,
     *     right_type: string,
     *     start: int|string,
     *     end: int|string,
     *     exclusive: bool
     * }> $intervals
     * @return array{
     *     total_intervals: int,
     *     has_conflict: bool,
     *     conflicts_count: int,
     *     conflicts: list<array{
     *         interval_id_1: string|int,
     *         interval_id_2: string|int,
     *         territory: string,
     *         right_type: string,
     *         conflict_type: 'exclusive_exclusive'|'exclusive_non_exclusive',
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
     *     certificate: array{
     *         total_intervals: int,
     *         has_conflict: bool,
     *         conflicts_count: int,
     *         conflicts: list<array{
     *             interval_id_1: string|int,
     *             interval_id_2: string|int,
     *             territory: string,
     *             right_type: string,
     *             conflict_type: 'exclusive_exclusive'|'exclusive_non_exclusive',
     *             overlap_start: int,
     *             overlap_end: int
     *         }>
     *     }
     * }
     */
    public static function detectConflicts(array $intervals): array
    {
        $normalized = [];

        foreach ($intervals as $idx => $item) {
            if (! isset($item['id'], $item['territory'], $item['right_type'], $item['start'], $item['end'], $item['exclusive'])) {
                throw new InvalidArgumentException("Missing required fields in interval at index {$idx}");
            }

            $startTs = self::normalizeTimestamp($item['start']);
            $endTs = self::normalizeTimestamp($item['end']);

            if ($startTs >= $endTs) {
                throw new InvalidArgumentException("Interval start {$startTs} must be strictly less than end {$endTs} at index {$idx}");
            }

            $normalized[] = [
                'id' => $item['id'],
                'territory' => trim($item['territory']),
                'right_type' => trim($item['right_type']),
                'start' => $startTs,
                'end' => $endTs,
                'exclusive' => (bool) $item['exclusive'],
            ];
        }

        // Group intervals by composite key "territory:right_type"
        $grouped = [];
        foreach ($normalized as $item) {
            $key = $item['territory'].':'.$item['right_type'];
            $grouped[$key][] = $item;
        }

        $conflicts = [];

        foreach ($grouped as $groupKey => $groupItems) {
            // Sort intervals by start ASC, end ASC, id ASC
            usort($groupItems, function ($a, $b) {
                if ($a['start'] !== $b['start']) {
                    return $a['start'] <=> $b['start'];
                }
                if ($a['end'] !== $b['end']) {
                    return $a['end'] <=> $b['end'];
                }

                return strcmp((string) $a['id'], (string) $b['id']);
            });

            // Sweep-line over intervals in this group
            // Active intervals list
            /** @var list<array{id: string|int, territory: string, right_type: string, start: int, end: int, exclusive: bool}> $active */
            $active = [];

            foreach ($groupItems as $current) {
                // Prune active intervals that ended before or at current start (half-open [start, end))
                $remainingActive = [];
                foreach ($active as $act) {
                    if ($act['end'] > $current['start']) {
                        $remainingActive[] = $act;
                    }
                }
                $active = $remainingActive;

                // Check conflict with all active intervals
                foreach ($active as $act) {
                    // Conflict if at least one is exclusive
                    if ($current['exclusive'] || $act['exclusive']) {
                        $overlapStart = max($act['start'], $current['start']);
                        $overlapEnd = min($act['end'], $current['end']);

                        if ($overlapStart < $overlapEnd) {
                            $type = ($act['exclusive'] && $current['exclusive'])
                                ? 'exclusive_exclusive'
                                : 'exclusive_non_exclusive';

                            // Deterministic pair ordering by ID
                            $id1 = $act['id'];
                            $id2 = $current['id'];
                            if (strcmp((string) $id1, (string) $id2) > 0) {
                                [$id1, $id2] = [$id2, $id1];
                            }

                            $conflicts[] = [
                                'interval_id_1' => $id1,
                                'interval_id_2' => $id2,
                                'territory' => $current['territory'],
                                'right_type' => $current['right_type'],
                                'conflict_type' => $type,
                                'overlap_start' => $overlapStart,
                                'overlap_end' => $overlapEnd,
                            ];
                        }
                    }
                }

                $active[] = $current;
            }
        }

        // Sort conflicts deterministically by id1 ASC, id2 ASC
        usort($conflicts, function ($a, $b) {
            $cmp = strcmp((string) $a['interval_id_1'], (string) $b['interval_id_1']);
            if ($cmp !== 0) {
                return $cmp;
            }

            return strcmp((string) $a['interval_id_2'], (string) $b['interval_id_2']);
        });

        $hasConflict = $conflicts !== [];

        $certificate = [
            'total_intervals' => count($normalized),
            'has_conflict' => $hasConflict,
            'conflicts_count' => count($conflicts),
            'conflicts' => $conflicts,
        ];

        $result = [
            'total_intervals' => count($normalized),
            'has_conflict' => $hasConflict,
            'conflicts_count' => count($conflicts),
            'conflicts' => $conflicts,
            'indexed_intervals' => $normalized,
            'certificate' => $certificate,
        ];

        IntervalIndexVerifier::verify($result, $intervals);

        return $result;
    }

    public static function normalizeTimestamp(int|string $ts): int
    {
        if (is_int($ts)) {
            return $ts;
        }
        $parsed = strtotime($ts);
        if ($parsed === false) {
            throw new InvalidArgumentException("Unable to parse timestamp string: '{$ts}'");
        }

        return $parsed;
    }
}
