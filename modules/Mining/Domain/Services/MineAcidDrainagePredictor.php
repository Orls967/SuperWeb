<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Services;

use InvalidArgumentException;

/**
 * Prediktor Pembentukan Air Asam Tambang (Acid Mine Drainage - AMD) (D94-6).
 *
 * Mengimplementasikan pengujian geokimia batuan penutup (overburden/waste rock):
 * - NAPP (Net Acid Producing Potential) = MPA (Maximum Potential Acidity) - ANC (Acid Neutralizing Capacity)
 *   MPA dihitung dari total sulfur pyritic (% S x 30.6 kg H2SO4/ton batuan).
 * - NAG (Net Acid Generation) pH:
 *   NAG pH < 4.5 & NAPP > 0 -> PAF (Potentially Acid Forming) -> Wajib enkapsulasi batuan kapur.
 *   NAG pH >= 4.5 & NAPP <= 0 -> NAF (Non-Acid Forming) -> Aman dijadikan topsoil atau tanggul.
 */
final class MineAcidDrainagePredictor
{
    /**
     * @param array{
     *     total_sulfur_pct: float,
     *     anc_kg_h2so4_per_ton: float,
     *     nag_ph: float,
     *     rock_sample_code: string
     * } $data
     * @return array{
     *     rock_sample_code: string,
     *     mpa_kg_h2so4_per_ton: float,
     *     anc_kg_h2so4_per_ton: float,
     *     napp_kg_h2so4_per_ton: float,
     *     nag_ph: float,
     *     classification: string,
     *     requires_limestone_encapsulation: bool,
     *     recommended_lime_dose_kg_per_ton: float,
     *     management_strategy: string
     * }
     */
    public function evaluate(array $data): array
    {
        $sulfurPct = (float) $data['total_sulfur_pct'];
        $anc = (float) $data['anc_kg_h2so4_per_ton'];
        $nagPh = (float) $data['nag_ph'];
        $sample = (string) $data['rock_sample_code'];

        if ($sulfurPct < 0.0 || $anc < 0.0 || $nagPh < 0.0 || $nagPh > 14.0) {
            throw new InvalidArgumentException('Nilai sulfur, ANC, dan rentang pH (0-14) harus valid.');
        }

        // MPA = % Sulfur x 30.6
        $mpa = round($sulfurPct * 30.6, 2);

        // NAPP = MPA - ANC
        $napp = round($mpa - $anc, 2);

        $classification = 'NAF';
        $requiresEncapsulation = false;
        $limeDose = 0.0;
        $strategy = 'Batuan Non-Acid Forming (NAF). Dapat digunakan langsung untuk penimbunan lereng atau pengurukan.';

        if ($nagPh < 4.5 && $napp > 0.0) {
            $classification = 'PAF_HIGH_CAPACITY';
            $requiresEncapsulation = true;
            // Dosis kapur tohor (CaO) untuk netralisasi asam = NAPP x 0.57 kg CaO/ton
            $limeDose = round($napp * 0.57, 2);
            $strategy = "Batuan Potentially Acid Forming (PAF) risiko tinggi. Wajib enkapsulasi selektif dalam sel lempung kedap air dengan penaburan kapur tohor {$limeDose} kg/ton.";
        } elseif ($nagPh < 4.5 && $napp <= 0.0) {
            $classification = 'PAF_LOW_UNCERTAIN';
            $requiresEncapsulation = true;
            $limeDose = 5.0;
            $strategy = 'Geokimia tidak pasti (NAG asam namun ANC tinggi). Lakukan uji kinetik kolom pelindian sebelum pembuangan.';
        } elseif ($nagPh >= 4.5 && $napp > 0.0) {
            $classification = 'UNCERTAIN_LAG_TIME';
            $requiresEncapsulation = false;
            $limeDose = round($napp * 0.25, 2);
            $strategy = 'Potensi asam terhambat (lag-time). Pantau pH rembesan kolam sedimentasi secara harian.';
        }

        return [
            'rock_sample_code' => $sample,
            'mpa_kg_h2so4_per_ton' => $mpa,
            'anc_kg_h2so4_per_ton' => round($anc, 2),
            'napp_kg_h2so4_per_ton' => $napp,
            'nag_ph' => round($nagPh, 2),
            'classification' => $classification,
            'requires_limestone_encapsulation' => $requiresEncapsulation,
            'recommended_lime_dose_kg_per_ton' => $limeDose,
            'management_strategy' => $strategy,
        ];
    }
}
