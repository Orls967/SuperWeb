<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Scheduling;

/**
 * Nilai kembalian pemecahan target waktu berbobot dengan kurva-S kumulatif.
 */
final class WeightedTimePhasingResult
{
    /**
     * @param  list<int>  $dailyAllocations  Alokasi integer per hari/periode
     * @param  list<int>  $cumulativeSCurve  Nilai kumulatif kurva-S per hari/periode
     * @param  list<float>  $exactQuotas  Kuota eksak sebelum pembulatan
     * @param  string  $certificateHash  Hash sertifikat integritas SHA-256
     */
    public function __construct(
        public readonly int $totalTarget,
        public readonly array $dailyAllocations,
        public readonly array $cumulativeSCurve,
        public readonly array $exactQuotas,
        public readonly string $certificateHash,
        public readonly array $certificateData
    ) {}
}
