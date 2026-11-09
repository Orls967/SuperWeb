<?php

declare(strict_types=1);

namespace App\Quality\Algorithms;

final class DuplicateWindowResult
{
    /**
     * @param  list<DuplicateBlock>  $blocks
     */
    public function __construct(
        public readonly int $candidateCount,
        public readonly array $blocks,
    ) {}

    public function toReport(int $totalLines): DuplicateReport
    {
        $dupLines = 0;
        foreach ($this->blocks as $block) {
            $dupLines += $block->length;
        }

        $ratio = $totalLines > 0 ? min(1.0, $dupLines / $totalLines) : 0.0;
        $cert = hash('sha256', json_encode(array_map(fn (DuplicateBlock $b) => $b->toArray(), $this->blocks)));

        return new DuplicateReport(
            duplicateCount: count($this->blocks),
            duplicateRatio: $ratio,
            blocks: $this->blocks,
            totalLinesAnalyzed: $totalLines,
            sha256Certificate: $cert,
        );
    }
}
