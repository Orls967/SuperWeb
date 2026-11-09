<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Services;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * RadioactiveMedicalWasteTrackerService
 *
 * Mengelola peluruhan radioaktif limbah kedokteran nuklir RS (BAPETEN & IAEA Safety Standards).
 * Menghitung waktu simpan luruh (decay-in-storage) berdasarkan waktu paruh (half-life) radionuklida
 * seperti Tc-99m (6 jam), I-131 (8.02 hari), F-18 (109.7 menit) hingga mencapai batas clearance level
 * (< 10 Bq/g) sebelum diizinkan dibuang sebagai limbah medis B3 umum.
 */
class RadioactiveMedicalWasteTrackerService
{
    // Radioisotope half-life dalam jam
    public const HALF_LIFE_HOURS = [
        'TC_99M' => 6.006,        // Technetium-99m untuk SPECT scan
        'F_18' => 1.829,          // Fluorine-18 untuk PET-CT (109.7 menit)
        'I_131' => 192.48,        // Iodine-131 untuk terapi tiroid (8.02 hari)
        'LU_177' => 159.53,       // Lutetium-177 untuk radioterapi PRRT (6.647 hari)
        'GA_68' => 1.128,         // Gallium-68 untuk PET scan (67.71 menit)
    ];

    /**
     * Clearance level radioaktivitas aman pelepasan (IAEA GSR Part 3) = 10 Bq/g.
     */
    public const CLEARANCE_THRESHOLD_BQ_PER_GRAM = 10.0;

    /**
     * Hitung sisa radioaktivitas dan waktu peluruhan yang dibutuhkan.
     *
     * @param string $isotope Radionuklida
     * @param float $initialActivityMegaBq Aktivitas awal saat diproduksi/disimpan (MBq)
     * @param float $wasteWeightGrams Berat total paket limbah (gram)
     * @param DateTimeImmutable $storageStartTime Waktu mulai penyimpanan di decay room
     * @param DateTimeImmutable $currentTime Waktu pengecekan saat ini
     * @return array{
     *     isotope: string,
     *     elapsed_hours: float,
     *     half_lives_passed: float,
     *     current_activity_mega_bq: float,
     *     current_specific_activity_bq_per_g: float,
     *     is_cleared_for_medical_disposal: bool,
     *     estimated_hours_until_clearance: float,
     *     decay_room_lead_shielding_required: bool
     * }
     */
    public function calculateDecayStatus(
        string $isotope,
        float $initialActivityMegaBq,
        float $wasteWeightGrams,
        DateTimeImmutable $storageStartTime,
        DateTimeImmutable $currentTime
    ): array {
        $upperIsotope = strtoupper($isotope);
        if (!isset(self::HALF_LIFE_HOURS[$upperIsotope])) {
            throw new InvalidArgumentException("Radionuklida tidak terdaftar: {$isotope}");
        }
        if ($initialActivityMegaBq <= 0 || $wasteWeightGrams <= 0) {
            throw new InvalidArgumentException('Aktivitas awal dan berat limbah harus bernilai positif');
        }

        $halfLife = self::HALF_LIFE_HOURS[$upperIsotope];
        $elapsedSeconds = $currentTime->getTimestamp() - $storageStartTime->getTimestamp();
        $elapsedHours = max(0.0, $elapsedSeconds / 3600.0);

        // Rumus peluruhan eksponensial: A(t) = A0 * e^(-λ * t), di mana λ = ln(2) / T_1/2
        $decayConstant = log(2) / $halfLife;
        $currentActivityMegaBq = $initialActivityMegaBq * exp(-1.0 * $decayConstant * $elapsedHours);

        // Konversi ke Bq/gram: (Aktivitas MBq * 10^6) / berat gram
        $currentActivityBq = $currentActivityMegaBq * 1000000.0;
        $specificActivityBqPerG = $currentActivityBq / $wasteWeightGrams;

        // Clearance criterion
        $isCleared = ($specificActivityBqPerG <= self::CLEARANCE_THRESHOLD_BQ_PER_GRAM);

        // Hitung total jam yang dibutuhkan dari awal hingga mencapai clearance threshold
        $targetTotalBq = self::CLEARANCE_THRESHOLD_BQ_PER_GRAM * $wasteWeightGrams;
        $initialTotalBq = $initialActivityMegaBq * 1000000.0;

        $totalHoursToClearance = 0.0;
        if ($initialTotalBq > $targetTotalBq) {
            $totalHoursToClearance = (log($initialTotalBq / $targetTotalBq) / $decayConstant);
        }

        $remainingHours = max(0.0, $totalHoursToClearance - $elapsedHours);

        return [
            'isotope' => $upperIsotope,
            'elapsed_hours' => round($elapsedHours, 2),
            'half_lives_passed' => round($elapsedHours / $halfLife, 2),
            'current_activity_mega_bq' => round($currentActivityMegaBq, 4),
            'current_specific_activity_bq_per_g' => round($specificActivityBqPerG, 2),
            'is_cleared_for_medical_disposal' => $isCleared,
            'estimated_hours_until_clearance' => round($remainingHours, 2),
            'decay_room_lead_shielding_required' => ($currentActivityMegaBq > 1.0),
        ];
    }
}
