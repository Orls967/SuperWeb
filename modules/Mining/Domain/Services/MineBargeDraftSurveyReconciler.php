<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Services;

use InvalidArgumentException;

/**
 * Rekonsiliator Survei Sarat Tongkang / Kapal (Barge / Vessel Draft Survey) (D94-2, D94-7).
 *
 * Menghitung tonase kargo komoditas menggunakan prinsip Archimedes:
 * 1. Mean Draft Correction (Quarter Mean):
 *    Draft Koreksi = (Draft_Fwd + 6*Draft_Mid + Draft_Aft) / 8
 * 2. Koreksi Densitas Air (Water Density Correction):
 *    Displacement Koreksi = Displacement Tabel x (Densitas Terukur / Densitas Standar 1.025)
 * 3. Deductibles: Dikurangi bobot konstanta kapal kosong (Lightship), ballast, bahan bakar, dan air tawar.
 */
final class MineBargeDraftSurveyReconciler
{
    /**
     * @param array{
     *     barge_code: string,
     *     initial_fwd_draft_m: float,
     *     initial_mid_draft_m: float,
     *     initial_aft_draft_m: float,
     *     final_fwd_draft_m: float,
     *     final_mid_draft_m: float,
     *     final_aft_draft_m: float,
     *     measured_water_density_g_cm3: float,
     *     tons_per_centimeter_immersion_tpc: float,
     *     ballast_water_diff_tons?: float
     * } $data
     * @return array{
     *     barge_code: string,
     *     initial_quarter_mean_m: float,
     *     final_quarter_mean_m: float,
     *     draft_immersion_difference_cm: float,
     *     raw_cargo_displacement_tons: float,
     *     density_corrected_cargo_tons: int,
     *     hog_sag_deflection_mm: float,
     *     is_deflection_acceptable: bool,
     *     survey_verdict: string
     * }
     */
    public function reconcile(array $data): array
    {
        $code = trim((string) $data['barge_code']);
        $iFwd = (float) $data['initial_fwd_draft_m'];
        $iMid = (float) $data['initial_mid_draft_m'];
        $iAft = (float) $data['initial_aft_draft_m'];
        $fFwd = (float) $data['final_fwd_draft_m'];
        $fMid = (float) $data['final_mid_draft_m'];
        $fAft = (float) $data['final_aft_draft_m'];
        $density = (float) $data['measured_water_density_g_cm3'];
        $tpc = (float) $data['tons_per_centimeter_immersion_tpc'];
        $ballastDiff = (float) ($data['ballast_water_diff_tons'] ?? 0.0);

        if ($tpc <= 0 || $density <= 0 || $fMid <= $iMid) {
            throw new InvalidArgumentException('Parameter sarat akhir harus lebih besar dari sarat awal, dan TPC/densitas harus positif.');
        }

        // 1. Initial Quarter Mean Draft (Masa Kosong / Ringan)
        $initialMeanM = ($iFwd + (6.0 * $iMid) + $iAft) / 8.0;

        // 2. Final Quarter Mean Draft (Masa Bermuatan)
        $finalMeanM = ($fFwd + (6.0 * $fMid) + $fAft) / 8.0;

        // 3. Selisih Celup Sarat (dalam cm: 1 m = 100 cm)
        $deltaDraftM = $finalMeanM - $initialMeanM;
        $deltaDraftCm = $deltaDraftM * 100.0;

        // 4. Displacement Kasar = Delta Sarat (cm) x TPC (Ton/cm)
        $rawTons = ($deltaDraftCm * $tpc) - $ballastDiff;

        // 5. Koreksi Densitas Air (Air tawar sungai ~1.000 vs Air laut standar ~1.025 g/cm3)
        $densityFactor = $density / 1.025;
        $correctedTons = (int) round($rawTons * $densityFactor);

        // 6. Evaluasi Defleksi Hog / Sag (Kelenturan Lambung Tongkang)
        $meanEnds = ($fFwd + $fAft) / 2.0;
        $hogSagM = $fMid - $meanEnds; // Positif = Sagging (tengah melengkung ke bawah), Negatif = Hogging
        $hogSagMm = round($hogSagM * 1000.0, 1);

        $isDeflectionAcceptable = (abs($hogSagMm) <= 250.0); // Toleransi deformasi lambung <= 250 mm

        $verdict = $isDeflectionAcceptable
            ? "Hasil draft survey valid (Defleksi lambung: {$hogSagMm} mm). Tonase muat bersih terverifikasi: {$correctedTons} ton."
            : "DELEKSI HOG/SAG BERLEBIHAN ({$hogSagMm} mm > 250 mm). Risiko kerusakan struktural tongkang. Atur ulang distribusi timbunan kargo.";

        return [
            'barge_code' => $code,
            'initial_quarter_mean_m' => round($initialMeanM, 3),
            'final_quarter_mean_m' => round($finalMeanM, 3),
            'draft_immersion_difference_cm' => round($deltaDraftCm, 2),
            'raw_cargo_displacement_tons' => round($rawTons, 2),
            'density_corrected_cargo_tons' => $correctedTons,
            'hog_sag_deflection_mm' => $hogSagMm,
            'is_deflection_acceptable' => $isDeflectionAcceptable,
            'survey_verdict' => $verdict,
        ];
    }
}
