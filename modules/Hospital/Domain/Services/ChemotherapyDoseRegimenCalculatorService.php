<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Services;

use InvalidArgumentException;

/**
 * ChemotherapyDoseRegimenCalculatorService
 *
 * Menghitung luas permukaan tubuh (Body Surface Area / BSA) dan dosis sitotoksik kemoterapi
 * secara aman untuk penyiapan obat farmasi onkologi steril (Permenkes Standar Pelayanan Farmasi):
 *   1. Perhitungan BSA menggunakan rumus Mosteller: BSA (m²) = sqrt( (TB_cm * BB_kg) / 3600 ).
 *   2. Perhitungan Klirens Kreatinin (Cockcroft-Gault CrCl) untuk penyesuaian dosis nefrotoksik.
 *   3. Penentuan dosis Carboplatin menggunakan Calvert Formula: Total Dosis (mg) = Target AUC * (GFR + 25).
 *   4. Verifikasi batas kumulatif kardiotoksik Doxorubicin (maksimum 450-550 mg/m² seumur hidup).
 */
class ChemotherapyDoseRegimenCalculatorService
{
    public const MAX_LIFETIME_DOXORUBICIN_MG_PER_M2 = 450.0;
    public const MAX_GFR_CALVERT_CAP = 125.0; // Batas atas GFR standar ASCO untuk Calvert formula

    /**
     * Hitung BSA pasien berdasarkan rumus Mosteller.
     */
    public function calculateBsaMosteller(float $heightCm, float $weightKg): float
    {
        if ($heightCm <= 0 || $weightKg <= 0) {
            throw new InvalidArgumentException('Tinggi badan dan berat badan harus lebih besar dari nol');
        }

        // BSA = sqrt((TB * BB) / 3600)
        return round(sqrt(($heightCm * $weightKg) / 3600.0), 2);
    }

    /**
     * Hitung estimasi Klirens Kreatinin menggunakan persamaan Cockcroft-Gault.
     */
    public function calculateCreatinineClearance(
        int $ageYears,
        float $weightKg,
        float $serumCreatinineMgPerDl,
        bool $isFemale
    ): float {
        if ($ageYears <= 0 || $weightKg <= 0 || $serumCreatinineMgPerDl <= 0) {
            throw new InvalidArgumentException('Parameter usia, berat badan, dan serum kreatinin harus bernilai positif');
        }

        // CrCl = [ (140 - usia) * BB ] / (72 * Cr) * (0.85 jika wanita)
        $crCl = ((140.0 - $ageYears) * $weightKg) / (72.0 * $serumCreatinineMgPerDl);
        if ($isFemale) {
            $crCl *= 0.85;
        }

        return round($crCl, 1);
    }

    /**
     * Hitung dosis Carboplatin dengan Calvert Formula.
     */
    public function calculateCarboplatinCalvertDose(float $targetAuc, float $creatinineClearance): float
    {
        if ($targetAuc <= 0 || $creatinineClearance <= 0) {
            throw new InvalidArgumentException('Target AUC dan klirens kreatinin harus lebih besar dari nol');
        }

        // ASCO capping rule: GFR dibatasi maksimum 125 mL/min untuk mencegah overdosis toksik
        $cappedGfr = min(self::MAX_GFR_CALVERT_CAP, $creatinineClearance);
        // Dosis (mg) = Target AUC * (GFR + 25)
        $totalDoseMg = $targetAuc * ($cappedGfr + 25.0);

        return round($totalDoseMg, 1);
    }

    /**
     * Verifikasi batas kumulatif kardiotoksisitas Doxorubicin / Anthracycline.
     *
     * @param float $bsa Luas permukaan tubuh pasien (m²)
     * @param float $plannedDoseMg Dosis yang direncanakan untuk siklus saat ini (mg)
     * @param float $priorLifetimeCumulativeMg Total dosis kumulatif yang sudah pernah diterima pasien sebelumnya (mg)
     * @return array{
     *     bsa_m2: float,
     *     planned_dose_mg: float,
     *     new_lifetime_cumulative_mg: float,
     *     lifetime_cumulative_per_m2: float,
     *     is_within_safe_lifetime_limit: bool,
     *     warning_message: ?string
     * }
     */
    public function verifyAnthracyclineLifetimeLimit(
        float $bsa,
        float $plannedDoseMg,
        float $priorLifetimeCumulativeMg
    ): array {
        if ($bsa <= 0) {
            throw new InvalidArgumentException('BSA harus lebih besar dari 0');
        }

        $newTotal = $priorLifetimeCumulativeMg + $plannedDoseMg;
        $cumulativePerM2 = round($newTotal / $bsa, 1);
        $isSafe = ($cumulativePerM2 <= self::MAX_LIFETIME_DOXORUBICIN_MG_PER_M2);

        $warning = null;
        if (!$isSafe) {
            $warning = sprintf(
                'Dosis kumulatif Doxorubicin seumur hidup (%.1f mg/m²) melampaui ambang batas aman kardiotoksisitas (%.1f mg/m²). Risiko kardiomiopati irreversibel & gagal jantung kongestif tinggi!',
                $cumulativePerM2,
                self::MAX_LIFETIME_DOXORUBICIN_MG_PER_M2
            );
        }

        return [
            'bsa_m2' => $bsa,
            'planned_dose_mg' => $plannedDoseMg,
            'new_lifetime_cumulative_mg' => $newTotal,
            'lifetime_cumulative_per_m2' => $cumulativePerM2,
            'is_within_safe_lifetime_limit' => $isSafe,
            'warning_message' => $warning,
        ];
    }
}
