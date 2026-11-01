<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Math;

use DateTimeInterface;
use InvalidArgumentException;

/**
 * 15-Minute Slot Set Operations and Half-Open Interval Algebra.
 *
 * Provides conversions between half-open time intervals [start, end) and discrete 15-minute slot indices,
 * fast overlap and adjacency detection, set operations (union, intersection, difference, complement),
 * and merging contiguous free/occupied slots into minimal canonical intervals.
 *
 * Complexity:
 * - intervalToSlots: O(k) where k = (end - start) / 15
 * - slotsToIntervals: O(m log m) where m is slot count
 * - overlaps / isAdjacent: O(1)
 * - set operations: O(m + n) on sorted slot sets
 *
 * @author Google Deepmind & Antigravity
 */
final class SlotSet
{
    public const int SLOT_MINUTES = 15;

    /**
     * @var list<int> Sorted unique slot indices.
     */
    private array $slots;

    /**
     * @param  list<int>  $slots
     */
    public function __construct(array $slots = [])
    {
        $unique = array_values(array_unique($slots));
        sort($unique);
        $this->slots = $unique;
    }

    /**
     * Parses time representation to minute of day or epoch minute.
     *
     * Supports:
     * - "HH:MM" (e.g. "09:00" -> 540)
     * - "YYYY-MM-DD HH:MM[:SS]" -> epoch minutes
     * - DateTimeInterface -> epoch minutes
     * - int -> raw minutes (must be non-negative)
     *
     * @return int Minute value
     *
     * @throws InvalidArgumentException
     */
    public static function parseTime(int|string|DateTimeInterface $time): int
    {
        if ($time instanceof DateTimeInterface) {
            $ts = $time->getTimestamp();
            if ($ts < 0) {
                throw new InvalidArgumentException('Timestamp cannot be negative');
            }

            return intdiv($ts, 60);
        }

        if (is_int($time)) {
            if ($time < 0) {
                throw new InvalidArgumentException("Minute cannot be negative: {$time}");
            }

            return $time;
        }

        if (is_numeric($time)) {
            $val = (int) $time;
            if ($val < 0) {
                throw new InvalidArgumentException("Minute cannot be negative: {$time}");
            }

            return $val;
        }

        $trimmed = trim($time);
        if (preg_match('/^(\d{1,2}):(\d{2})$/', $trimmed, $matches)) {
            $h = (int) $matches[1];
            $m = (int) $matches[2];
            if ($h === 24 && $m === 0) {
                return 1440;
            }
            if ($h < 0 || $h > 23 || $m < 0 || $m > 59) {
                throw new InvalidArgumentException("Invalid time components in '{$time}'");
            }

            return $h * 60 + $m;
        }

        $ts = strtotime($trimmed);
        if ($ts !== false && $ts >= 0) {
            return intdiv($ts, 60);
        }

        throw new InvalidArgumentException("Unrecognized time format: '{$time}'");
    }

    /**
     * Formats minute to "HH:MM" format.
     */
    public static function formatMinute(int $minute): string
    {
        if ($minute < 0) {
            throw new InvalidArgumentException("Minute cannot be negative: {$minute}");
        }
        $h = intdiv($minute, 60);
        $m = $minute % 60;

        return sprintf('%02d:%02d', $h, $m);
    }

    /**
     * Converts a half-open interval [start, end) into 15-minute slot indices.
     * Both start and end must be aligned to 15-minute boundaries.
     *
     * @return list<int>
     *
     * @throws InvalidArgumentException
     */
    public static function intervalToSlots(int|string|DateTimeInterface $start, int|string|DateTimeInterface $end): array
    {
        $startMin = self::parseTime($start);
        $endMin = self::parseTime($end);

        if ($startMin >= $endMin) {
            throw new InvalidArgumentException("Start time ({$startMin}) must be strictly less than end time ({$endMin})");
        }

        if ($startMin % self::SLOT_MINUTES !== 0) {
            throw new InvalidArgumentException("Start time must be aligned to 15-minute slot boundary: {$startMin}");
        }

        if ($endMin % self::SLOT_MINUTES !== 0) {
            throw new InvalidArgumentException("End time must be aligned to 15-minute slot boundary: {$endMin}");
        }

        $startSlot = intdiv($startMin, self::SLOT_MINUTES);
        $endSlot = intdiv($endMin, self::SLOT_MINUTES);

        $slots = [];
        for ($s = $startSlot; $s < $endSlot; $s++) {
            $slots[] = $s;
        }

        return $slots;
    }

