<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Services;

use InvalidArgumentException;

/**
 * EmergencyTriageEsiAlgorithmService
 *
 * Mengimplementasikan Algoritma Triase Emergency Severity Index (ESI Tingkat 1 s/d 5)
 * untuk Instalasi Gawat Darurat (IGD) Rumah Sakit (AHRQ Guidelines).
 * Menentukan tingkat kegawatan pasien, batas waktu respons dokter jaga (door-to-doctor time),
 * estimasi kebutuhan sumber daya penunjang (lab, radiologi, IV fluid, prosedur invasif),
 * serta penyesuaian level tarif biaya pelayanan IGD.
 */
class EmergencyTriageEsiAlgorithmService
{
    public const ESI_LEVEL_1_RESUSCITATION = 'ESI_1_RESUSCITATION_IMMEDIATE';
    public const ESI_LEVEL_2_EMERGENT = 'ESI_2_EMERGENT_HIGH_RISK';
    public const ESI_LEVEL_3_URGENT = 'ESI_3_URGENT_MULTIPLE_RESOURCES';
    public const ESI_LEVEL_4_LESS_URGENT = 'ESI_4_LESS_URGENT_ONE_RESOURCE';
    public const ESI_LEVEL_5_NON_URGENT = 'ESI_5_NON_URGENT_NO_RESOURCE';

    /**
     * Evaluasi alur triase ESI untuk pasien IGD.
     *
     * @param bool $requiresImmediateLifeSaving Apakah pasien membutuhkan intervensi penyelamatan hidup seketika (apnea, henti jantung, syok berat)
     * @param bool $isHighRiskConfusionLethargy Apakah pasien dalam kondisi risiko tinggi, disorientasi akut, atau nyeri hebat (VAS >= 7)
     * @param array<string> $anticipatedResources Sumber daya diagnostik/terapi yang diprediksi dibutuhkan ('LAB', 'XRAY_CT', 'IV_HYDRATION', 'SPECIALTY_CONSULT', 'INVASIVE_PROCEDURE')
     * @param int $heartRate Denyut nadi (bpm)
     * @param int $respiratoryRate Laju pernapasan (x/menit)
     * @param float $oxygenSaturation SpO2 (%)
     * @param int $patientAgeYears Usia pasien dalam tahun
     * @return array{
     *     esi_level: string,
     *     target_response_time_minutes: int,
     *     acuity_badge: string,
     *     triage_decision_rationale: string,
     *     is_danger_vital_signs_breached: bool,
     *     recommended_bed_zone: string
     * }
     */
    public function evaluateEsiTriage(
        bool $requiresImmediateLifeSaving,
        bool $isHighRiskConfusionLethargy,
        array $anticipatedResources,
        int $heartRate,
        int $respiratoryRate,
        float $oxygenSaturation,
        int $patientAgeYears
    ): array {
        if ($patientAgeYears < 0) {
            throw new InvalidArgumentException('Usia pasien tidak boleh negatif');
        }

        // Keputusan A: Intervensi penyelamatan hidup segera?
        if ($requiresImmediateLifeSaving) {
            return [
                'esi_level' => self::ESI_LEVEL_1_RESUSCITATION,
                'target_response_time_minutes' => 0, // Segera / 0 menit
                'acuity_badge' => 'RED_RESUSCITATION',
                'triage_decision_rationale' => 'Pasien membutuhkan resusitasi jalan napas atau hemodinamik segera.',
                'is_danger_vital_signs_breached' => true,
                'recommended_bed_zone' => 'RESUSCITATION_ROOM_BAY_1',
            ];
        }

        // Keputusan B: Situasi risiko tinggi, bingung/letargis, atau nyeri berat?
        if ($isHighRiskConfusionLethargy) {
            return [
                'esi_level' => self::ESI_LEVEL_2_EMERGENT,
                'target_response_time_minutes' => 10,
                'acuity_badge' => 'ORANGE_EMERGENT',
                'triage_decision_rationale' => 'Pasien berisiko perburukan cepat atau mengalami disorientasi/nyeri hebat.',
                'is_danger_vital_signs_breached' => false,
                'recommended_bed_zone' => 'CRITICAL_CARE_MONITORING_BED',
            ];
        }

        // Keputusan C: Berapa banyak sumber daya yang dibutuhkan?
        $resourceCount = count(array_unique($anticipatedResources));

        if ($resourceCount === 0) {
            return [
                'esi_level' => self::ESI_LEVEL_5_NON_URGENT,
                'target_response_time_minutes' => 120,
                'acuity_badge' => 'BLUE_NON_URGENT',
                'triage_decision_rationale' => 'Pasien tidak memerlukan pemeriksaan penunjang diagnostik/terapi kompleks.',
                'is_danger_vital_signs_breached' => false,
                'recommended_bed_zone' => 'FAST_TRACK_AMBULATORY_CLINIC',
            ];
        }

        if ($resourceCount === 1) {
            return [
                'esi_level' => self::ESI_LEVEL_4_LESS_URGENT,
                'target_response_time_minutes' => 60,
                'acuity_badge' => 'GREEN_LESS_URGENT',
                'triage_decision_rationale' => 'Pasien membutuhkan 1 modalitas sumber daya penunjang.',
                'is_danger_vital_signs_breached' => false,
                'recommended_bed_zone' => 'OBSERVATION_HOLDING_AREA',
            ];
        }

        // Pasien butuh 2 atau lebih sumber daya: Keputusan D (Periksa Tanda Vital Bahaya ESI Level 3 -> upgrade Level 2 jika bahaya)
        $isDangerVital = false;
        if ($oxygenSaturation < 92.0 || $heartRate > 120 || $respiratoryRate > 28) {
            $isDangerVital = true;
        }

        if ($isDangerVital) {
            return [
                'esi_level' => self::ESI_LEVEL_2_EMERGENT,
                'target_response_time_minutes' => 10,
                'acuity_badge' => 'ORANGE_EMERGENT',
                'triage_decision_rationale' => 'Ditingkatkan ke ESI Level 2 karena tanda vital melewati zona bahaya (takikardia/takipnea/desaturasi).',
                'is_danger_vital_signs_breached' => true,
                'recommended_bed_zone' => 'CRITICAL_CARE_MONITORING_BED',
            ];
        }

        return [
            'esi_level' => self::ESI_LEVEL_3_URGENT,
            'target_response_time_minutes' => 30,
            'acuity_badge' => 'YELLOW_URGENT',
            'triage_decision_rationale' => sprintf('Pasien membutuhkan %d sumber daya diagnostik/terapi dengan tanda vital stabil.', $resourceCount),
            'is_danger_vital_signs_breached' => false,
            'recommended_bed_zone' => 'ACUTE_CARE_TREATMENT_BAY',
        ];
    }
}
