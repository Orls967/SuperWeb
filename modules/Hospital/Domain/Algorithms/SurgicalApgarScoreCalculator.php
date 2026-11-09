<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Algorithms;

use InvalidArgumentException;

final class SurgicalApgarScoreCalculator
{
    /**
     * Menghitung Surgical Apgar Score (SAS 0-10) untuk memprediksi risiko
     * komplikasi mayor dan mortalitas 30 hari pasca operasi bedah rawat inap.
     *
     * @param array{
     *     estimated_blood_loss_ml: int, // <=100 (3), 101-600 (2), 601-1000 (1), >1000 (0)
     *     lowest_mean_arterial_pressure_mmhg: int, // >=70 (3), 55-69 (2), 40-54 (1), <40 (0)
     *     lowest_heart_rate_bpm: int // <=55 (0), 56-65 (1), 66-75 (2), 76-85 (3), >85 (4)
     * } $data
     * @return array{
     *     ebl_points: int,
     *     map_points: int,
     *     hr_points: int,
     *     total_score: int,
     *     risk_tier: string, // HIGH_RISK, MODERATE_RISK, LOW_RISK
     *     postop_disposition_recommendation: string, // ICU, HDU, WARD
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
        $ebl = (int) ($data['estimated_blood_loss_ml'] ?? 0);
        $map = (int) ($data['lowest_mean_arterial_pressure_mmhg'] ?? 75);
        $hr = (int) ($data['lowest_heart_rate_bpm'] ?? 70);

        // 1. Estimated Blood Loss Points (0 - 3)
        $eblPoints = match (true) {
            $ebl <= 100 => 3,
            $ebl <= 600 => 2,
            $ebl <= 1000 => 1,
            default => 0,
        };

        // 2. Lowest Mean Arterial Pressure Points (0 - 3)
        $mapPoints = match (true) {
            $map >= 70 => 3,
            $map >= 55 => 2,
            $map >= 40 => 1,
            default => 0,
        };

        // 3. Lowest Heart Rate Points (0 - 4)
        $hrPoints = match (true) {
            $hr > 85 => 4,
            $hr >= 76 => 3,
            $hr >= 66 => 2,
            $hr >= 56 => 1,
            default => 0,
        };

        $totalScore = $eblPoints + $mapPoints + $hrPoints;

        $riskTier = match (true) {
            $totalScore <= 4 => 'HIGH_RISK',
            $totalScore <= 7 => 'MODERATE_RISK',
            default => 'LOW_RISK',
        };

        $disposition = match ($riskTier) {
            'HIGH_RISK' => 'INTENSIVE_CARE_UNIT_VENTILATOR_MONITORING',
            'MODERATE_RISK' => 'HIGH_DEPENDENCY_UNIT_OR_PACU_OVERNIGHT',
            'LOW_RISK' => 'STANDARD_SURGICAL_WARD_RECOVERY',
        };

        $inputSerialized = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $inputHash = hash('sha256', $inputSerialized);
        $sigPayload = "SASC-V1:{$totalScore}:{$riskTier}:{$disposition}:{$inputHash}";
        $signature = hash_hmac('sha256', $sigPayload, 'HSP_SECRET_SURGICAL_APGAR_2026');

        return [
            'ebl_points' => $eblPoints,
            'map_points' => $mapPoints,
            'hr_points' => $hrPoints,
            'total_score' => $totalScore,
            'risk_tier' => $riskTier,
            'postop_disposition_recommendation' => $disposition,
            'surgical_audit_certificate' => [
                'sas_version' => 'SAS-V1',
                'provenance_hash' => $inputHash,
                'audit_signature' => $signature,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $result
     * @param array<string, mixed> $originalInput
     */
    public function verifyCertificate(array $result, array $originalInput): bool
    {
        $rawBytes = json_encode($originalInput, JSON_UNESCAPED_SLASHES);
        $computedHash = hash('sha256', $rawBytes);

        $cert = $result['surgical_audit_certificate'] ?? [];
        if (($cert['provenance_hash'] ?? '') !== $computedHash) {
            return false;
        }

        $total = $result['total_score'] ?? 0;
        $tier = $result['risk_tier'] ?? '';
        $disp = $result['postop_disposition_recommendation'] ?? '';

        $expectedSigPayload = "SASC-V1:{$total}:{$tier}:{$disp}:{$computedHash}";
        $expectedSig = hash_hmac('sha256', $expectedSigPayload, 'HSP_SECRET_SURGICAL_APGAR_2026');

        return hash_equals($expectedSig, (string) ($cert['audit_signature'] ?? ''));
    }
}
