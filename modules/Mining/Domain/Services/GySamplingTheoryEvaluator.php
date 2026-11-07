<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Services;

use InvalidArgumentException;

/**
 * Evaluator Teori Sampling Pierre Gy (Gy's Sampling Theory) (D94-2).
 *
 * Menganalisis ketidakpastian pengambilan sampel material heterogen (bijih laterit, batubara ROM):
 * - Fundamental Sampling Error (FSE) kuadrat: s_FSE^2 = C * d^3 * (1/M_s - 1/M_l)
 *   di mana C adalah konstanta sampling (C = c * l * f * g):
 *   c = mineralogical factor (faktor mineralogi)
 *   l = liberation factor (faktor liberasi)
 *   f = particle shape factor (faktor bentuk partikel, lazimnya 0.5)
 *   g = granulometric factor (faktor distribusi ukuran butir, lazimnya 0.25)
 *   d = ukuran butir terbesar (top-size d95 dalam cm)
 *   M_s = massa sampel yang diambil (gram)
 *   M_l = massa lot keseluruhan (gram)
 */
final class GySamplingTheoryEvaluator
{
    /**
     * @param array{
     *     lot_mass_kg: float,
     *     sample_mass_kg: float,
     *     top_size_d95_mm: float,
     *     mineralogical_density_g_cm3?: float,
     *     liberation_factor?: float,
     *     shape_factor?: float,
     *     granulometric_factor?: float
     * } $data
     * @return array{
     *     lot_mass_kg: float,
     *     sample_mass_kg: float,
     *     top_size_cm: float,
     *     sampling_constant_c: float,
     *     fundamental_sampling_variance: float,
     *     relative_standard_deviation_pct: float,
     *     is_sampling_statistically_representative: bool,
     *     recommended_minimum_sample_kg: float,
     *     grade_risk_verdict: string
     * }
     */
    public function evaluate(array $data): array
    {
        $lotKg = (float) $data['lot_mass_kg'];
        $sampleKg = (float) $data['sample_mass_kg'];
        $d95Mm = (float) $data['top_size_d95_mm'];
        $cMineral = (float) ($data['mineralogical_density_g_cm3'] ?? 3.2); // Densitas rata-rata bijih/batubara
        $lLiberation = (float) ($data['liberation_factor'] ?? 0.8);
        $fShape = (float) ($data['shape_factor'] ?? 0.5);
        $gGranulo = (float) ($data['granulometric_factor'] ?? 0.25);

        if ($lotKg <= 0 || $sampleKg <= 0 || $d95Mm <= 0) {
            throw new InvalidArgumentException('Massa lot, massa sampel, dan ukuran partikel d95 harus bernilai positif.');
        }

        if ($sampleKg > $lotKg) {
            throw new InvalidArgumentException('Massa sampel tidak boleh melebihi massa total lot.');
        }

        // Konversi satuan ke CGS (cm dan gram)
        $dCm = $d95Mm / 10.0;
        $msGram = $sampleKg * 1000.0;
        $mlGram = $lotKg * 1000.0;

        // C = c * l * f * g
        $constantC = round($cMineral * $lLiberation * $fShape * $gGranulo, 4);

        // s_FSE^2 = C * d^3 * (1/M_s - 1/M_l)
        $dCubed = pow($dCm, 3);
        $massFactor = (1.0 / $msGram) - (1.0 / $mlGram);
        $varianceFSE = max(0.0, $constantC * $dCubed * $massFactor);

        // Standar deviasi relatif FSE (%)
        $rsdPct = round(sqrt($varianceFSE) * 100.0, 2);

        // Rekomendasi massa minimum sampel untuk RSD <= 5% (s^2 <= 0.0025)
        $targetVariance = 0.0025; // 5% deviasi batas komersial ISO 13909
        $minSampleGram = ($constantC * $dCubed) / $targetVariance;
        $minSampleKg = round($minSampleGram / 1000.0, 2);

        $isRepresentative = ($rsdPct <= 5.0);

        $verdict = $isRepresentative
            ? "Sampel representatif secara statistik (RSD FSE: {$rsdPct}% <= 5.0%). Protokol assay lab dapat dipertanggungjawabkan."
            : "GALAT SAMPLING TINGGI (RSD FSE: {$rsdPct}% > 5.0%). Tingkatkan massa sampel minimal menjadi {$minSampleKg} kg atau lakukan crushing sebelum sub-sampling.";

        return [
            'lot_mass_kg' => $lotKg,
            'sample_mass_kg' => $sampleKg,
            'top_size_cm' => round($dCm, 2),
            'sampling_constant_c' => $constantC,
            'fundamental_sampling_variance' => round($varianceFSE, 8),
            'relative_standard_deviation_pct' => $rsdPct,
            'is_sampling_statistically_representative' => $isRepresentative,
            'recommended_minimum_sample_kg' => $minSampleKg,
            'grade_risk_verdict' => $verdict,
        ];
    }
}
