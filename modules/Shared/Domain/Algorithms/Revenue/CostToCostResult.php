<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Revenue;

final readonly class CostToCostResult
{
    public function __construct(
        public int $contractAmountIdr,
        public int $cumulativeCostIdr,
        public int $totalEstimatedCostIdr,
        public int $previouslyRecognizedIdr,
        public int $cumulativeRecognizedIdr,
        public int $periodRevenueIdr,
        public string $certificateSha256,
    ) {}
}
