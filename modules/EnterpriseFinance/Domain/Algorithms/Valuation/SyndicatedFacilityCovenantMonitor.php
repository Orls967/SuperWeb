<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Domain\Algorithms\Valuation;

use InvalidArgumentException;

/**
 * Pemantau Sensitivitas Kovenan Utang terhadap Kejutan Suku Bunga Makroekonomi (Interest Rate Stress Testing).
 * Seluruh kalkulasi beroperasi pada bilangan bulat minor unit (sen) tanpa tipe data float (KONSEP §A3).
 */
final class SyndicatedFacilityCovenantMonitor
{
    /**
     * Menguji Ketahanan Rasio Kovenan ICR dan DSCR di Bawah Skenario Lonjakan Suku Bunga Pinjaman.
     *
     * @param  int  $principalOutstandingMinor  Sisa saldo pokok pinjaman sindikasi.
     * @param  int  $baselineAnnualRateBps  Suku bunga acuan tahunan (basis points).
     * @return array{
     *     baseline_icr_bps: int,
     *     icr_shock_plus_100bps: int,
     *     icr_shock_plus_250bps: int,
     *     icr_shock_plus_500bps: int,
     *     baseline_dscr_bps: int,
     *     dscr_shock_plus_250bps: int,
     *     dscr_shock_plus_500bps: int,
     *     is_vulnerable_to_rate_hike: bool
     * }
     */
    public static function stressTestInterestRateHikes(
        int $principalOutstandingMinor,
        int $baselineAnnualRateBps,
        int $ebitdaMinor,
        int $ebitMinor,
        int $annualPrincipalDueMinor
    ): array {
        if ($principalOutstandingMinor <= 0 || $baselineAnnualRateBps <= 0) {
            throw new InvalidArgumentException('Pokok pinjaman dan suku bunga acuan harus lebih besar dari nol.');
        }

        // 1. Beban Bunga Baseline
        $baseInterest = intdiv($principalOutstandingMinor * $baselineAnnualRateBps, 10000);
        $baseIcr = ($baseInterest > 0) ? intdiv($ebitMinor * 10000, $baseInterest) : 99999;
        $baseDscr = ($annualPrincipalDueMinor + $baseInterest > 0)
            ? intdiv($ebitdaMinor * 10000, $annualPrincipalDueMinor + $baseInterest)
            : 99999;

        // 2. Kejutan Suku Bunga +100 bps (+1.00%)
        $rate100 = $baselineAnnualRateBps + 100;
        $interest100 = intdiv($principalOutstandingMinor * $rate100, 10000);
        $icr100 = ($interest100 > 0) ? intdiv($ebitMinor * 10000, $interest100) : 99999;

        // 3. Kejutan Suku Bunga +250 bps (+2.50%)
        $rate250 = $baselineAnnualRateBps + 250;
        $interest250 = intdiv($principalOutstandingMinor * $rate250, 10000);
        $icr250 = ($interest250 > 0) ? intdiv($ebitMinor * 10000, $interest250) : 99999;
        $dscr250 = ($annualPrincipalDueMinor + $interest250 > 0)
            ? intdiv($ebitdaMinor * 10000, $annualPrincipalDueMinor + $interest250)
            : 99999;

        // 4. Kejutan Ekstrem Suku Bunga +500 bps (+5.00%)
        $rate500 = $baselineAnnualRateBps + 500;
        $interest500 = intdiv($principalOutstandingMinor * $rate500, 10000);
        $icr500 = ($interest500 > 0) ? intdiv($ebitMinor * 10000, $interest500) : 99999;
        $dscr500 = ($annualPrincipalDueMinor + $interest500 > 0)
            ? intdiv($ebitdaMinor * 10000, $annualPrincipalDueMinor + $interest500)
            : 99999;

        // Rentan jika pada kenaikan 250 bps DSCR jatuh di bawah 1.25x (12500 bps) atau ICR di bawah 2.0x (20000 bps)
        $isVulnerable = ($dscr250 < 12500 || $icr250 < 20000);

        return [
            'baseline_icr_bps' => $baseIcr,
            'icr_shock_plus_100bps' => $icr100,
            'icr_shock_plus_250bps' => $icr250,
            'icr_shock_plus_500bps' => $icr500,
            'baseline_dscr_bps' => $baseDscr,
            'dscr_shock_plus_250bps' => $dscr250,
            'dscr_shock_plus_500bps' => $dscr500,
            'is_vulnerable_to_rate_hike' => $isVulnerable,
        ];
    }
}
