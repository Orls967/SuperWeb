<?php

declare(strict_types=1);

namespace Modules\Insurance\Domain\DTOs;

/**
 * Rencana cession hasil CessionEngine: bruto = retensi + Σ ceded. Snapshot masukan disimpan (PR3).
 */
final readonly class CessionPlan
{
    /**
     * @param  list<CessionLineData>  $lines
     * @param  array<string, mixed>  $snapshot
     */
    public function __construct(
        public int $grossIdr,
        public int $retainedIdr,
        public array $lines,
        public array $snapshot,
    ) {}

    public function cededIdr(): int
    {
        return array_sum(array_map(static fn (CessionLineData $l): int => $l->cededIdr, $this->lines));
    }

    public function commissionIdr(): int
    {
        return array_sum(array_map(static fn (CessionLineData $l): int => $l->commissionIdr, $this->lines));
    }

    public function reinstatementIdr(): int
    {
        return array_sum(array_map(static fn (CessionLineData $l): int => $l->reinstatementIdr, $this->lines));
    }

    public function isEmpty(): bool
    {
        return $this->lines === [];
    }

    public function snapshotHash(): string
    {
        return hash('sha256', json_encode($this->snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }
}
