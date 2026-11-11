<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Domain\DTOs;

final class SyndicatedLoanCovenantInputDTO
{
    /**
     * @param array{
     *     min_dscr_permille?: int,
     *     max_der_permille?: int,
     *     min_iscr_permille?: int,
     *     min_current_ratio_permille?: int
     * } $thresholds
     */
    public function __construct(
        public readonly string $facilityCode,
        public readonly string $evaluationPeriod,
        public readonly int $ebitdaMinor,
        public readonly int $taxPaidMinor,
        public readonly int $debtServiceMinor,
        public readonly int $totalInterestBearingDebtMinor,
        public readonly int $totalEquityMinor,
        public readonly int $ebitMinor,
        public readonly int $interestExpenseMinor,
        public readonly int $currentAssetsMinor,
        public readonly int $currentLiabilitiesMinor,
        public readonly array $thresholds = []
    ) {}
}
