<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Services;

use Carbon\CarbonImmutable;

final class EmergencyTriageProtocolService
{
    /**
     * Menilai kategori triase Emergency Severity Index (ESI Level 1 s.d. Level 5).
     *
     * @param array{
     *     requires_immediate_life_saving_intervention: bool, // ESI 1 (Intubasi, henti napas/jantung, syok berat)
     *     high_risk_situation_or_confused: bool, // ESI 2 (Nyeri dada khas infark, agitasi, stroke akut)
     *     severe_pain_or_distress: bool, // ESI 2
     *     vital_danger_zone: bool, // ESI 2
     *     estimated_resource_needs_count: int // 0 = ESI 5, 1 = ESI 4, >=2 = ESI 3
     * } $criteria
     * @return array{
     *     esi_level: int,
     *     esi_name: string,
     *     target_time_to_physician_minutes: int,
     *     recommended_ward_type: string,
     *     triage_summary: string
     * }
     */
    public function stratify(array $criteria): array
    {
        if (!empty($criteria['requires_immediate_life_saving_intervention'])) {
            return [
                'esi_level' => 1,
                'esi_name' => 'RESUSCITATION',
                'target_time_to_physician_minutes' => 0,
                'recommended_ward_type' => 'RESUSCITATION_BAY_OR_ICU',
                'triage_summary' => 'Kondisi mengancam nyawa seketika; butuh intervensi penyelamatan jiwa segera.',
            ];
        }

        if (!empty($criteria['high_risk_situation_or_confused'])
            || !empty($criteria['severe_pain_or_distress'])
            || !empty($criteria['vital_danger_zone'])
        ) {
            return [
                'esi_level' => 2,
                'esi_name' => 'EMERGENT',
                'target_time_to_physician_minutes' => 10,
                'recommended_ward_type' => 'HIGH_ACUITY_BED_OR_HDU',
                'triage_summary' => 'Situasi risiko tinggi, penurunan kesadaran akut, atau tanda vital zona bahaya.',
            ];
        }

        $resources = max(0, (int) ($criteria['estimated_resource_needs_count'] ?? 0));

        if ($resources >= 2) {
            return [
                'esi_level' => 3,
                'esi_name' => 'URGENT',
                'target_time_to_physician_minutes' => 30,
                'recommended_ward_type' => 'GENERAL_INPATIENT_WARD',
                'triage_summary' => 'Membutuhkan dua atau lebih sumber daya penunjang (lab + rontgen + IV fluid).',
            ];
        }

        if ($resources === 1) {
            return [
                'esi_level' => 4,
                'esi_name' => 'LESS_URGENT',
                'target_time_to_physician_minutes' => 60,
                'recommended_ward_type' => 'OBSERVATION_UNIT_OR_OUTPATIENT',
                'triage_summary' => 'Membutuhkan tepat satu sumber daya diagnostik (misal: rontgen ekstremitas tunggal).',
            ];
        }

        return [
            'esi_level' => 5,
            'esi_name' => 'NON_URGENT',
            'target_time_to_physician_minutes' => 120,
            'recommended_ward_type' => 'FAST_TRACK_CLINIC',
            'triage_summary' => 'Kondisi stabil tanpa kebutuhan sumber daya penunjang medis invasif.',
        ];
    }
}
