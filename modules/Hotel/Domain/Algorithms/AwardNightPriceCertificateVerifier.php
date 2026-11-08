<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Algorithms;

final class AwardNightPriceCertificateVerifier
{
    public function verify(AwardNightPriceCertificate $certificate): bool
    {
        $payload = json_encode([
            'final_points' => $certificate->finalPointsRequired,
            'reimbursement' => $certificate->reimbursementValueIdr,
            'floor_guaranteed' => $certificate->floorGuaranteedIdr,
            'is_floor_applied' => $certificate->isFloorApplied,
            'tier_discount' => $certificate->tierDiscountPts,
        ], JSON_THROW_ON_ERROR);

        $expectedHash = hash('sha256', $payload);

        return hash_equals($expectedHash, $certificate->signatureHash);
    }
}
