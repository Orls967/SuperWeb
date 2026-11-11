<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Domain\DTOs;

final readonly class CorporateDividendCalculationResultDTO
{
    public function __construct(
        public string $subsidiaryEntity,
        public int $netProfitMinor,
        public int $retainedEarningsMinor,
        public int $payoutRatioBps,
        public int $statutoryReserveDeductionMinor,
        public int $distributableDividendMinor,
        public int $parentDividendShareMinor,
        public int $nciDividendShareMinor,
        public bool $passesSolvencyTest,
        public string $resolutionReference
    ) {}

    public function toArray(): array
    {
        return [
            'subsidiary_entity' => $this->subsidiaryEntity,
            'net_profit_minor' => $this->netProfitMinor,
            'retained_earnings_minor' => $this->retainedEarningsMinor,
            'payout_ratio_bps' => $this->payoutRatioBps,
            'statutory_reserve_deduction_minor' => $this->statutoryReserveDeductionMinor,
            'distributable_dividend_minor' => $this->distributableDividendMinor,
            'parent_dividend_share_minor' => $this->parentDividendShareMinor,
            'nci_dividend_share_minor' => $this->nciDividendShareMinor,
            'passes_solvency_test' => $this->passesSolvencyTest,
            'resolution_reference' => $this->resolutionReference,
        ];
    }
}
