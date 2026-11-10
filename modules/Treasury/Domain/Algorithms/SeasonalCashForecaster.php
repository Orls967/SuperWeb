<?php

declare(strict_types=1);

namespace Modules\Treasury\Domain\Algorithms;

use InvalidArgumentException;
use Modules\Treasury\Domain\Algorithms\Certificates\SeasonalCashForecastCertificate;
use Modules\Treasury\Domain\DTOs\ForecastResultDTO;

/**
 * Algoritma D3: Peramalan Arus Kas 13-Minggu Berbasis Dekomposisi Musiman Multiplikatif & OLS Rasional.
 *
 * Kompleksitas Waktu: O(n)
 * Kompleksitas Ruang: O(n)
 * Aritmetika: Bilangan bulat / pecahan rasional tanpa float.
 */
final class SeasonalCashForecaster
{
    /**
     * @param  list<int>  $historyWeeks
     */
    public function forecast(array $historyWeeks, int $seasonPeriod = 4, int $horizonWeeks = 13): ForecastResultDTO
    {
        $n = count($historyWeeks);

        if ($n < (2 * $seasonPeriod)) {
            throw new InvalidArgumentException("INSUFFICIENT_HISTORY: Riwayat arus kas ({$n} minggu) kurang dari 2 siklus musiman minimum (".(2 * $seasonPeriod).' minggu).');
        }

        if (($n % $seasonPeriod) !== 0) {
            throw new InvalidArgumentException("HISTORY_NOT_FULL_CYCLES: Jumlah minggu ({$n}) wajib kelipatan dari periode musiman ({$seasonPeriod}).");
        }

        $cycles = intdiv($n, $seasonPeriod);
        $sumY = array_sum($historyWeeks);

        // Overall Mean (rasional sumY / n)
        // Seasonal Means: m_k = sum_cycles(y) / cycles
        $seasonalMeans = [];
        $seasonalSums = array_fill(0, $seasonPeriod, 0);

        for ($t = 0; $t < $n; $t++) {
            $k = $t % $seasonPeriod;
            $seasonalSums[$k] += $historyWeeks[$t];
        }

        for ($k = 0; $k < $seasonPeriod; $k++) {
            $seasonalMeans[$k] = intdiv($seasonalSums[$k], $cycles);
        }

        // Seasonal indices in permille (floor(1000 * m_k / overallMean))
        $seasonalIndicesPermille = [];
        for ($k = 0; $k < $seasonPeriod; $k++) {
            // (1000 * m_k * n) / sumY
            $seasonalIndicesPermille[$k] = intdiv(1000 * $seasonalMeans[$k] * $n, $sumY);
        }

        // Deseasonalize d_t = y_t / S_k = (y_t * sumY) / (n * m_k)
        // Kita hitung OLS: sum((t - t_bar) * d_t) / sum((t - t_bar)^2)
        // Karena t integer 1..n, t_bar = (n + 1) / 2
        // Kita gunakan 2 * t - (n + 1) untuk integer murni
        // Denominator of OLS: sum_{t=1}^n (t - (n+1)/2)^2 = n * (n^2 - 1) / 12
        $ss_t_num = $n * ($n * $n - 1);
        $ss_t_den = 12;
        $ss_t_gcd = RationalRate::gcd($ss_t_num, $ss_t_den);
        $ss_t_num = intdiv($ss_t_num, $ss_t_gcd);
        $ss_t_den = intdiv($ss_t_den, $ss_t_gcd); // for n=12, ss_t = 143/1

        // Numerator of slope b: sum_{t=1}^n (t - (n+1)/2) * d_t
        // d_t = (y_t * sumY) / (n * m_k(t))
        // Common denominator for d_t: let D_k = n * m_k
        // We accumulate numerator and denominator of sum((2*t - (n+1)) * y_t * sumY / (2 * n * m_k))
        $slopeNumerator = 0;
        $slopeDenominator = 1;

        // Common multiple for m_k to keep exact fraction
        $lcm_m = (string) $seasonalMeans[0];
        for ($k = 1; $k < $seasonPeriod; $k++) {
            $mStr = (string) $seasonalMeans[$k];
            $gcd = self::bcGcd($lcm_m, $mStr);
            $lcm_m = bcdiv(bcmul($lcm_m, $mStr), $gcd);
        }

        // Common denominator for d_t: D = n * lcm_m
        // d_t * D = y_t * sumY * (lcm_m / m_k)
        $scaledSumD = '0';
        $scaledCov = '0'; // sum (2t - (n+1)) * (d_t * D)
        $sumYStr = (string) $sumY;

        for ($t = 1; $t <= $n; $t++) {
            $k = ($t - 1) % $seasonPeriod;
            $mult = bcdiv($lcm_m, (string) $seasonalMeans[$k]);
            $dt_scaled = bcmul(bcmul((string) $historyWeeks[$t - 1], $sumYStr), $mult); // d_t * n * lcm_m

            $scaledSumD = bcadd($scaledSumD, $dt_scaled);
            $weight = (string) (2 * $t - ($n + 1));
            $scaledCov = bcadd($scaledCov, bcmul($weight, $dt_scaled));
        }

        // b = Cov(t, d) / Var(t)
        // Cov(t, d) = scaledCov / (2 * n * lcm_m)
        // Var(t) = ss_t_num / ss_t_den
        // b = (scaledCov * ss_t_den) / (2 * n * lcm_m * ss_t_num)
        $b_num = bcmul($scaledCov, (string) $ss_t_den);
        $b_den = bcmul(bcmul((string) (2 * $n), $lcm_m), (string) $ss_t_num);
        $gcd_b = self::bcGcd($b_num, $b_den);
        $b_num = bcdiv($b_num, $gcd_b);
        $b_den = bcdiv($b_den, $gcd_b);
        if (bccomp($b_den, '0') < 0) {
            $b_num = bcmul($b_num, '-1');
            $b_den = bcmul($b_den, '-1');
        }

        // a = d_bar - b * t_bar
        // d_bar = scaledSumD / (n * n * lcm_m)
        // t_bar = (n + 1) / 2
        // a = [scaledSumD * 2 * b_den - (n+1) * b_num * (n * n * lcm_m)] / [2 * n * n * lcm_m * b_den]
        $nSquaredLcm = bcmul((string) ($n * $n), $lcm_m);
        $a_term1 = bcmul(bcmul($scaledSumD, '2'), $b_den);
        $a_term2 = bcmul((string) ($n + 1), bcmul($b_num, $nSquaredLcm));
        $a_num = bcsub($a_term1, $a_term2);
        $a_den = bcmul(bcmul((string) (2 * $n * $n), $lcm_m), $b_den);
        $gcd_a = self::bcGcd($a_num, $a_den);
        $a_num = bcdiv($a_num, $gcd_a);
        $a_den = bcdiv($a_den, $gcd_a);
        if (bccomp($a_den, '0') < 0) {
            $a_num = bcmul($a_num, '-1');
            $a_den = bcmul($a_den, '-1');
        }

        // Proyeksi 13 minggu
        // y_hat_t = floor((a + b * t) * S_k)
        // a + b * t = (a_num * b_den + b_num * a_den * t) / (a_den * b_den)
        // S_k = (m_k * n) / sumY
        $projectedWeeks = [];
        $common_den_ab = bcmul($a_den, $b_den);

        for ($weekIdx = 1; $weekIdx <= $horizonWeeks; $weekIdx++) {
            $t = (string) ($n + $weekIdx);
            $k = ($n + $weekIdx - 1) % $seasonPeriod;

            $trend_num = bcadd(bcmul($a_num, $b_den), bcmul(bcmul($b_num, $a_den), $t));
            $trend_den = $common_den_ab;

            // Multiply by S_k = (m_k * n) / sumY
            $proj_num = bcmul($trend_num, (string) ($seasonalMeans[$k] * $n));
            $proj_den = bcmul($trend_den, $sumYStr);

            // Floor division
            $projected = (int) bcdiv($proj_num, $proj_den, 0);
            $rem = bcmod($proj_num, $proj_den);
            if (bccomp($proj_num, '0') < 0 && bccomp($rem, '0') !== 0) {
                $projected--;
            }
            $projectedWeeks[] = $projected;
        }

        $canonicalInputs = [
            'history' => $historyWeeks,
            'horizon_weeks' => $horizonWeeks,
            'season_period' => $seasonPeriod,
        ];

        $steps = [
            'overall_mean' => $sumY / $n,
            'seasonal_means' => $seasonalMeans,
            'seasonal_indices_permille' => $seasonalIndicesPermille,
            'intercept' => "{$a_num}/{$a_den}",
            'slope' => "{$b_num}/{$b_den}",
        ];

        $results = [
            'projected_sum' => array_sum($projectedWeeks),
            'projected_weeks' => $projectedWeeks,
        ];

        $certificate = SeasonalCashForecastCertificate::create($canonicalInputs, $steps, $results);

        return new ForecastResultDTO(
            overallMean: (float) ($sumY / $n),
            seasonalMeans: $seasonalMeans,
            seasonalIndicesPermille: $seasonalIndicesPermille,
            interceptNumerator: (int) $a_num,
            interceptDenominator: (int) $a_den,
            slopeNumerator: (int) $b_num,
            slopeDenominator: (int) $b_den,
            projectedWeeks: $projectedWeeks,
            certificate: $certificate
        );
    }

    private static function bcGcd(string $a, string $b): string
    {
        $a = ltrim($a, '-');
        $b = ltrim($b, '-');
        while (bccomp($b, '0') !== 0) {
            $t = $b;
            $b = bcmod($a, $b);
            $a = $t;
        }

        return bccomp($a, '0') === 0 ? '1' : $a;
    }
}
