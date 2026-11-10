<?php

declare(strict_types=1);

namespace Modules\Intercompany\Domain\DTOs;

use Modules\Intercompany\Domain\Algorithms\Certificates\IntercompanyAmortizationCertificate;

final class LoanAmortizationResultDTO
{
    /**
     * @param  array<int, LoanAmortizationScheduleItemDTO>  $schedules
     */
    public function __construct(
        public readonly int $totalPrincipalMinor,
        public readonly int $baseRateBps,
        public readonly int $spreadBps,
        public readonly int $withholdingTaxBps,
        public readonly int $totalInterestGrossMinor,
        public readonly int $totalTaxWithheldMinor,
        public readonly int $totalInterestNetMinor,
        public readonly array $schedules,
        public readonly IntercompanyAmortizationCertificate $certificate
    ) {}
}
