<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Algorithms;

final class PointsLiabilityFifoCertificateVerifier
{
    public function verify(PointsLiabilityFifoCertificate $certificate): bool
    {
        $payload = json_encode([
            'allocations' => $certificate->redemptionAllocations,
            'pts_deducted' => $certificate->totalPtsDeducted,
            'liability_deducted' => $certificate->totalLiabilityDeductedIdr,
            'expired_batches' => $certificate->expiredBatches,
            'expired_pts' => $certificate->totalExpiredPts,
            'breakage_revenue' => $certificate->totalBreakageRevenueIdr,
        ], JSON_THROW_ON_ERROR);

        $expectedHash = hash('sha256', $payload);

        return hash_equals($expectedHash, $certificate->signatureHash);
    }
}
