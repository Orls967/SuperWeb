<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Forestry;

use InvalidArgumentException;

/**
 * Kalkulator Volume Pohon Berdiri & Batang Kayu Kanonikal.
 *
 * Rumus silvikultur:
 * V = (pi / 4) * (DBH_m)^2 * Height_m * form_factor
 *
 * Untuk menjaga presisi integer tanpa kehilangan akurasi:
 * V_dm3 (liter) = round((pi / 4) * (dbh_cm / 100)^2 * height_m * (form_factor_pct / 100) * 1000)
 */
final class TreeVolumeCalculator
{
    /**
     * Menghitung volume dalam desimeter kubik (dm3 = liter) presisi integer.
     * 1 m3 = 1.000 dm3.
     */
    public static function calculateVolumeDm3(int $dbhCm, int $heightM, int $formFactorPct = 70): int
    {
        if ($dbhCm <= 0) {
            throw new InvalidArgumentException("Diameter pohon (DBH) harus positif: {$dbhCm} cm");
        }

        if ($heightM <= 0) {
            throw new InvalidArgumentException("Tinggi pohon harus positif: {$heightM} m");
        }

        if ($formFactorPct <= 0 || $formFactorPct > 100) {
            throw new InvalidArgumentException("Faktor bentuk (form factor) harus antara 1-100%: {$formFactorPct}%");
        }

        $radiusM = ($dbhCm / 100.0) / 2.0;
        $basalAreaM2 = M_PI * ($radiusM ** 2);
        $volumeM3 = $basalAreaM2 * $heightM * ($formFactorPct / 100.0);

        return (int) round($volumeM3 * 1000.0);
    }

    /**
     * Konversi dari dm3 ke m3 integer bulat (kebawah).
     */
    public static function toM3(int $volumeDm3): int
    {
        return intdiv($volumeDm3, 1000);
    }
}
