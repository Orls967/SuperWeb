<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Application\Actions;

use InvalidArgumentException;

class MonitorDebtCovenantRatiosAction
{
    /**
     * @param  float  $maxDeRatio  Maximum Debt-to-Equity limit (e.g. 2.50)
     * @param  float  $minIcr  Minimum Interest Coverage Ratio (e.g. 3.00)
     * @return array{
     *     total_debt_idr: int,
     *     total_equity_idr: int,
     *     ebitda_idr: int,
     *     annual_interest_idr: int,
     *     debt_to_equity_ratio: float,
     *     interest_coverage_ratio: float,
     *     max_de_ratio_limit: float,
     *     min_icr_limit: float,
     *     covenant_status: string, // 'COMPLIANT'|'WARNING'|'BREACH'
     *     is_compliant: bool
     * }
     */
    public function execute(
        int $totalDebtIdr,
        int $totalEquityIdr,
        int $ebitdaIdr,
        int $annualInterestExpenseIdr,
        float $maxDeRatio = 2.50,
        float $minIcr = 3.00
    ): array {
        if ($totalDebtIdr < 0 || $totalEquityIdr < 0) {
            throw new InvalidArgumentException('Debt and equity figures cannot be negative.');
        }

        $deRatio = $totalEquityIdr > 0 ? round($totalDebtIdr / $totalEquityIdr, 2) : 999.0;
        $icr = $annualInterestExpenseIdr > 0 ? round($ebitdaIdr / $annualInterestExpenseIdr, 2) : 999.0;

        $covenantStatus = 'COMPLIANT';
        if ($deRatio > $maxDeRatio || $icr < $minIcr) {
            $covenantStatus = 'BREACH';
        } elseif ($deRatio > ($maxDeRatio * 0.9) || $icr < ($minIcr * 1.15)) {
            $covenantStatus = 'WARNING';
        }

        return [
            'total_debt_idr' => $totalDebtIdr,
            'total_equity_idr' => $totalEquityIdr,
            'ebitda_idr' => $ebitdaIdr,
            'annual_interest_idr' => $annualInterestExpenseIdr,
            'debt_to_equity_ratio' => $deRatio,
            'interest_coverage_ratio' => $icr,
            'max_de_ratio_limit' => $maxDeRatio,
            'min_icr_limit' => $minIcr,
            'covenant_status' => $covenantStatus,
            'is_compliant' => $covenantStatus !== 'BREACH',
        ];
    }
}
