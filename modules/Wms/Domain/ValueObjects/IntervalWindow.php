<?php

declare(strict_types=1);

namespace Modules\Wms\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * Value object interval setengah-terbuka [start, end) dalam menit bulat (D279-4, D96).
 */
final readonly class IntervalWindow
{
    public function __construct(
        public int $start,
        public int $end,
    ) {
        if ($this->start < 0 || $this->end <= $this->start) {
            throw new InvalidArgumentException("Interval setengah-terbuka tidak valid: [{$this->start}, {$this->end}).");
        }
    }

    public function duration(): int
    {
        return $this->end - $this->start;
    }

    public function overlaps(self $other): bool
    {
        return $this->start < $other->end && $this->end > $other->start;
    }

    public function containsMinute(int $minute): bool
    {
        return $minute >= $this->start && $minute < $this->end;
    }

    public function intersection(self $other): ?self
    {
        if (! $this->overlaps($other)) {
            return null;
        }

        return new self(max($this->start, $other->start), min($this->end, $other->end));
    }

    /**
     * @param  list<self>  $intervals
     * @return list<self>
     */
    public static function findGaps(array $intervals, int $horizonStart, int $horizonEnd): array
    {
        if ($horizonEnd <= $horizonStart) {
            return [];
        }

        usort($intervals, static fn (self $a, self $b): int => [$a->start, $a->end] <=> [$b->start, $b->end]);

        $cursor = $horizonStart;
        $gaps = [];

        foreach ($intervals as $iv) {
            if ($iv->end <= $cursor) {
                continue;
            }
            if ($iv->start > $cursor) {
                $gapEnd = min($iv->start, $horizonEnd);
                if ($gapEnd > $cursor) {
                    $gaps[] = new self($cursor, $gapEnd);
                }
            }
            $cursor = max($cursor, $iv->end);
            if ($cursor >= $horizonEnd) {
                break;
            }
        }

        if ($cursor < $horizonEnd) {
            $gaps[] = new self($cursor, $horizonEnd);
        }

        return $gaps;
    }
}
