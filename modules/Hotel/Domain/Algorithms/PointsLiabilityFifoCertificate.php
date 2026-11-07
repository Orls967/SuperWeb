<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Algorithms;

final readonly class PointsLiabilityFifoCertificate
{
    /**
     * @param  list<array{batch_id: string, deducted_pts: int, liability_deducted_idr: int}>  $redemptionAllocations
     * @param  list<array{batch_id: string, expired_pts: int, breakage_revenue_idr: int}>  $expiredBatches
     */
    public function __construct(
        public array $redemptionAllocations,
        public int $totalPtsDeducted,
        public int $totalLiabilityDeductedIdr,
        public array $expiredBatches,
        public int $totalExpiredPts,
        public int $totalBreakageRevenueIdr,
        public string $signatureHash
    ) {}
}
