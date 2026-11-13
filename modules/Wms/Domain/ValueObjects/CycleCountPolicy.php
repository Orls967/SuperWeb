<?php

declare(strict_types=1);

namespace Modules\Wms\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * Kebijakan toleransi selisih cycle count (bps & nominal IDR minor units).
 */
final readonly class CycleCountPolicy
{
    public function __construct(
        public int $autoApproveMaxVarianceBps = 100,
        public int $autoApproveMaxVarianceIdr = 250000,
        public int $dailyBinCapacity = 40,
    ) {
        if ($autoApproveMaxVarianceBps < 0 || $autoApproveMaxVarianceIdr < 0 || $dailyBinCapacity <= 0) {
            throw new InvalidArgumentException('Parameter CycleCountPolicy tidak valid.');
        }
    }

    public function requiresSupervisorApproval(int $varianceBps, int $absVarianceValueIdr): bool
    {
        return abs($varianceBps) > $this->autoApproveMaxVarianceBps
            || $absVarianceValueIdr > $this->autoApproveMaxVarianceIdr;
    }
}
