<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Services;

use InvalidArgumentException;

/**
 * CriticalCareOrganSupportScoringService
 *
 * Menghitung skor Sequential Organ Failure Assessment (SOFA) pasien Intensive Care Unit (ICU)
 * untuk asesmen mortalitas sepsis (Sepsis-3 criteria) serta justifikasi klinis add-on rawat intensif
 * pada pengajuan berkas klaim INA-CBG dan asuransi kesehatan swasta.
 */
class CriticalCareOrganSupportScoringService
{
    /**
     * Hitung skor SOFA berdasarkan 6 sistem organ (Respirasi, Koagulasi, Hepar, Kardiovaskular, SSP, Ginjal).
     *
     * @param float $pao2Fio2Ratio Rasio PaO2/FiO2 (mmHg)
     * @param bool $onMechanicalVentilation Apakah pasien menggunakan ventilator mekanik
     * @param int $plateletsThousandPerMicroliter Jumlah trombosit (ribu/µL)
     * @param float $bilirubinMgPerDl Bilirubin total serum (mg/dL)
     * @param float $meanArterialPressure Mmrata MAP (mmHg)
     * @param string $vasopressorSupport Tipe obat vasopresor ('NONE', 'DOPAMINE_LOW', 'DOPAMINE_HIGH', 'NOREPINEPHRINE')
     * @param int $glasgowComaScale Nilai GCS (3 - 15)
     * @param float $creatinineMgPerDl Kreatinin serum (mg/dL)
     * @param float $urineOutputMlPerDay Pengeluaran urin 24 jam (mL/hari)
     * @return array{
     *     respiratory_score: int,
     *     coagulation_score: int,
     *     liver_score: int,
     *     cardiovascular_score: int,
     *     cns_score: int,
     *     renal_score: int,
     *     total_sofa_score: int,
     *     predicted_mortality_rate_pct: float,
     *     icu_acuity_tier: string,
     *     clinical_justification_note: string
     * }
     */
    public function calculateSofaScore(
        float $pao2Fio2Ratio,
        bool $onMechanicalVentilation,
        int $plateletsThousandPerMicroliter,
        float $bilirubinMgPerDl,
        float $meanArterialPressure,
        string $vasopressorSupport,
        int $glasgowComaScale,
        float $creatinineMgPerDl,
        float $urineOutputMlPerDay
    ): array {
        if ($glasgowComaScale < 3 || $glasgowComaScale > 15) {
            throw new InvalidArgumentException('GCS harus berada di rentang 3 s/d 15');
        }

        // 1. Respirasi
        $resp = 0;
        if ($pao2Fio2Ratio < 100.0 && $onMechanicalVentilation) {
            $resp = 4;
        } elseif ($pao2Fio2Ratio < 200.0 && $onMechanicalVentilation) {
            $resp = 3;
        } elseif ($pao2Fio2Ratio < 300.0) {
            $resp = 2;
        } elseif ($pao2Fio2Ratio < 400.0) {
            $resp = 1;
        }

        // 2. Koagulasi (Trombosit)
        $coag = 0;
        if ($plateletsThousandPerMicroliter < 20) {
            $coag = 4;
        } elseif ($plateletsThousandPerMicroliter < 50) {
            $coag = 3;
        } elseif ($plateletsThousandPerMicroliter < 100) {
            $coag = 2;
        } elseif ($plateletsThousandPerMicroliter < 150) {
            $coag = 1;
        }

        // 3. Hepar (Bilirubin)
        $liver = 0;
        if ($bilirubinMgPerDl >= 12.0) {
            $liver = 4;
        } elseif ($bilirubinMgPerDl >= 6.0) {
            $liver = 3;
        } elseif ($bilirubinMgPerDl >= 2.0) {
            $liver = 2;
        } elseif ($bilirubinMgPerDl >= 1.2) {
            $liver = 1;
        }

        // 4. Kardiovaskular
        $cv = 0;
        if ($vasopressorSupport === 'NOREPINEPHRINE' || $vasopressorSupport === 'DOPAMINE_HIGH') {
            $cv = 4;
        } elseif ($vasopressorSupport === 'DOPAMINE_LOW') {
            $cv = 3;
        } elseif ($meanArterialPressure < 70.0) {
            $cv = 1;
        }

        // 5. Susunan Saraf Pusat (GCS)
        $cns = 0;
        if ($glasgowComaScale < 6) {
            $cns = 4;
        } elseif ($glasgowComaScale <= 9) {
            $cns = 3;
        } elseif ($glasgowComaScale <= 12) {
            $cns = 2;
        } elseif ($glasgowComaScale <= 14) {
            $cns = 1;
        }

        // 6. Ginjal (Kreatinin / Urin)
        $renal = 0;
        if ($creatinineMgPerDl >= 5.0 || $urineOutputMlPerDay < 200.0) {
            $renal = 4;
        } elseif ($creatinineMgPerDl >= 3.5 || $urineOutputMlPerDay < 500.0) {
            $renal = 3;
        } elseif ($creatinineMgPerDl >= 2.0) {
            $renal = 2;
        } elseif ($creatinineMgPerDl >= 1.2) {
            $renal = 1;
        }

        $totalSofa = $resp + $coag + $liver + $cv + $cns + $renal;

        // Estimasi mortalitas berdasarkan total SOFA
        $mortality = 5.0;
        $tier = 'LOW_ACUITY_STEPDOWN';
        if ($totalSofa >= 12) {
            $mortality = 80.0;
            $tier = 'EXTREME_ACUITY_MULTI_ORGAN_FAILURE';
        } elseif ($totalSofa >= 8) {
            $mortality = 45.0;
            $tier = 'HIGH_ACUITY_CRITICAL_CARE';
        } elseif ($totalSofa >= 4) {
            $mortality = 20.0;
            $tier = 'MODERATE_ACUITY_INTENSIVE_CARE';
        }

        $note = sprintf(
            'Skor SOFA total %d (Respirasi: %d, Koagulasi: %d, Hepar: %d, KV: %d, SSP: %d, Ginjal: %d) mengindikasikan %s dengan estimasi risiko mortalitas %.1f%%.',
            $totalSofa,
            $resp,
            $coag,
            $liver,
            $cv,
            $cns,
            $renal,
            $tier,
            $mortality
        );

        return [
            'respiratory_score' => $resp,
            'coagulation_score' => $coag,
            'liver_score' => $liver,
            'cardiovascular_score' => $cv,
            'cns_score' => $cns,
            'renal_score' => $renal,
            'total_sofa_score' => $totalSofa,
            'predicted_mortality_rate_pct' => $mortality,
            'icu_acuity_tier' => $tier,
            'clinical_justification_note' => $note,
        ];
    }
}
