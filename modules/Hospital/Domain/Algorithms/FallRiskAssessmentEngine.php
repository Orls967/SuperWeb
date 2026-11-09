<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Algorithms;

final class FallRiskAssessmentEngine
{
    /**
     * Menghitung skor risiko jatuh pasien rawat inap menggunakan Morse Fall Scale (MFS)
     * dan menghasilkan intervensi pencegahan cedera terstandar.
     *
     * @param array{
     *     history_of_falling_past_3months: bool, // 25 poin
     *     secondary_diagnosis_present: bool, // 15 poin
     *     ambulatory_aid: string, // NONE (0), CRUTCHES_CANE_WALKER (15), FURNITURE_HOLD (30)
     *     iv_line_or_heparin_lock: bool, // 20 poin
     *     gait_transferring: string, // NORMAL (0), WEAK (10), IMPAIRED (20)
     *     mental_status: string // ORIENTED (0), FORGETS_LIMITATIONS (15)
     * } $profile
     * @return array{
     *     morse_score: int,
     *     risk_category: string, // LOW_RISK, MEDIUM_RISK, HIGH_RISK
     *     prevention_bundle: list<string>,
     *     bed_safety_requirements: list<string>,
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
        $bundle = ['BED_LOWEST_POSITION', 'CALL_BELL_WITHIN_REACH', 'NON_SKID_FOOTWEAR'];
        $bedReqs = ['SIDE_RAILS_UP_2'];

        if (! empty($profile['history_of_falling_past_3months'])) {
            $score += 25;
            $bundle[] = 'YELLOW_FALL_RISK_WRISTBAND';
        }

        if (! empty($profile['secondary_diagnosis_present'])) {
            $score += 15;
        }

        $aid = strtoupper((string) ($profile['ambulatory_aid'] ?? 'NONE'));
        if ($aid === 'FURNITURE_HOLD') {
            $score += 30;
            $bundle[] = 'SUPERVISED_AMBULATION_ONLY';
        } elseif ($aid === 'CRUTCHES_CANE_WALKER') {
            $score += 15;
            $bundle[] = 'MOBILITY_DEVICE_SAFETY_CHECK';
        }

        if (! empty($profile['iv_line_or_heparin_lock'])) {
            $score += 20;
            $bundle[] = 'IV_POLE_WHEEL_LOCK_SAFETY';
        }

        $gait = strtoupper((string) ($profile['gait_transferring'] ?? 'NORMAL'));
        if ($gait === 'IMPAIRED') {
            $score += 20;
            $bedReqs[] = 'BED_EXIT_ALARM_ENABLED';
        } elseif ($gait === 'WEAK') {
            $score += 10;
        }

        $mental = strtoupper((string) ($profile['mental_status'] ?? 'ORIENTED'));
        if ($mental === 'FORGETS_LIMITATIONS') {
            $score += 15;
            $bundle[] = 'FREQUENT_NURSING_ROUNDS_HOURLY';
            $bedReqs[] = 'SIDE_RAILS_UP_4_WITH_SUPERVISION';
        }

        if ($score >= 45) {
            $category = 'HIGH_RISK';
            $bundle[] = 'NURSE_1_TO_1_TRANSFER_ASSISTANCE';
        } elseif ($score >= 25) {
            $category = 'MEDIUM_RISK';
        } else {
            $category = 'LOW_RISK';
        }

        $inputSerialized = json_encode($profile, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $inputHash = hash('sha256', $inputSerialized);
        $sigPayload = "FRAE-V1:{$score}:{$category}:{$inputHash}";
        $signature = hash_hmac('sha256', $sigPayload, 'HSP_SECRET_FALL_RISK_2026');

        return [
            'morse_score' => $score,
            'risk_category' => $category,
            'prevention_bundle' => array_values(array_unique($bundle)),
            'bed_safety_requirements' => array_values(array_unique($bedReqs)),
            'certificate' => [
                'algorithm' => 'FallRiskAssessmentEngine',
                'version' => '1.0.0',
                'input_hash' => $inputHash,
                'signature' => $signature,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $result
     * @param  array<string, mixed>  $originalProfile
     */
    public function verifyCertificate(array $result, array $originalProfile): bool
    {
        $inputSerialized = json_encode($originalProfile, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $inputHash = hash('sha256', $inputSerialized);

        $cert = $result['certificate'] ?? [];
        if (($cert['input_hash'] ?? '') !== $inputHash) {
            return false;
        }

        $score = $result['morse_score'] ?? 0;
        $category = $result['risk_category'] ?? '';
        $expectedSigPayload = "FRAE-V1:{$score}:{$category}:{$inputHash}";
        $expectedSig = hash_hmac('sha256', $expectedSigPayload, 'HSP_SECRET_FALL_RISK_2026');

        return hash_equals($expectedSig, (string) ($cert['signature'] ?? ''));
    }
}
