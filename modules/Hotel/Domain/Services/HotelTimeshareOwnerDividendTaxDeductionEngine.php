<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

class HotelTimeshareOwnerDividendTaxDeductionEngine
{
    /**
     * Menghitung rincian dividen bersih, potongan PPh, dan penyisihan dana cadangan perbaikan per investor.
     *
     * @return array{investor_id: string, gross_dividend_idr: int, sinking_fund_reserve_idr: int, tax_withheld_idr: int, net_take_home_idr: int, statement_hash: string}
     */
    public function computeInvestorNetTakeHome(
        string $investorId,
        int $grossDividendIdr,
        float $sinkingFundReservePct = 5.0,
        float $taxWithholdingPct = 10.0
    ): array {
        $sinkingFund = (int) round(($grossDividendIdr * $sinkingFundReservePct) / 100);
        $taxWithheld = (int) round(($grossDividendIdr * $taxWithholdingPct) / 100);
        $netTakeHome = max(0, $grossDividendIdr - $sinkingFund - $taxWithheld);

        $hash = hash('sha256', "{$investorId}:{$grossDividendIdr}:{$netTakeHome}");

        return [
            'investor_id' => $investorId,
            'gross_dividend_idr' => $grossDividendIdr,
            'sinking_fund_reserve_idr' => $sinkingFund,
            'tax_withheld_idr' => $taxWithheld,
            'net_take_home_idr' => $netTakeHome,
            'statement_hash' => $hash,
        ];
    }
}
