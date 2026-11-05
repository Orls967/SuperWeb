<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Islamic;

/**
 * Hasil audit screening syariah atas instrumen keuangan.
 */
final class ShariahScreeningResult
{
    public function __construct(
        public readonly int $interestBearingDebtIdr,
        public readonly int $totalAssetsIdr,
        public readonly int $debtRatioBps,
        public readonly int $nonHalalRevenueIdr,
        public readonly int $totalRevenueIdr,
        public readonly int $nonHalalRevenueRatioBps,
        public readonly string $sectorCode,
        public readonly bool $sectorAllowed,
        public readonly bool $debtCompliant,
        public readonly bool $revenueCompliant,
        public readonly bool $isCompliant
    ) {}
}