    /**
     * Merges contiguous slot indices into minimal half-open intervals [start, end) in minutes.
     *
     * @param  list<int>  $slots
     * @return list<array{start: int, end: int}>
     */
    public static function slotsToIntervals(array $slots): array
    {
        if (empty($slots)) {
            return [];
        }

        $unique = array_values(array_unique($slots));
        sort($unique);

        $intervals = [];
        $runStart = $unique[0];
        $runEnd = $runStart + 1;

        $count = count($unique);
        for ($i = 1; $i < $count; $i++) {
            if ($unique[$i] === $runEnd) {
                $runEnd++;
            } else {
                $intervals[] = [
                    'start' => $runStart * self::SLOT_MINUTES,
                    'end' => $runEnd * self::SLOT_MINUTES,
                ];
                $runStart = $unique[$i];
                $runEnd = $runStart + 1;
            }
        }

        $intervals[] = [
            'start' => $runStart * self::SLOT_MINUTES,
            'end' => $runEnd * self::SLOT_MINUTES,
        ];

        return $intervals;
    }

    /**
     * Merges contiguous slot indices into minimal half-open intervals formatted as "HH:MM".
     *
     * @param  list<int>  $slots
     * @return list<array{start: string, end: string}>
     */
    public static function slotsToTimeIntervals(array $slots): array
    {
        $intervals = self::slotsToIntervals($slots);
        $result = [];

        foreach ($intervals as $interval) {
            $result[] = [
                'start' => self::formatMinute($interval['start']),
                'end' => self::formatMinute($interval['end']),
            ];
        }

        return $result;
    }

    /**
     * Determines whether two half-open intervals [startA, endA) and [startB, endB) overlap.
     *
     * In half-open semantics, [a, b) and [b, c) do NOT overlap (intersection is empty).
     */
    public static function overlaps(
        int|string|DateTimeInterface $startA,
        int|string|DateTimeInterface $endA,
        int|string|DateTimeInterface $startB,
        int|string|DateTimeInterface $endB
    ): bool {
        $sA = self::parseTime($startA);
        $eA = self::parseTime($endA);
        $sB = self::parseTime($startB);
        $eB = self::parseTime($endB);

        if ($sA >= $eA) {
            throw new InvalidArgumentException("Interval A start ({$sA}) must be less than end ({$eA})");
        }
        if ($sB >= $eB) {
            throw new InvalidArgumentException("Interval B start ({$sB}) must be less than end ({$eB})");
        }

        return max($sA, $sB) < min($eA, $eB);
    }

    /**
     * Determines whether two half-open intervals are adjacent (touching at boundary without overlapping).
     */
    public static function isAdjacent(
        int|string|DateTimeInterface $startA,
        int|string|DateTimeInterface $endA,
        int|string|DateTimeInterface $startB,
        int|string|DateTimeInterface $endB
    ): bool {
        $sA = self::parseTime($startA);
        $eA = self::parseTime($endA);
        $sB = self::parseTime($startB);
        $eB = self::parseTime($endB);

        if ($sA >= $eA || $sB >= $eB) {
            throw new InvalidArgumentException('Interval start must be less than end');
        }

        return $eA === $sB || $eB === $sA;
    }

    /**
     * Computes free 15-minute slot indices within [rangeStart, rangeEnd) excluding occupied intervals.
     *
     * @param  list<array{start: int|string|DateTimeInterface, end: int|string|DateTimeInterface}>  $occupiedIntervals
     * @return list<int>
     */
    public static function freeSlots(
        int|string|DateTimeInterface $rangeStart,
        int|string|DateTimeInterface $rangeEnd,
        array $occupiedIntervals
    ): array {
        $allSlots = self::intervalToSlots($rangeStart, $rangeEnd);
        $occupiedSlotMap = [];

        foreach ($occupiedIntervals as $interval) {
            $occSlots = self::intervalToSlots($interval['start'], $interval['end']);
            foreach ($occSlots as $slot) {
                $occupiedSlotMap[$slot] = true;
            }
        }

        $free = [];
        foreach ($allSlots as $slot) {
            if (! isset($occupiedSlotMap[$slot])) {
                $free[] = $slot;
            }
        }

        return $free;
    }

