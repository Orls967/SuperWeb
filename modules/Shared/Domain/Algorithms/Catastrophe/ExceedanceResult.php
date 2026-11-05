<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Catastrophe;

/**
 * Hasil analisis Year Loss Table. $yearEvents disimpan agar verifier dapat menghitung ulang secara independen.
 */
final readonly class ExceedanceResult
{
    /**
     * @param  list<list<int>>  $yearEvents  kerugian event per tahun (urut kejadian)
     * @param  list<int>  $aggregates  Σ per tahun
     * @param  list<int>  $maxima  kerugian event terbesar per tahun
     * @param  list<int>  $aggregateRanking  indeks tahun urut turun menurut agregat (tie-break indeks menaik)
     * @param  list<int>  $maximumRanking  idem menurut maksimum
     */
    public function __construct(
        public array $yearEvents,
        public array $aggregates,
        public array $maxima,
        public array $aggregateRanking,
        public array $maximumRanking,
        public int $aalIdr,
    ) {}

    public function years(): int
    {
        return count($this->yearEvents);
    }
}
