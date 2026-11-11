<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Domain\Algorithms\Valuation;

use InvalidArgumentException;

/**
 * Kalkulator Fasilitas Kredit Sindikasi, Amortisasi Pinjaman Korporat & Pemantauan Rasio Kovenan.
 * Seluruh kalkulasi beroperasi pada bilangan bulat minor unit (sen) tanpa tipe data float (KONSEP §A3).
 */
final class CorporateSyndicationCalculator
{
    /**
     * Menghitung Jadwal Amortisasi Pinjaman Pokok dan Beban Bunga (Anuitas Bilangan Bulat / Equal Installment).
     *
     * @param  int  $principalMinor  Pokok pinjaman dalam minor IDR.
     * @param  int  $annualInterestRateBps  Suku bunga tahunan dalam basis points (misal: 850 untuk 8.50%).
     * @param  int  $tenorMonths  Jangka waktu pinjaman dalam bulan (misal: 60 bulan).
     * @param  int  $gracePeriodMonths  Masa tenggang pokok (grace period) dalam bulan.
     * @return list<array{
     *     month: int,
     *     beginning_balance_minor: int,
     *     principal_payment_minor: int,
     *     interest_payment_minor: int,
     *     total_installment_minor: int,
     *     ending_balance_minor: int
     * }>
     */
    public static function generateAmortizationSchedule(
        int $principalMinor,
        int $annualInterestRateBps,
        int $tenorMonths,
        int $gracePeriodMonths = 0
    ): array {
        if ($principalMinor <= 0 || $tenorMonths <= 0 || $annualInterestRateBps < 0) {
            throw new InvalidArgumentException('Parameter pinjaman sindikasi tidak valid.');
        }

        if ($gracePeriodMonths >= $tenorMonths) {
            throw new InvalidArgumentException('Masa tenggang pokok tidak boleh melebihi atau sama dengan total tenor.');
        }

        $schedule = [];
        $balance = $principalMinor;
        $repaymentMonths = $tenorMonths - $gracePeriodMonths;
        $monthlyPrincipalBase = intdiv($principalMinor, $repaymentMonths);
        $monthlyPrincipalRemainder = $principalMinor % $repaymentMonths;

        for ($m = 1; $m <= $tenorMonths; $m++) {
            $beginning = $balance;

            // Bunga bulanan = Saldo Pokok * (Suku Bunga Tahunan / 12)
            $interestPayment = intdiv($beginning * $annualInterestRateBps, 12 * 10000);

            $principalPayment = 0;
            if ($m > $gracePeriodMonths) {
                $principalPayment = $monthlyPrincipalBase;
                // Alokasikan sisa pembulatan pada bulan pembayaran pertama
                if ($m === ($gracePeriodMonths + 1)) {
                    $principalPayment += $monthlyPrincipalRemainder;
                }
            }

            // Pastikan tidak membayar lebih dari sisa saldo
            $principalPayment = min($principalPayment, $balance);
            $ending = $balance - $principalPayment;
            $totalInstallment = $principalPayment + $interestPayment;

            $schedule[] = [
                'month' => $m,
                'beginning_balance_minor' => $beginning,
                'principal_payment_minor' => $principalPayment,
                'interest_payment_minor' => $interestPayment,
                'total_installment_minor' => $totalInstallment,
                'ending_balance_minor' => $ending,
            ];

            $balance = $ending;
        }

        return $schedule;
    }

    /**
     * Mengevaluasi Rasio Kovenan Utang (Debt Covenants Ratios).
     *
     * 1. DSCR (Debt Service Coverage Ratio) = (EBITDA - Pajak) / (Pokok Jatuh Tempo + Bunga) [Target >= 1.25x = 12500 bps]
     * 2. DER (Debt to Equity Ratio) = Total Utang Berbunga / Total Ekuitas [Maksimal <= 2.50x = 25000 bps]
     * 3. ICR (Interest Coverage Ratio) = EBIT / Beban Bunga [Target >= 2.00x = 20000 bps]
     * 4. Current Ratio = Aset Lancar / Kewajiban Lancar [Target >= 1.10x = 11000 bps]
     *
     * @return array{
     *     is_compliant: bool,
     *     dscr_bps: int,
     *     dscr_status: string,
     *     der_bps: int,
     *     der_status: string,
     *     icr_bps: int,
     *     icr_status: string,
     *     current_ratio_bps: int,
     *     current_ratio_status: string,
     *     breached_covenants: list<string>
     * }
     */
    public static function evaluateCovenants(
        int $ebitdaMinor,
        int $ebitMinor,
        int $interestExpenseMinor,
        int $principalDueMinor,
        int $taxPaidMinor,
        int $totalDebtMinor,
        int $totalEquityMinor,
        int $currentAssetsMinor,
        int $currentLiabilitiesMinor
    ): array {
        $breaches = [];

        // 1. DSCR
        $debtService = $principalDueMinor + $interestExpenseMinor;
        $operatingCash = max(0, $ebitdaMinor - $taxPaidMinor);
        $dscrBps = ($debtService > 0) ? intdiv($operatingCash * 10000, $debtService) : 99999;
        $dscrStatus = ($dscrBps >= 12500) ? 'COMPLIANT' : 'BREACH';
        if ($dscrStatus === 'BREACH') {
            $breaches[] = 'DSCR berada di bawah batas minimum 1.25x (aktual: '.number_format($dscrBps / 10000, 2).'x)';
        }

        // 2. DER
        $derBps = ($totalEquityMinor > 0) ? intdiv($totalDebtMinor * 10000, $totalEquityMinor) : 99999;
        $derStatus = ($derBps <= 25000) ? 'COMPLIANT' : 'BREACH';
        if ($derStatus === 'BREACH') {
            $breaches[] = 'DER melampaui batas maksimum 2.50x (aktual: '.number_format($derBps / 10000, 2).'x)';
        }

        // 3. ICR
        $icrBps = ($interestExpenseMinor > 0) ? intdiv($ebitMinor * 10000, $interestExpenseMinor) : 99999;
        $icrStatus = ($icrBps >= 20000) ? 'COMPLIANT' : 'BREACH';
        if ($icrStatus === 'BREACH') {
            $breaches[] = 'ICR berada di bawah batas minimum 2.00x (aktual: '.number_format($icrBps / 10000, 2).'x)';
        }

        // 4. Current Ratio
        $crBps = ($currentLiabilitiesMinor > 0) ? intdiv($currentAssetsMinor * 10000, $currentLiabilitiesMinor) : 99999;
        $crStatus = ($crBps >= 11000) ? 'COMPLIANT' : 'BREACH';
        if ($crStatus === 'BREACH') {
            $breaches[] = 'Current Ratio berada di bawah batas likuiditas 1.10x (aktual: '.number_format($crBps / 10000, 2).'x)';
        }

        return [
            'is_compliant' => empty($breaches),
            'dscr_bps' => $dscrBps,
            'dscr_status' => $dscrStatus,
            'der_bps' => $derBps,
            'der_status' => $derStatus,
            'icr_bps' => $icrBps,
            'icr_status' => $icrStatus,
            'current_ratio_bps' => $crBps,
            'current_ratio_status' => $crStatus,
            'breached_covenants' => $breaches,
        ];
    }
}