    /**
     * Computes and merges free contiguous slots within [rangeStart, rangeEnd) excluding occupied intervals.
     *
     * @param  list<array{start: int|string|DateTimeInterface, end: int|string|DateTimeInterface}>  $occupiedIntervals
     * @return list<array{start: string, end: string}>
     */
    public static function mergeFreeSlots(
        int|string|DateTimeInterface $rangeStart,
        int|string|DateTimeInterface $rangeEnd,
        array $occupiedIntervals
    ): array {
        $free = self::freeSlots($rangeStart, $rangeEnd, $occupiedIntervals);

        return self::slotsToTimeIntervals($free);
    }

    /**
     * Alias for mergeFreeSlots.
     *
     * @param  list<array{start: int|string|DateTimeInterface, end: int|string|DateTimeInterface}>  $occupiedIntervals
     * @return list<array{start: string, end: string}>
     */
    public static function freeIntervals(
        int|string|DateTimeInterface $rangeStart,
        int|string|DateTimeInterface $rangeEnd,
        array $occupiedIntervals
    ): array {
        return self::mergeFreeSlots($rangeStart, $rangeEnd, $occupiedIntervals);
    }

    /**
     * Create SlotSet from a half-open interval.
     */
    public static function fromInterval(int|string|DateTimeInterface $start, int|string|DateTimeInterface $end): self
    {
        return new self(self::intervalToSlots($start, $end));
    }

    /**
     * Create SlotSet from multiple half-open intervals.
     *
     * @param  list<array{start: int|string|DateTimeInterface, end: int|string|DateTimeInterface}>  $intervals
     */
    public static function fromIntervals(array $intervals): self
    {
        $all = [];
        foreach ($intervals as $interval) {
            $slots = self::intervalToSlots($interval['start'], $interval['end']);
            foreach ($slots as $slot) {
                $all[] = $slot;
            }
        }

        return new self($all);
    }

    public function containsSlot(int $slot): bool
    {
        return in_array($slot, $this->slots, true);
    }

    public function containsTime(int|string|DateTimeInterface $time): bool
    {
        $min = self::parseTime($time);
        $slot = intdiv($min, self::SLOT_MINUTES);

        return $this->containsSlot($slot);
    }

    public function addSlot(int $slot): self
    {
        return new self([...$this->slots, $slot]);
    }

    public function removeSlot(int $slot): self
    {
        return new self(array_values(array_filter($this->slots, fn (int $s) => $s !== $slot)));
    }

    public function addInterval(int|string|DateTimeInterface $start, int|string|DateTimeInterface $end): self
    {
        $newSlots = self::intervalToSlots($start, $end);

        return new self([...$this->slots, ...$newSlots]);
    }

    public function removeInterval(int|string|DateTimeInterface $start, int|string|DateTimeInterface $end): self
    {
        $removeMap = array_flip(self::intervalToSlots($start, $end));
        $filtered = array_filter($this->slots, fn (int $s) => ! isset($removeMap[$s]));

        return new self(array_values($filtered));
    }

    public function overlapsWith(self $other): bool
    {
        $otherMap = array_flip($other->slots);
        foreach ($this->slots as $s) {
            if (isset($otherMap[$s])) {
                return true;
            }
        }

        return false;
    }

    public function union(self $other): self
    {
        return new self([...$this->slots, ...$other->slots]);
    }

    public function intersect(self $other): self
    {
        $otherMap = array_flip($other->slots);
        $common = array_filter($this->slots, fn (int $s) => isset($otherMap[$s]));

        return new self(array_values($common));
    }

    public function diff(self $other): self
    {
        $otherMap = array_flip($other->slots);
        $diff = array_filter($this->slots, fn (int $s) => ! isset($otherMap[$s]));

        return new self(array_values($diff));
    }

    public function invert(int|string|DateTimeInterface $rangeStart, int|string|DateTimeInterface $rangeEnd): self
    {
        $universe = self::intervalToSlots($rangeStart, $rangeEnd);
        $selfMap = array_flip($this->slots);
        $inverted = array_filter($universe, fn (int $s) => ! isset($selfMap[$s]));

        return new self(array_values($inverted));
    }

    /**
     * @return list<int>
     */
    public function slots(): array
    {
        return $this->slots;
    }

    public function count(): int
    {
        return count($this->slots);
    }

    public function isEmpty(): bool
    {
        return empty($this->slots);
    }

    /**
     * @return list<array{start: int, end: int}>
     */
    public function toIntervals(): array
    {
        return self::slotsToIntervals($this->slots);
    }

    /**
     * @return list<array{start: string, end: string}>
     */
    public function toTimeIntervals(): array
    {
        return self::slotsToTimeIntervals($this->slots);
    }
}
