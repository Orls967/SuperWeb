<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Aviation;

/**
 * Algoritma A6 Shared: Fare Bucket Yield Optimizer.
 *
 * Mengoptimalkan tingkat proteksi kursi (protection levels) dan batas pemesanan
 * (booking limits) untuk kelas tarif bersarang menggunakan metode EMSR-b diskret.
 */
class FareBucketYieldOptimizer
{
    /**
     * Hitung proteksi bertingkat dan hasilkan YieldOptimizerResult bersertifikat.
     *
     * @param  array<int, array{code: string, fare: int, mean_demand: float|int, std_dev: float|int}>  $classes
     */
    public function optimize(int $totalCapacity, array $classes): YieldOptimizerResult
    {
        usort($classes, fn ($a, $b) => $b['fare'] <=> $a['fare']);

        $m = count($classes);
        if ($m === 0) {
            $cert = YieldOptimizerCertificate::create($totalCapacity, [], 'EMSR-b-v1.0');

            return new YieldOptimizerResult($totalCapacity, [], $cert);
        }

        $protectionLevels = array_fill(0, $m, 0);
        $bookingLimits = array_fill(0, $m, 0);
        $bookingLimits[0] = $totalCapacity;

        $cumMean = 0.0;
        $cumVar = 0.0;
        $weightedRevenueSum = 0.0;

        for ($j = 0; $j < $m - 1; $j++) {
            $mean = (float) $classes[$j]['mean_demand'];
            $stdDev = max(0.1, (float) $classes[$j]['std_dev']);
            $fare = (float) $classes[$j]['fare'];

            $cumMean += $mean;
            $cumVar += ($stdDev * $stdDev);
            $weightedRevenueSum += ($fare * $mean);

            $cumStdDev = sqrt($cumVar);
            $avgFare = $cumMean > 0 ? ($weightedRevenueSum / $cumMean) : $fare;

            $nextFare = (float) $classes[$j + 1]['fare'];
            $criticalRatio = $avgFare > 0 ? min(0.9999, max(0.0001, $nextFare / $avgFare)) : 0.5;

            $z = $this->inverseStandardNormalCdf(1.0 - $criticalRatio);
            $s = (int) ceil($cumMean + ($z * $cumStdDev));
            $s = max(0, min($totalCapacity, $s));

            $protectionLevels[$j] = $s;
            $bookingLimits[$j + 1] = max(0, $totalCapacity - $s);
        }

        $buckets = [];
        for ($i = 0; $i < $m; $i++) {
            $buckets[] = [
                'code' => $classes[$i]['code'],
                'fare' => $classes[$i]['fare'],
                'protection_level' => $protectionLevels[$i],
                'booking_limit' => $bookingLimits[$i],
            ];
        }

        $certificate = YieldOptimizerCertificate::create($totalCapacity, $buckets, 'EMSR-b-v1.0');

        return new YieldOptimizerResult($totalCapacity, $buckets, $certificate);
    }

    public function inverseStandardNormalCdf(float $p): float
    {
        if ($p <= 0.0) {
            return -5.0;
        }
        if ($p >= 1.0) {
            return 5.0;
        }

        $a = [
            -3.969683028665376e+01,
            2.209460984245205e+02,
            -2.759285104469687e+02,
            1.383577518672690e+02,
            -3.066479806614716e+01,
            2.506628277459239e+00,
        ];

        $b = [
            -5.447609879822406e+01,
            1.615858368580409e+02,
            -1.556989798598866e+02,
            6.680131188771972e+01,
            -1.328068155288572e+01,
        ];

        $c = [
            -7.784894002430293e-03,
            -3.223964580411365e-01,
            -2.400758277161838e+00,
            -2.549732539343734e+00,
            4.374664141464968e+00,
            2.938163982698783e+00,
        ];

        $d = [
            7.784695709041462e-03,
            3.224671290700398e-01,
            2.445134137142996e+00,
            3.754408661907416e+00,
        ];

        $pLow = 0.02425;
        $pHigh = 1.0 - $pLow;

        if ($p < $pLow) {
            $q = sqrt(-2.0 * log($p));

            return ((((($c[0] * $q + $c[1]) * $q + $c[2]) * $q + $c[3]) * $q + $c[4]) * $q + $c[5]) /
                (((($d[0] * $q + $d[1]) * $q + $d[2]) * $q + $d[3]) * $q + 1.0);
        }

        if ($p <= $pHigh) {
            $q = $p - 0.5;
            $r = $q * $q;

            return ((((($a[0] * $r + $a[1]) * $r + $a[2]) * $r + $a[3]) * $r + $a[4]) * $r + $a[5]) * $q /
                ((((($b[0] * $r + $b[1]) * $r + $b[2]) * $r + $b[3]) * $r + $b[4]) * $r + 1.0);
        }

        $q = sqrt(-2.0 * log(1.0 - $p));

        return -((((($c[0] * $q + $c[1]) * $q + $c[2]) * $q + $c[3]) * $q + $c[4]) * $q + $c[5]) /
            (((($d[0] * $q + $d[1]) * $q + $d[2]) * $q + $d[3]) * $q + 1.0);
    }
}
