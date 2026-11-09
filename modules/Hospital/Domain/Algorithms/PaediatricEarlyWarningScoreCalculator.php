<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Algorithms;

final class PaediatricEarlyWarningScoreCalculator
{
    /**
     * Menghitung skor peringatan dini pediatrik (Paediatric Early Warning Score - PEWS)
     * untuk pasien anak dan bayi berdasarkan perilaku/kesadaran, kardiovaskular, dan respirasi.
     *
     * @param array{
     *     behaviour: string, // PLAYING (0), SLEEPING (1), IRRITABLE (2), LETHARGIC_CONFUSED (3)
     *     cardiovascular: string, // PINK_NORMAL (0), PALE (1), GREY_CYANOTIC (2), TACHYCARDIA_CRT_GT_4S (3)
     *     respiratory: string // NORMAL (0), MILD_RETRACTIONS (1), MODERATE_RETRACTIONS (2), SEVERE_GRUNTING_APNEA (3)
     * } $vitals
     * @return array{
     *     behaviour_score: int,
     *     cardiovascular_score: int,
     *     respiratory_score: int,
     *     total_pews_score: int,
     *     risk_category: string, // LOW, MEDIUM, HIGH, CRITICAL
     *     escalation_action: string,
     *     certificate: array{
     *         algorithm: string,
     *         version: string,
     *         input_hash: string,
     *         signature: string
     *     }
     * }
     */
    public function evaluate(array $vitals): array
    {
        $beh = strtoupper((string) ($vitals['behaviour'] ?? 'PLAYING'));
        $cv = strtoupper((string) ($vitals['cardiovascular'] ?? 'PINK_NORMAL'));
        $resp = strtoupper((string) ($vitals['respiratory'] ?? 'NORMAL'));

        $behScore = match ($beh) {
            'LETHARGIC_CONFUSED' => 3,
            'IRRITABLE' => 2,
            'SLEEPING' => 1,
            default => 0,
        };

        $cvScore = match ($cv) {
            'TACHYCARDIA_CRT_GT_4S' => 3,
            'GREY_CYANOTIC' => 2,
            'PALE' => 1,
            default => 0,
        };

        $respScore = match ($resp) {
            'SEVERE_GRUNTING_APNEA' => 3,
            'MODERATE_RETRACTIONS' => 2,
            'MILD_RETRACTIONS' => 1,
            default => 0,
        };

        $totalScore = $behScore + $cvScore + $respScore;

        $riskCat = match (true) {
            $totalScore >= 7 || $behScore === 3 || $cvScore === 3 || $respScore === 3 => 'CRITICAL',
            $totalScore >= 5 => 'HIGH',
            $totalScore >= 3 => 'MEDIUM',
            default => 'LOW',
        };

        $action = match ($riskCat) {
            'CRITICAL' => 'IMMEDIATE_PAEDIATRIC_CODE_BLUE_PICU_TRANSFER',
            'HIGH' => 'URGENT_DUTY_PAEDIATRICIAN_BEDSIDE_EVALUATION',
            'MEDIUM' => 'INCREASE_OBSERVATIONS_HOURLY_SENIOR_NURSE_REVIEW',
            'LOW' => 'ROUTINE_WARD_OBSERVATIONS_EVERY_4_HOURS',
        };

        $inputSerialized = json_encode($vitals, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $inputHash = hash('sha256', $inputSerialized);
        $sigPayload = "PEWS-V1:{$totalScore}:{$riskCat}:{$inputHash}";
        $signature = hash_hmac('sha256', $sigPayload, 'HSP_SECRET_PAEDIATRIC_EWS_2026');

        return [
            'behaviour_score' => $behScore,
            'cardiovascular_score' => $cvScore,
            'respiratory_score' => $respScore,
            'total_pews_score' => $totalScore,
            'risk_category' => $riskCat,
            'escalation_action' => $action,
            'paediatric_certificate' => [
                'scheme' => 'PEWS-V1',
                'digest' => $inputHash,
                'signature_proof' => $signature,
                'eval_class' => self::class,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $result
     * @param  array<string, mixed>  $originalInput
     */
    public function verifyCertificate(array $result, array $originalInput): bool
    {
        $payloadRaw = json_encode($originalInput, JSON_UNESCAPED_SLASHES);
        $expectedHash = hash('sha256', $payloadRaw);

        $cert = $result['paediatric_certificate'] ?? [];
        if (($cert['digest'] ?? '') !== $expectedHash) {
            return false;
        }

        $total = $result['total_pews_score'] ?? 0;
        $risk = $result['risk_category'] ?? '';

        $expectedSigPayload = "PEWS-V1:{$total}:{$risk}:{$expectedHash}";
        $expectedSig = hash_hmac('sha256', $expectedSigPayload, 'HSP_SECRET_PAEDIATRIC_EWS_2026');

        return hash_equals($expectedSig, (string) ($cert['signature_proof'] ?? ''));
    }
}
