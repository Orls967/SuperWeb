<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Algorithms;

use InvalidArgumentException;

final class VitalSignTrendAnalyzer
{
    /**
     * Menganalisis rentet waktu tanda vital untuk mendeteksi dekompensasi klinis tersembunyi
     * menggunakan kemiringan tren (linear slope) dan variabilitas (standar deviasi).
     *
     * @param list<array{
     *     timestamp: string,
     *     heart_rate: int,
     *     systolic_bp: int,
     *     respiratory_rate: int,
     *     spo2: float,
     *     temperature: float
     * }> $history
     * @return array{
     *     hr_slope: float,
     *     sbp_slope: float,
     *     spo2_slope: float,
     *     is_deteriorating_rapidly: bool,
     *     decompensation_flags: list<string>,
     *     urgency_tier: string,
     *     certificate: array{
     *         algorithm: string,
     *         version: string,
     *         input_hash: string,
     *         signature: string
     *     }
     * }
     */
    public function analyze(array $history): array
    {
        $n = count($history);
        if ($n < 2) {
            throw new InvalidArgumentException('Minimal 2 titik data waktu vital diperlukan untuk analisis tren.');
        }

        $hrValues = array_column($history, 'heart_rate');
        $sbpValues = array_column($history, 'systolic_bp');
        $spo2Values = array_column($history, 'spo2');

        $hrSlope = $this->calculateSlope($hrValues);
        $sbpSlope = $this->calculateSlope($sbpValues);
        $spo2Slope = $this->calculateSlope($spo2Values);

        $flags = [];
        // Tanda dekompensasi syok/sepsis dini: HR naik signifikan bersamaan tekanan darah turun
        if ($hrSlope > 2.0 && $sbpSlope < -2.0) {
            $flags[] = 'SHOCK_INDEX_DIVERGENCE_HEMODYNAMIC_COLLAPSE_RISK';
        }

        // Tanda gagal napas impending: Spo2 turun bertahap
        if ($spo2Slope < -0.8) {
            $flags[] = 'RESPIRATORY_FAILURE_OXYGENATION_DESATURATION_TREND';
        }

        // Taki-aritmia progresif
        if ($hrSlope > 4.0) {
            $flags[] = 'RAPID_TACHYCARDIA_ESCALATION';
        }

        $isDeteriorating = count($flags) > 0;
        $urgencyTier = match (count($flags)) {
            0 => 'STABLE',
            1 => 'ELEVATED_WATCH',
            default => 'CRITICAL_RRT_ESCALATION',
        };

        $inputSerialized = json_encode($history, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $inputHash = hash('sha256', $inputSerialized);
        $sigPayload = "VSTA-V1:{$hrSlope}:{$sbpSlope}:{$spo2Slope}:{$urgencyTier}:{$inputHash}";
        $signature = hash_hmac('sha256', $sigPayload, 'HSP_SECRET_VITALS_TREND_2026');

        return [
            'hr_slope' => round($hrSlope, 4),
            'sbp_slope' => round($sbpSlope, 4),
            'spo2_slope' => round($spo2Slope, 4),
            'is_deteriorating_rapidly' => $isDeteriorating,
            'decompensation_flags' => $flags,
            'urgency_tier' => $urgencyTier,
            'certificate' => [
                'algorithm' => 'VitalSignTrendAnalyzer',
                'version' => '1.0.0',
                'input_hash' => $inputHash,
                'signature' => $signature,
            ],
        ];
    }

    /**
     * @param  list<float|int>  $yValues
     */
    private function calculateSlope(array $yValues): float
    {
        $n = count($yValues);
        $sumX = 0;
        $sumY = 0;
        $sumXY = 0;
        $sumXX = 0;

        for ($i = 0; $i < $n; $i++) {
            $x = $i + 1;
            $y = (float) $yValues[$i];
            $sumX += $x;
            $sumY += $y;
            $sumXY += ($x * $y);
            $sumXX += ($x * $x);
        }

        $denominator = ($n * $sumXX) - ($sumX * $sumX);
        if ($denominator == 0) {
            return 0.0;
        }

        return (($n * $sumXY) - ($sumX * $sumY)) / $denominator;
    }

    /**
     * @param  array<string, mixed>  $result
     * @param  list<array<string, mixed>>  $history
     */
    public function verifyCertificate(array $result, array $history): bool
    {
        $inputSerialized = json_encode($history, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $inputHash = hash('sha256', $inputSerialized);

        $cert = $result['certificate'] ?? [];
        if (($cert['input_hash'] ?? '') !== $inputHash) {
            return false;
        }

        $hrSlope = (float) ($result['hr_slope'] ?? 0.0);
        $sbpSlope = (float) ($result['sbp_slope'] ?? 0.0);
        $spo2Slope = (float) ($result['spo2_slope'] ?? 0.0);
        $urgencyTier = (string) ($result['urgency_tier'] ?? 'STABLE');

        $sigPayload = "VSTA-V1:{$hrSlope}:{$sbpSlope}:{$spo2Slope}:{$urgencyTier}:{$inputHash}";
        $expectedSig = hash_hmac('sha256', $sigPayload, 'HSP_SECRET_VITALS_TREND_2026');

        return hash_equals($expectedSig, (string) ($cert['signature'] ?? ''));
    }
}
