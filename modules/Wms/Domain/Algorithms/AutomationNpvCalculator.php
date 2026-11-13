<?php

declare(strict_types=1);

namespace Modules\Wms\Domain\Algorithms;

use InvalidArgumentException;
use Modules\Wms\Domain\Certificates\AutomationNpvCertificate;

/**
 * Algoritma J3: Perhitungan NPV & Payback rasional eksak untuk proposal otomasi gudang.
 *
 * Kompleksitas: O(T) dengan T = jumlah tahun arus kas.
 * Aritmetika: 100% rasional eksak memakai bcmath (tanpa float).
 * Pembulatan: NPV dibulatkan SEKALI di akhir dengan aturan HalfAwayFromZero; payback bulan memakai ceiling bulat.
 * Batas: rate_num > 0 dan rate_den > 0 (selain itu INVALID_RATE).
 */
final class AutomationNpvCalculator
{
    public const string ERROR_INVALID_RATE = 'INVALID_RATE';

    public const string PAYBACK_NONE = 'NO_PAYBACK';

    /**
     * @param  int  $investmentMinor  Investasi awal I0 (bilangan bulat IDR minor/rupiah)
     * @param  list<int>  $cashFlows  Arus kas tahunan CF_1..CF_T (bilangan bulat)
     * @param  int  $rateNum  Pembilang suku bunga diskonto r = rateNum / rateDen
     * @param  int  $rateDen  Penyebut suku bunga diskonto
     * @return array{
     *     npv: int,
     *     payback_months: int|null,
     *     payback_status: string,
     *     recommendation: string,
     *     certificate: AutomationNpvCertificate
     * }
     */
    public function evaluate(int $investmentMinor, array $cashFlows, int $rateNum, int $rateDen): array
    {
        if ($rateNum <= 0 || $rateDen <= 0) {
            throw new InvalidArgumentException(self::ERROR_INVALID_RATE);
        }

        $onePlusRNum = (string) ($rateDen + $rateNum);
        $denStr = (string) $rateDen;

        $horizon = count($cashFlows);
        $commonDen = '1';
        for ($i = 0; $i < $horizon; $i++) {
            $commonDen = bcmul($commonDen, $onePlusRNum, 0);
        }

        // Hitung sum_{t=1..T} CF_t * d^t * (d+n)^{T-t}
        $sumNumerator = '0';
        $steps = [];
        $cumCash = 0;
        $paybackMonths = null;

        foreach ($cashFlows as $idx => $cfRaw) {
            $t = $idx + 1;
            $cf = (int) $cfRaw;

            $powD = bcpow($denStr, (string) $t, 0);
            $powOnePlusRRest = bcpow($onePlusRNum, (string) ($horizon - $t), 0);
            $termNum = bcmul(bcmul((string) $cf, $powD, 0), $powOnePlusRRest, 0);
            $sumNumerator = bcadd($sumNumerator, $termNum, 0);

            if ($paybackMonths === null && $cf > 0) {
                $prevCum = $cumCash;
                $cumCash += $cf;
                if ($cumCash >= $investmentMinor) {
                    $remaining = max(0, $investmentMinor - $prevCum);
                    // ceil(12 * remaining / cf) memakai aritmetika bulat
                    $monthsInYear = intdiv((12 * $remaining) + $cf - 1, $cf);
                    $paybackMonths = (12 * ($t - 1)) + $monthsInYear;
                }
            } else {
                $cumCash += $cf;
            }

            $steps[] = [
                'year' => $t,
                'cash_flow' => $cf,
                'cumulative_undiscounted' => $cumCash,
            ];
        }

        $investNum = bcmul((string) $investmentMinor, $commonDen, 0);
        $netNumerator = bcsub($sumNumerator, $investNum, 0);
        $npv = $this->divideHalfAwayFromZero($netNumerator, $commonDen);

        $paybackStatus = $paybackMonths !== null ? 'ACHIEVED' : self::PAYBACK_NONE;
        $recommendation = $npv > 0 ? 'APPROVE' : 'REJECT';

        $canonicalInput = [
            'investment' => $investmentMinor,
            'cash_flows' => array_map('intval', $cashFlows),
            'rate_num' => $rateNum,
            'rate_den' => $rateDen,
        ];
        $resultSummary = [
            'npv' => $npv,
            'payback_months' => $paybackMonths,
            'payback_status' => $paybackStatus,
            'recommendation' => $recommendation,
        ];

        $certificate = AutomationNpvCertificate::issue($canonicalInput, $steps, $resultSummary);
        $certificate->verifyOrFail();

        return [
            'npv' => $npv,
            'payback_months' => $paybackMonths,
            'payback_status' => $paybackStatus,
            'recommendation' => $recommendation,
            'certificate' => $certificate,
        ];
    }

    /**
     * Pembagian rasional eksak dengan pembulatan setengah menjauhi nol (HalfAwayFromZero).
     */
    private function divideHalfAwayFromZero(string $numerator, string $denominator): int
    {
        $isNegative = str_starts_with($numerator, '-');
        $absNum = $isNegative ? substr($numerator, 1) : $numerator;

        $quotient = bcdiv($absNum, $denominator, 0);
        $remainder = bcmod($absNum, $denominator);
        $twiceRem = bcmul($remainder, '2', 0);

        if (bccomp($twiceRem, $denominator, 0) >= 0) {
            $quotient = bcadd($quotient, '1', 0);
        }

        $signed = ($isNegative && $quotient !== '0') ? ('-'.$quotient) : $quotient;

        return (int) $signed;
    }
}
