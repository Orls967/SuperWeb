<?php

declare(strict_types=1);

namespace Modules\Hotel\Application\Actions;

use Modules\Hotel\Domain\Algorithms\ChurnRiskCertificate;
use Modules\Hotel\Domain\Algorithms\ChurnRiskCertificateVerifier;
use Modules\Hotel\Domain\Algorithms\ChurnRiskScorer;
use RuntimeException;

/**
 * Tindakan Penilaian Risiko Atrisi / Churn Tamu Hotel (Algoritma H9).
 */
final class ScoreGuestChurnRiskAction
{
    public function __construct(
        private readonly ChurnRiskScorer $scorer,
        private readonly ChurnRiskCertificateVerifier $verifier,
    ) {}

    public function execute(
        string $guestId,
        int $daysSinceLastStay,
        int $totalStaysPastYear,
        int $averageSpendPerStayIdr,
        int $unresolvedComplaintsCount,
        float $averageReviewRating
    ): ChurnRiskCertificate {
        $cert = $this->scorer->score(
            guestId: $guestId,
            daysSinceLastStay: $daysSinceLastStay,
            totalStaysPastYear: $totalStaysPastYear,
            averageSpendPerStayIdr: $averageSpendPerStayIdr,
            unresolvedComplaintsCount: $unresolvedComplaintsCount,
            averageReviewRating: $averageReviewRating
        );

        if (! $this->verifier->verify($cert)) {
            throw new RuntimeException("Verifikasi sertifikat ChurnRisk gagal untuk tamu {$guestId}");
        }

        return $cert;
    }
}
