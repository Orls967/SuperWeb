<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Domain\DTOs;

use Modules\EnterpriseFinance\Domain\Algorithms\Certificates\SyndicatedLoanCovenantCertificate;

final class SyndicatedLoanCovenantResultDTO
{
    public function __construct(
        public readonly string $facilityCode,
        public readonly string $evaluationPeriod,
        public readonly int $dscrPermille,
        public readonly int $derPermille,
        public readonly int $iscrPermille,
        public readonly int $currentRatioPermille,
        public readonly bool $isDscrCompliant,
        public readonly bool $isDerCompliant,
        public readonly bool $isIscrCompliant,
        public readonly bool $isCurrentRatioCompliant,
        public readonly bool $isFullyCompliant,
        public readonly SyndicatedLoanCovenantCertificate $certificate
    ) {}
}
