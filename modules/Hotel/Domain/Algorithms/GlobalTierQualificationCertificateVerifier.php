<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Algorithms;

final class GlobalTierQualificationCertificateVerifier
{
    public function verify(GlobalTierQualificationCertificate $certificate): bool
    {
        $payload = json_encode([
            'qualified_tier' => $certificate->qualifiedTier,
            'is_upgraded' => $certificate->isUpgraded,
            'is_downgraded' => $certificate->isDowngraded,
            'is_grace_period' => $certificate->isGracePeriodActive,
            'progress_percentage' => round($certificate->progressToNextTierPercentage, 4),
        ], JSON_THROW_ON_ERROR);

        $expectedHash = hash('sha256', $payload);

        return hash_equals($expectedHash, $certificate->signatureHash);
    }
}
