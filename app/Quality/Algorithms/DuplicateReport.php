<?php

declare(strict_types=1);

namespace App\Quality\Algorithms;

final class DuplicateReport
{
    /**
     * @param  list<DuplicateBlock>  $blocks
     */
    public function __construct(
        public readonly int $duplicateCount,
        public readonly float $duplicateRatio,
        public readonly array $blocks,
        public readonly int $totalLinesAnalyzed,
        public readonly string $sha256Certificate = '',
    ) {}

    public function hasDuplicates(): bool
    {
        return $this->duplicateCount > 0;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'duplicate_count' => $this->duplicateCount,
            'duplicate_ratio' => $this->duplicateRatio,
            'total_lines' => $this->totalLinesAnalyzed,
            'certificate' => $this->sha256Certificate,
            'blocks' => array_map(fn (DuplicateBlock $b) => $b->toArray(), $this->blocks),
        ];
    }
}
