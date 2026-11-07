<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Algorithms;

/**
 * Verifikator Matematis untuk ChurnRiskCertificate.
 */
final class ChurnRiskCertificateVerifier
{
    public function verify(ChurnRiskCertificate $cert): bool
    {
        // 1. Verifikasi Range Skor (0.00 hingga 1.00)
        if ($cert->churnScore < 0.0 || $cert->churnScore > 1.0) {
            return false;
        }

        // 2. Verifikasi Konsistensi Kategori Risiko
        $expectedCategory = match (true) {
            $cert->churnScore >= 0.65 => 'HIGH_RISK',
            $cert->churnScore >= 0.30 => 'MEDIUM_RISK',
            default => 'LOW_RISK',
        };

        if ($cert->riskCategory !== $expectedCategory) {
            return false;
        }

        // 3. Verifikasi Checksum Hash
        $expectedChecksum = hash('sha256', sprintf(
            '%s|%.4f|%s|%d|%d|%d',
            $cert->guestId,
            $cert->churnScore,
            $cert->riskCategory,
            $cert->daysSinceLastStay,
            $cert->totalStaysPastYear,
            $cert->unresolvedComplaintsCount
        ));

        return hash_equals($expectedChecksum, $cert->checksum);
    }
}
