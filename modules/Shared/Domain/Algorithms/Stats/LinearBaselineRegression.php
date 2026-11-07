<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Stats;

use InvalidArgumentException;

/**
 * Algoritma M3: Regresi Linier Garis Dasar Konsumsi BBM vs Tonase-Jarak (Linear Baseline Regression)
 * Menggunakan estimasi kuadrat terkecil biasa (Ordinary Least Squares) dengan representasi rasional.
 *
 * Kompleksitas Waktu: O(N) di mana N adalah jumlah titik observasi historis.
 * Kompleksitas Ruang: O(N).
 */
final class LinearBaselineRegression
{
    public function __construct(
        private readonly ?LinearBaselineRegressionCertificateVerifier $verifier = null
    ) {}

    /**
     * @param  list<float|int>  $xPoints  Titik ton-km (misalnya ribuan ton-km)
     * @param  list<float|int>  $yPoints  Titik konsumsi BBM liter
     * @param  float|int  $newX  Titik ton-km baru yang akan dievaluasi
     * @param  float|int  $actualY  Titik konsumsi BBM aktual baru
     * @param  int  $thresholdBps  Ambang batas anomali dalam basis point (default: 1500 bps = 15%)
     */
    public function fitAndPredict(
        array $xPoints,
        array $yPoints,
        float|int $newX,
        float|int $actualY,
        int $thresholdBps = 1500
    ): LinearBaselineRegressionResult {
        $n = count($xPoints);
        if ($n < 2) {
            throw new InvalidArgumentException('Regresi membutuhkan minimal 2 titik data historis');
        }

        if ($n !== count($yPoints)) {
            throw new InvalidArgumentException('Jumlah titik X dan Y harus sama');
        }

        $sumX = 0.0;
        $sumY = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $sumX += (float) $xPoints[$i];
            $sumY += (float) $yPoints[$i];
        }

        $meanX = $sumX / $n;
        $meanY = $sumY / $n;

        $ssXX = 0.0;
        $ssXY = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $dx = ((float) $xPoints[$i]) - $meanX;
            $dy = ((float) $yPoints[$i]) - $meanY;
            $ssXX += $dx * $dx;
            $ssXY += $dx * $dy;
        }

        if (abs($ssXX) < 1e-9) {
            throw new InvalidArgumentException('Varians X bernilai nol; tidak dapat menghitung garis regresi');
        }

        $slope = $ssXY / $ssXX;
        $intercept = $meanY - ($slope * $meanX);

        // Representasi rasional pecahan slope
        $slopeNum = (int) round($ssXY * 2);
        $slopeDen = (int) round($ssXX * 2);
        $gcdSlope = $this->gcd($slopeNum, $slopeDen);
        $simplifiedSlopeNum = intdiv($slopeNum, $gcdSlope);
        $simplifiedSlopeDen = intdiv($slopeDen, $gcdSlope);

        $interceptNum = (int) round($intercept);
        $interceptDen = 1;

        // Hitung residu historis
        $residuals = [];
        for ($i = 0; $i < $n; $i++) {
            $predI = $intercept + ($slope * (float) $xPoints[$i]);
            $residuals[] = round(((float) $yPoints[$i]) - $predI, 4);
        }

        // Prediksi titik baru
        $predictedY = $intercept + ($slope * (float) $newX);
        $excessY = ((float) $actualY) - $predictedY;

        $excessBps = 0;
        if ($predictedY > 0) {
            $excessBps = (int) floor(($excessY / $predictedY) * 10000);
        }

        $isAnomaly = ($excessBps > $thresholdBps);

        $certificateData = [
            'slope_num' => $simplifiedSlopeNum,
            'slope_den' => $simplifiedSlopeDen,
            'intercept' => $interceptNum,
            'new_x' => (float) $newX,
            'predicted_y' => round($predictedY, 4),
            'actual_y' => (float) $actualY,
            'excess_bps' => $excessBps,
            'is_anomaly' => $isAnomaly,
            'algorithm' => 'LinearBaselineRegression_v1',
        ];

        $certificateHash = hash('sha256', json_encode($certificateData, JSON_THROW_ON_ERROR));

        $result = new LinearBaselineRegressionResult(
            slope: [
                'numerator' => $simplifiedSlopeNum,
                'denominator' => $simplifiedSlopeDen,
                'float_val' => $slope,
            ],
            intercept: [
                'numerator' => $interceptNum,
                'denominator' => $interceptDen,
                'float_val' => $intercept,
            ],
            residuals: $residuals,
            newX: (float) $newX,
            predictedY: $predictedY,
            actualY: (float) $actualY,
            excessY: $excessY,
            excessBps: $excessBps,
            thresholdBps: $thresholdBps,
            isAnomaly: $isAnomaly,
            certificateHash: $certificateHash,
            certificateData: $certificateData
        );

        $verifier = $this->verifier ?? new LinearBaselineRegressionCertificateVerifier;
        $verifier->verify($result);

        return $result;
    }

    private function gcd(int $a, int $b): int
    {
        $a = abs($a);
        $b = abs($b);

        while ($b !== 0) {
            $t = $b;
            $b = $a % $b;
            $a = $t;
        }

        return $a ?: 1;
    }
}
