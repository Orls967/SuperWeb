<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Services;

use Modules\Hospital\Domain\Models\ExternalFactor;

/**
 * G7-D59: Gerbang Tunggal Pembacaan Faktor Kepekaan Eksternal Rumah Sakit.
 * Membaca parameter berversi dari tabel hsp_external_factors (T-5).
 */
class HospitalExternalFactors
{
    /**
     * Membaca nilai faktor eksternal aktif untuk kota dan tanggal tertentu.
     */
    public function getFactor(string $key, string $cityCode = 'JKT', ?string $effectiveDate = null): int
    {
        $date = $effectiveDate ?? now()->toDateString();

        $record = ExternalFactor::where('factor_key', $key)
            ->where('city_code', $cityCode)
            ->where('effective_from', '<=', $date)
            ->orderByDesc('effective_from')
            ->first();

        if ($record !== null) {
            return (int) $record->factor_value;
        }

        // Nilai fallback default berbasis aturan regulasi / asumsi
        return match ($key) {
            'epidemic_dengue_index' => 50,      // Indeks kasus DBD normal
            'air_quality_pm25_index' => 45,     // Kualitas udara normal
            'flood_access_blocked_hours' => 0,  // Tidak ada banjir
            'usd_exchange_rate' => 16000,       // Kurs USD/IDR default
            'medical_record_retention_years' => 25, // Permenkes 24/2022 Pasal 39
            default => 0,
        };
    }

    /**
     * Deteksi apakah terjadi lonjakan KLB DBD (indeks >= 150).
     */
    public function isDengueOutbreak(string $cityCode = 'JKT'): bool
    {
        return $this->getFactor('epidemic_dengue_index', $cityCode) >= 150;
    }

    /**
     * Deteksi apakah terjadi krisis kualitas udara / ISPA (PM2.5 > 150 ug/m3).
     */
    public function isAirQualityEmergency(string $cityCode = 'JKT'): bool
    {
        return $this->getFactor('air_quality_pm25_index', $cityCode) > 150;
    }

    /**
     * Deteksi apakah akses jalan RS terputus banjir (> 2 jam).
     */
    public function isFloodAccessBlocked(string $cityCode = 'JKT'): bool
    {
        return $this->getFactor('flood_access_blocked_hours', $cityCode) >= 2;
    }
}
