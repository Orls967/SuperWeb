<?php

declare(strict_types=1);

namespace App\Quality\Progress;

/**
 * Value object representing a phase section in docs/PROGRESS.md.
 */
final class ProgressPhase
{
    /**
     * @param  list<ProgressItem>  $items
     */
    public function __construct(
        public readonly string $id,
        public readonly string $title,
        public readonly string $statusIcon,
        public readonly string $statusRaw,
        public readonly ?string $statusDate,
        public readonly array $items = [],
    ) {}

    public function isFaseR(): bool
    {
        return str_starts_with($this->id, 'R');
    }

    public function isVerified(): bool
    {
        return $this->statusIcon === '✅';
    }

    public function isStatusChangedAfter(string $cutoffDate = '2026-10-10'): bool
    {
        if ($this->statusDate === null) {
            return false;
        }

        return strcmp($this->statusDate, $cutoffDate) > 0;
    }
}
