<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Algorithms;

use RuntimeException;

/**
 * B2: EarlyWarningScoreCalculator.
 * Skor NEWS2 berstandar Royal College of Physicians.
 * Kompleksitas: O(1).
 */
final class EarlyWarningScoreCalculator
{
    public const RISK_LOW = 'RENDAH';

    public const RISK_LOW_MEDIUM = 'RENDAH_SEDANG';

    public const RISK_MEDIUM = 'SEDANG';

    public const RISK_HIGH = 'TINGGI';

    /**
     * @param  int  $respirationRate  Laju napas per menit
     * @param  int  $spo2Percent  Saturasi oksigen (%)
     * @param  bool  $supplementalOxygen  Apakah menggunakan oksigen tambahan
     * @param  int  $systolicBp  Tekanan darah sistolik (mmHg)
     * @param  int  $heartRate  Denyut nadi (bpm)
     * @param  string  $consciousness  Tingkat kesadaran ('A'=Alert, lainnya CVPU)
     * @param  int  $tempDeciC  Suhu tubuh dalam 0,1 °C (misal 368 = 36,8 °C)
     */
    public function calculate(
        int $respirationRate,
        int $spo2Percent,
        bool $supplementalOxygen,
        int $systolicBp,
        int $heartRate,
        string $consciousness,
        int $tempDeciC
    ): News2ScoreResult {
        // 1. Laju napas
        $respScore = match (true) {
            $respirationRate <= 8 => 3,
            $respirationRate <= 11 => 1,
            $respirationRate <= 20 => 0,
            $respirationRate <= 24 => 2,
            default => 3,
        };

        // 2. SpO2 Skala 1
        $spo2Score = match (true) {
            $spo2Percent <= 91 => 3,
            $spo2Percent <= 93 => 2,
            $spo2Percent <= 95 => 1,
            default => 0,
        };

        // 3. Oksigen tambahan
        $o2Score = $supplementalOxygen ? 2 : 0;

        // 4. Tekanan sistolik
        $sysScore = match (true) {
            $systolicBp <= 90 => 3,
            $systolicBp <= 100 => 2,
            $systolicBp <= 110 => 1,
            $systolicBp <= 219 => 0,
            default => 3,
        };

        // 5. Nadi
        $hrScore = match (true) {
            $heartRate <= 40 => 3,
            $heartRate <= 50 => 1,
            $heartRate <= 90 => 0,
            $heartRate <= 110 => 1,
            $heartRate <= 130 => 2,
            default => 3,
        };

        // 6. Kesadaran
        $consScore = strtoupper(trim($consciousness)) === 'A' ? 0 : 3;

        // 7. Suhu (0,1 °C)
        $tempScore = match (true) {
            $tempDeciC <= 350 => 3,
            $tempDeciC <= 360 => 1,
            $tempDeciC <= 380 => 0,
            $tempDeciC <= 390 => 1,
            default => 2,
        };

        $totalScore = $respScore + $spo2Score + $o2Score + $sysScore + $hrScore + $consScore + $tempScore;

        $hasSingleThree = (
            $respScore === 3 ||
            $spo2Score === 3 ||
            $sysScore === 3 ||
            $hrScore === 3 ||
            $consScore === 3 ||
            $tempScore === 3
        );

        $riskCategory = match (true) {
            $totalScore >= 7 => self::RISK_HIGH,
            $totalScore >= 5 => self::RISK_MEDIUM,
            $totalScore <= 4 && $hasSingleThree => self::RISK_LOW_MEDIUM,
            default => self::RISK_LOW,
        };

        $breakdown = [
            'respiration' => $respScore,
            'spo2' => $spo2Score,
            'supplemental_o2' => $o2Score,
            'systolic_bp' => $sysScore,
            'heart_rate' => $hrScore,
            'consciousness' => $consScore,
            'temperature' => $tempScore,
        ];

        $certificate = new News2CalculationCertificate(
            totalScore: $totalScore,
            riskCategory: $riskCategory,
            breakdown: $breakdown,
            hasSingleThree: $hasSingleThree,
            generatedAt: time()
        );

        News2CertificateVerifier::verify($certificate);

        return new News2ScoreResult($totalScore, $riskCategory, $breakdown, $certificate);
    }
}

final class News2ScoreResult
{
    /**
     * @param  array<string, int>  $breakdown
     */
    public function __construct(
        public readonly int $totalScore,
        public readonly string $riskCategory,
        public readonly array $breakdown,
        public readonly News2CalculationCertificate $certificate
    ) {}
}

final class News2CalculationCertificate
{
    public readonly string $sha256;

    /**
     * @param  array<string, int>  $breakdown
     */
    public function __construct(
        public readonly int $totalScore,
        public readonly string $riskCategory,
        public readonly array $breakdown,
        public readonly bool $hasSingleThree,
        public readonly int $generatedAt,
        ?string $customSha256 = null
    ) {
        $payload = json_encode([
            'total' => $this->totalScore,
            'category' => $this->riskCategory,
            'breakdown' => $this->breakdown,
            'single3' => $this->hasSingleThree,
        ], JSON_THROW_ON_ERROR);

        $this->sha256 = $customSha256 ?? hash('sha256', $payload);
    }
}

final class News2CertificateVerifier
{
    public static function verify(News2CalculationCertificate $certificate): void
    {
        $payload = json_encode([
            'total' => $certificate->totalScore,
            'category' => $certificate->riskCategory,
            'breakdown' => $certificate->breakdown,
            'single3' => $certificate->hasSingleThree,
        ], JSON_THROW_ON_ERROR);

        $expectedHash = hash('sha256', $payload);
        if (! hash_equals($expectedHash, $certificate->sha256)) {
            throw new RuntimeException('Sertifikat NEWS2 tidak valid: hash tidak cocok.');
        }

        $sum = array_sum($certificate->breakdown);
        if ($sum !== $certificate->totalScore) {
            throw new RuntimeException("Sertifikat NEWS2 cacat: total {$certificate->totalScore} != sum breakdown {$sum}");
        }
    }
}
