<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Domain\DTOs;

use Modules\EnterpriseFinance\Domain\Algorithms\Certificates\ConsolidatedTrialBalanceCertificate;

final class ConsolidatedTrialBalanceResultDTO
{
    /**
     * @param  list<AggregatedAccountSummaryDTO>  $aggregatedAccounts
     */
    public function __construct(
        public readonly string $period,
        public readonly string $reportingCurrency,
        public readonly int $totalEntitiesCount,
        public readonly int $totalDebitMinor,
        public readonly int $totalCreditMinor,
        public readonly int $deltaBalanceMinor,
        public readonly bool $isZeroSumBalanced,
        public readonly array $aggregatedAccounts,
        public readonly ConsolidatedTrialBalanceCertificate $certificate
    ) {}
}
