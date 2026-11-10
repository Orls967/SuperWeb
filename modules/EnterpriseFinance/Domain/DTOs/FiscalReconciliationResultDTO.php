<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Domain\DTOs;

final readonly class FiscalReconciliationResultDTO
{
    public function __construct(
        public string $fiscalYear,
        public string $entityCode,
        public int $commercialNetProfitMinor,
        public int $totalPositiveCorrectionMinor,
        public int $totalNegativeCorrectionMinor,
        public int $fiscalTaxableIncomeMinor,
        public int $corporateTaxDueMinor,
        public int $effectiveTaxRateBps,
        public int $taxCreditsMinor,
        public int $underpaymentPph29Minor,
        public string $reconciledAt
    ) {}

    public function toArray(): array
    {
        return [
            'fiscal_year' => $this->fiscalYear,
            'entity_code' => $this->entityCode,
            'commercial_net_profit_minor' => $this->commercialNetProfitMinor,
            'total_positive_correction_minor' => $this->totalPositiveCorrectionMinor,
            'total_negative_correction_minor' => $this->totalNegativeCorrectionMinor,
            'fiscal_taxable_income_minor' => $this->fiscalTaxableIncomeMinor,
            'corporate_tax_due_minor' => $this->corporateTaxDueMinor,
            'effective_tax_rate_bps' => $this->effectiveTaxRateBps,
            'tax_credits_minor' => $this->taxCreditsMinor,
            'underpayment_pph29_minor' => $this->underpaymentPph29Minor,
            'reconciled_at' => $this->reconciledAt,
        ];
    }
}
