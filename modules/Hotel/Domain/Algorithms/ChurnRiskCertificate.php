<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Algorithms;

/**
 * Data Nilai Sertifikat Penilaian Risiko Atrisi / Churn Tamu Hotel.
 */
final class ChurnRiskCertificate
{
    /**
     * @param  array<string, mixed>  $actionPlan
     */
    public function __construct(
        public readonly string $guestId,
        public readonly float $churnScore,
        public readonly string $riskCategory,
        public readonly int $daysSinceLastStay,
        public readonly int $totalStaysPastYear,
        public readonly int $unresolvedComplaintsCount,
        public readonly array $actionPlan,
        public readonly string $checksum,
    ) {}
}
