<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Algorithms;

use InvalidArgumentException;

final class ClinicalNutritionRiskCalculator
{
    /**
     * Menghitung skor skrining risiko nutrisi rawat inap (Nutritional Risk Screening - NRS-2002)
     * menggabungkan status gangguan gizi, keparahan penyakit (stres metabolik), dan faktor usia.
     *
     * @param array{
     *     bmi: float,
     *     weight_loss_pct_3months: float,
     *     food_intake_reduction_pct_last_week: float,
     *     disease_severity_score: int, // 0 = normal, 1 = ringan (fraktur), 2 = sedang (stroke/bedah mayor), 3 = berat (ICU/sepsis)
     *     age_years: int
     * } $data
     * @return array{
     *     impaired_nutritional_status_score: int,
     *     disease_severity_score: int,
     *     age_adjustment_score: int,
     *     total_nrs_score: int,
     *     is_at_nutritional_risk: bool,
     *     clinical_action_plan: string,
     *     certificate: array{
     *         algorithm: string,
     *         version: string,
     *         input_hash: string,
     *         signature: string
     *     }
     * }
     */
    public function evaluate(array $data): array
    {
        $bmi = (float) ($data['bmi'] ?? 22.0);
        $weightLoss = (float) ($data['weight_loss_pct_3months'] ?? 0.0);
        $intakeReduction = (float) ($data['food_intake_reduction_pct_last_week'] ?? 0.0);
        $diseaseScore = min(3, max(0, (int) ($data['disease_severity_score'] ?? 0)));
        $age = (int) ($data['age_years'] ?? 30);

        // 1. Skor Gangguan Status Gizi (0 - 3)
        $nutritionScore = 0;
        if ($bmi < 18.5 || $weightLoss > 15.0 || ($intakeReduction >= 75.0)) {
            $nutritionScore = 3; // Berat
        } elseif ($weightLoss > 5.0 || ($intakeReduction >= 50.0)) {
            $nutritionScore = 2; // Sedang
        } elseif ($weightLoss > 0.0 || ($intakeReduction >= 25.0)) {
            $nutritionScore = 1; // Ringan
        }

        // 2. Koreksi Usia (>= 70 tahun menambah 1 poin)
        $ageScore = ($age >= 70) ? 1 : 0;

        $totalScore = $nutritionScore + $diseaseScore + $ageScore;
        $isAtRisk = $totalScore >= 3;

        $actionPlan = match (true) {
            $totalScore >= 5 => 'DIET_INTERVENTION_ENTERAL_OR_PARENTERAL_CRITICAL',
            $totalScore >= 3 => 'NUTRITION_SUPPORT_PLAN_MANDATORY',
            default => 'ROUTINE_WEEKLY_NUTRITIONAL_RESCREENING',
        };

        $inputSerialized = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $inputHash = hash('sha256', $inputSerialized);
        $sigPayload = "CNRC-V1:{$totalScore}:{$nutritionScore}:{$diseaseScore}:{$ageScore}:" . ($isAtRisk ? '1' : '0') . ":{$inputHash}";
        $signature = hash_hmac('sha256', $sigPayload, 'HSP_SECRET_NUTRITION_RISK_2026');

        return [
            'impaired_nutritional_status_score' => $nutritionScore,
            'disease_severity_score' => $diseaseScore,
            'age_adjustment_score' => $ageScore,
            'total_nrs_score' => $totalScore,
            'is_at_nutritional_risk' => $isAtRisk,
            'clinical_action_plan' => $actionPlan,
            'certificate' => [
                'algorithm' => 'ClinicalNutritionRiskCalculator',
                'version' => '1.0.0',
                'input_hash' => $inputHash,
                'signature' => $signature,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $result
     * @param array<string, mixed> $originalInput
     */
    public function verifyCertificate(array $result, array $originalInput): bool
    {
        $inputSerialized = json_encode($originalInput, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $inputHash = hash('sha256', $inputSerialized);

        $cert = $result['certificate'] ?? [];
        if (($cert['input_hash'] ?? '') !== $inputHash) {
            return false;
        }

        $total = $result['total_nrs_score'] ?? 0;
        $nutr = $result['impaired_nutritional_status_score'] ?? 0;
        $dis = $result['disease_severity_score'] ?? 0;
        $age = $result['age_adjustment_score'] ?? 0;
        $risk = !empty($result['is_at_nutritional_risk']) ? '1' : '0';

        $expectedSigPayload = "CNRC-V1:{$total}:{$nutr}:{$dis}:{$age}:{$risk}:{$inputHash}";
        $expectedSig = hash_hmac('sha256', $expectedSigPayload, 'HSP_SECRET_NUTRITION_RISK_2026');

        return hash_equals($expectedSig, (string) ($cert['signature'] ?? ''));
    }
}
