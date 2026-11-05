<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Islamic;

/**
 * Hasil alokasi pembagian zakat ke 8 asnaf.
 */
final class AsnafDistributionResult
{
    /**
     * @param  array<string, int>  $weightsBps
     * @param  array<string, int>  $allocations
     */
    public function __construct(
        public readonly int $totalZakatFundIdr,
        public readonly array $weightsBps,
        public readonly array $allocations
    ) {}

    public function allocationFor(string $asnaf): int
    {
        return $this->allocations[$asnaf] ?? 0;
    }
}
