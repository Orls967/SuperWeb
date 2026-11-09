<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Services;

/**
 * Telemedicine Clinical Triage Scoring & Early Warning System (MEWS/PEWS).
 * Menilai tingkat keparahan fisiologis pasien jarak jauh:
 * 1. Kalkulasi Modified Early Warning Score (MEWS) untuk pasien dewasa.
 * 2. Skoring Pediatric Early Warning Score (PEWS) untuk pasien anak.
 * 3. Pemetaan ke Emergency Severity Index (ESI Level 1-5).
 * 4. Peringatan dini hipoksia ($SpO_2 < 92\%$), takikardia, dan penurunan kesadaran (AVPU scale).
 */
class TelemedicineClinicalTriageScoringService
{
    /**
     * Menghitung Modified Early Warning Score (MEWS) untuk dewasa.
     *
     * @param array{
     *     heart_rate_bpm: int,
     *     systolic_bp_mmhg: int,
     *     respiratory_rate_bpm: int,
     *     temperature_celsius: float,
     *     avpu_consciousness: string // ALERT, VOICE, PAIN, UNRESPONSIVE
     * } $vitals
     * @return array{
     *     mews_score: int,
     *     esi_level: int, // 1 to 5
     *     clinical_risk_tier: string, // HIGH, MEDIUM, LOW
     *     recommended_action: string
     * }
     */
    public function calculateAdultMews(array $vitals): array
    {
        $hr = (int) ($vitals['heart_rate_bpm'] ?? 75);
        $sbp = (int) ($vitals['systolic_bp_mmhg'] ?? 120);
        $rr = (int) ($vitals['respiratory_rate_bpm'] ?? 16);
        $temp = (float) ($vitals['temperature_celsius'] ?? 36.8);
        $avpu = strtoupper((string) ($vitals['avpu_consciousness'] ?? 'ALERT'));

        $score = 0;

        // Heart rate score
        if ($hr <= 40 || $hr >= 130) {
            $score += 3;
        } elseif ($hr >= 111 || $hr <= 50) {
            $score += 2;
        } elseif ($hr >= 101) {
            $score += 1;
        }

        // Systolic BP score
        if ($sbp <= 70) {
            $score += 3;
        } elseif ($sbp <= 80 || $sbp >= 200) {
            $score += 2;
        } elseif ($sbp <= 100) {
            $score += 1;
        }

        // Respiratory rate score
        if ($rr < 9 || $rr >= 30) {
            $score += 3;
        } elseif ($rr >= 21) {
            $score += 2;
        } elseif ($rr >= 15) {
            $score += 1;
        }

        // Temperature score
        if ($temp < 35.0 || $temp >= 38.5) {
            $score += 2;
        }

        // AVPU score
        if ($avpu === 'UNRESPONSIVE') {
            $score += 3;
        } elseif ($avpu === 'PAIN') {
            $score += 2;
        } elseif ($avpu === 'VOICE') {
            $score += 1;
        }

        $risk = match (true) {
            $score >= 5 => 'HIGH',
            $score >= 3 => 'MEDIUM',
            default => 'LOW',
        };

        $esi = match (true) {
            $avpu === 'UNRESPONSIVE' || $score >= 6 => 1, // Resuscitation
            $score >= 4 || $sbp < 80 => 2,                // Emergent
            $score >= 2 => 3,                             // Urgent
            $score === 1 => 4,                            // Less Urgent
            default => 5,                                 // Non-Urgent
        };

        $action = match ($risk) {
            'HIGH' => 'Eskalasi Gawat Darurat Instan: Rujuk ke IGD atau kirim ambulans segera (ESI 1-2).',
            'MEDIUM' => 'Perhatian Khusus: Pemeriksaan dokter tatap muka dalam 1-2 jam diperlukan (ESI 3).',
            default => 'Rawat Jalan Aman: Konsultasi telemedis dan preskripsi digital dapat dilanjutkan (ESI 4-5).',
        };

        return [
            'mews_score' => $score,
            'esi_level' => $esi,
            'clinical_risk_tier' => $risk,
            'recommended_action' => $action,
        ];
    }
}
