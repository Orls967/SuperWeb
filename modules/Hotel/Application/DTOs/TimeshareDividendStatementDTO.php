<?php

declare(strict_types=1);

namespace Modules\Hotel\Application\DTOs;

final class TimeshareDividendStatementDTO
{
    public function __construct(
        public readonly string $statementId,
        public readonly string $unitCode,
        public readonly string $investorUserId,
        public readonly int $tokenSharesOwned,
        public readonly int $rentalPoolGrossIncomeIdr,
        public readonly int $resortManagementFeeDeductedIdr,
        public readonly int $taxWithheldIdr,
        public readonly int $netPayableDividendIdr,
        public readonly string $distributionPeriod
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'statement_id' => $this->statementId,
            'unit_code' => $this->unitCode,
            'investor_user_id' => $this->investorUserId,
            'token_shares_owned' => $this->tokenSharesOwned,
            'rental_pool_gross_income_idr' => $this->rentalPoolGrossIncomeIdr,
            'resort_management_fee_deducted_idr' => $this->resortManagementFeeDeductedIdr,
            'tax_withheld_idr' => $this->taxWithheldIdr,
            'net_payable_dividend_idr' => $this->netPayableDividendIdr,
            'distribution_period' => $this->distributionPeriod,
        ];
    }
}
