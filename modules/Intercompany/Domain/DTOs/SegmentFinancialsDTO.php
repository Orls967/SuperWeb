<?php

declare(strict_types=1);

namespace Modules\Intercompany\Domain\DTOs;

final class SegmentFinancialsDTO
{
    public function __construct(
        public readonly string $segmentCode,
        public readonly string $segmentName,
        public readonly int $externalRevenueIdr,
        public readonly int $interSegmentRevenueIdr,
        public readonly int $totalRevenueIdr,
        public readonly int $operatingExpenseIdr,
        public readonly int $segmentProfitIdr,
        public readonly int $segmentAssetsIdr,
        public readonly int $segmentLiabilitiesIdr
    ) {}
}
