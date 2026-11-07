<?php

declare(strict_types=1);

namespace Modules\Hotel\Application\DTOs;

final readonly class BreakageAuditResultDTO
{
    /**
     * @param  list<array{batch_id: string, expired_pts: int, breakage_revenue_idr: int}>  $expiredBatches
     */
    public function __construct(
        public int $totalBatchesScanned,
        public int $totalExpiredBatches,
        public int $totalExpiredPts,
        public int $breakageRevenueIdr,
        public array $expiredBatches
    ) {}
}
