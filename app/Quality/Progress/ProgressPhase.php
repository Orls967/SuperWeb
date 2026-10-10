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
     * @param  list<array{id: string, item: string, minus: string, dampak: string, prioritas: string, rencana: string, is_closed: bool}>  $minusEntries
     */
    public function __construct(
        public readonly string $id,
        public readonly string $title,
        public readonly string $statusIcon,
        public readonly string $statusRaw,
        public readonly ?string $statusDate,
        public readonly array $items = [],
        public readonly array $minusEntries = [],
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

    /**
     * Returns list of open (unclosed) P0 or P1 minus IDs.
     *
     * @return list<string>
     */
    public function openCriticalMinusIds(): array
    {
        $critical = [];
        foreach ($this->minusEntries as $entry) {
            if ($entry['is_closed']) {
                continue;
            }
            $pri = strtoupper(trim((string) ($entry['prioritas'] ?? '')));
            if ($pri === 'P0' || $pri === 'P1') {
                $critical[] = (string) ($entry['id'] ?? 'unknown');
            }
        }

        return $critical;
    }
}
