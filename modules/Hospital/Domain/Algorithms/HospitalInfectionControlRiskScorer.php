<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Algorithms;

use InvalidArgumentException;

final class HospitalInfectionControlRiskScorer
{
    /**
     * Menghitung skor risiko infeksi nosokomial (HAIs - Healthcare-Associated Infections)
     * dan merekomendasikan tingkat isolasi serta protokol kewaspadaan (contact, droplet, airborne).
     *
     * @param array{
     *     has_invasive_device: bool,
     *     device_days: int,
     *     is_immunocompromised: bool,
     *     prior_multidrug_resistant_organism: bool,
     *     has_open_wound: bool,
     *     current_antibiotic_days: int,
     *     respiratory_symptoms: bool,
     *     diarrhea_active: bool,
     *     icu_stay: bool
     * } $profile
     * @return array{
     *     risk_score: int,
     *     risk_category: string,
     *     recommended_isolation: string,
     *     precaution_bundle: list<string>,
     *     screening_required: bool,
     *     certificate: array{
     *         algorithm: string,
     *         version: string,
     *         input_hash: string,
     *         signature: string
     *     }
     * }
     */
    public function evaluate(array $profile): array
    {
        $score = 0;
        $precautions = ['STANDARD_HAND_HYGIENE', 'UNIVERSAL_MASKING'];

        if (!empty($profile['has_invasive_device'])) {
            $score += 15;
            $deviceDays = (int) ($profile['device_days'] ?? 0);
            if ($deviceDays > 7) {
                $score += 15;
                $precautions[] = 'DEVICE_LINE_AUDIT_DAILY';
            } elseif ($deviceDays > 3) {
                $score += 10;
            }
        }

        if (!empty($profile['is_immunocompromised'])) {
            $score += 20;
            $precautions[] = 'PROTECTIVE_ENVIRONMENT_CONSIDERATION';
        }

        if (!empty($profile['prior_multidrug_resistant_organism'])) {
            $score += 25;
            $precautions[] = 'CONTACT_PRECAUTION';
            $precautions[] = 'DEDICATED_EQUIPMENT';
        }

        if (!empty($profile['has_open_wound'])) {
            $score += 10;
            $precautions[] = 'STRICT_ASEPTIC_DRESSING';
        }

        $abxDays = (int) ($profile['current_antibiotic_days'] ?? 0);
        if ($abxDays >= 14) {
            $score += 15;
            $precautions[] = 'ANTIMICROBIAL_STEWARDSHIP_AUDIT';
        } elseif ($abxDays >= 7) {
            $score += 10;
        }

        if (!empty($profile['respiratory_symptoms'])) {
            $score += 15;
            $precautions[] = 'DROPLET_PRECAUTION';
            $precautions[] = 'AIRBORNE_ISOLATION_EVALUATION';
        }

        if (!empty($profile['diarrhea_active'])) {
            $score += 15;
            $precautions[] = 'ENTERIC_CONTACT_PRECAUTION';
            $precautions[] = 'SOAP_WATER_HYGIENE_MANDATORY';
        }

        if (!empty($profile['icu_stay'])) {
            $score += 10;
        }

        // Kategori Risiko
        if ($score >= 60) {
            $category = 'VERY_HIGH';
            $isolation = 'AIRBORNE_OR_NEGATIVE_PRESSURE';
            $screening = true;
        } elseif ($score >= 40) {
            $category = 'HIGH';
            $isolation = 'CONTACT_ISOLATION_SINGLE_ROOM';
            $screening = true;
        } elseif ($score >= 20) {
            $category = 'MODERATE';
            $isolation = 'COHORT_COMPATIBLE';
            $screening = false;
        } else {
            $category = 'LOW';
            $isolation = 'STANDARD_WARD';
            $screening = false;
        }

        $inputSerialized = json_encode($profile, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $inputHash = hash('sha256', $inputSerialized);
        $sigPayload = "HICR-V1:{$score}:{$category}:{$isolation}:{$inputHash}";
        $signature = hash_hmac('sha256', $sigPayload, 'HSP_SECRET_INFECTION_SURVEILLANCE_2026');

        return [
            'risk_score' => $score,
            'risk_category' => $category,
            'recommended_isolation' => $isolation,
            'precaution_bundle' => array_values(array_unique($precautions)),
            'screening_required' => $screening,
            'certificate' => [
                'algorithm' => 'HospitalInfectionControlRiskScorer',
                'version' => '1.0.0',
                'input_hash' => $inputHash,
                'signature' => $signature,
            ],
        ];
    }

    /**
     * Verifikasi integritas sertifikat kalkulasi risiko infeksi.
     *
     * @param array<string, mixed> $evaluation
     * @param array<string, mixed> $originalProfile
     * @return bool
     */
    public function verifyCertificate(array $evaluation, array $originalProfile): bool
    {
        $inputSerialized = json_encode($originalProfile, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $inputHash = hash('sha256', $inputSerialized);

        $cert = $evaluation['certificate'] ?? [];
        if (($cert['input_hash'] ?? '') !== $inputHash) {
            return false;
        }

        $score = $evaluation['risk_score'] ?? 0;
        $category = $evaluation['risk_category'] ?? '';
        $isolation = $evaluation['recommended_isolation'] ?? '';
        $expectedSigPayload = "HICR-V1:{$score}:{$category}:{$isolation}:{$inputHash}";
        $expectedSig = hash_hmac('sha256', $expectedSigPayload, 'HSP_SECRET_INFECTION_SURVEILLANCE_2026');

        return hash_equals($expectedSig, (string) ($cert['signature'] ?? ''));
    }
}
