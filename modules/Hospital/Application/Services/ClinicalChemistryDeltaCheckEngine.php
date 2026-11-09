<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\Services;

use Carbon\Carbon;
use InvalidArgumentException;

/**
 * Engine Evaluasi Delta Check & Reference Change Value (RCV) Laboratorium Klinis (Fase 105).
 *
 * Menerapkan rumus variasi biologis individual internasional (Fraser et al.):
 * RCV = 2^(1/2) * Z * sqrt(CV_A^2 + CV_I^2)
 * di mana:
 * - CV_A: Koefisien Variasi Analitik (analyser precision)
 * - CV_I: Koefisien Variasi Biologis Intra-individual
 * - Z: Multiplier confidence interval (1.96 untuk 95% dua sisi, 2.58 untuk 99%)
 *
 * Mengidentifikasi fluktuasi hasil ekstrim antara pengukuran saat ini dan sebelumnya
 * untuk mendeteksi potensi tertukarnya spesimen (misidentification) atau perburukan klinis akut.
 */
class ClinicalChemistryDeltaCheckEngine
{
    /**
     * Konfigurasi parameter biologis standar [CV_A (%), CV_I (%), MaxDaysBetweenChecks].
     * Sumber: Westgard Biological Variation Database & CLSI guidelines.
     *
     * @var array<string, array{cv_a: float, cv_i: float, max_days: int, absolute_delta_limit?: float}>
     */
    private const PARAMETER_PROFILES = [
        'POTASSIUM' => [
            'cv_a' => 1.5,
            'cv_i' => 4.6,
            'max_days' => 7,
            'absolute_delta_limit' => 1.5, // mEq/L
        ],
        'SODIUM' => [
            'cv_a' => 0.8,
            'cv_i' => 0.6,
            'max_days' => 7,
            'absolute_delta_limit' => 8.0, // mEq/L
        ],
        'CREATININE' => [
            'cv_a' => 2.0,
            'cv_i' => 5.3,
            'max_days' => 14,
            'absolute_delta_limit' => 0.5, // mg/dL
        ],
        'UREA_NITROGEN' => [
            'cv_a' => 2.5,
            'cv_i' => 12.3,
            'max_days' => 14,
            'absolute_delta_limit' => 15.0, // mg/dL
        ],
        'HEMOGLOBIN' => [
            'cv_a' => 1.2,
            'cv_i' => 2.8,
            'max_days' => 7,
            'absolute_delta_limit' => 3.0, // g/dL
        ],
        'PLATELETS' => [
            'cv_a' => 3.5,
            'cv_i' => 9.1,
            'max_days' => 7,
            'absolute_delta_limit' => 100000.0, // /uL
        ],
        'ALBUMIN' => [
            'cv_a' => 1.8,
            'cv_i' => 3.2,
            'max_days' => 30,
            'absolute_delta_limit' => 0.8, // g/dL
        ],
        'GLUCOSE' => [
            'cv_a' => 2.1,
            'cv_i' => 5.6,
            'max_days' => 3,
            'absolute_delta_limit' => 80.0, // mg/dL
        ],
        'TOTAL_BILIRUBIN' => [
            'cv_a' => 3.0,
            'cv_i' => 21.8,
            'max_days' => 14,
            'absolute_delta_limit' => 2.0, // mg/dL
        ],
        'ALANINE_AMINOTRANSFERASE' => [
            'cv_a' => 3.8,
            'cv_i' => 19.4,
            'max_days' => 14,
            'absolute_delta_limit' => 50.0, // U/L
        ],
        'ASPARTATE_AMINOTRANSFERASE' => [
            'cv_a' => 3.2,
            'cv_i' => 12.3,
            'max_days' => 14,
            'absolute_delta_limit' => 45.0, // U/L
        ],
        'CALCIUM' => [
            'cv_a' => 1.4,
            'cv_i' => 2.1,
            'max_days' => 14,
            'absolute_delta_limit' => 1.0, // mg/dL
        ],
    ];

    /**
     * Hitung ambang batas Reference Change Value (RCV) persentase.
     *
     * @param  float  $cvA  Koefisien Variasi Analitik (persen)
     * @param  float  $cvI  Koefisien Variasi Biologis (persen)
     * @param  float  $z  Multiplier Z-score (1.96 untuk CI 95%, 2.58 untuk CI 99%)
     */
    public function calculateReferenceChangeValue(float $cvA, float $cvI, float $z = 1.96): float
    {
        if ($cvA < 0.0 || $cvI < 0.0) {
            throw new InvalidArgumentException('Koefisien variasi tidak boleh negatif.');
        }

        // RCV% = 2^(0.5) * Z * sqrt(cvA^2 + cvI^2)
        $rcv = sqrt(2.0) * $z * sqrt(pow($cvA, 2) + pow($cvI, 2));

        return round($rcv, 2);
    }

    /**
     * Evaluasi delta check antara hasil saat ini dan hasil historis.
     *
     * @param  string  $analyteCode  Kode analit atau tes (misal: 'POTASSIUM', 'CREATININE')
     * @param  float  $currentValue  Nilai pengukuran sekarang
     * @param  float  $previousValue  Nilai pengukuran sebelumnya
     * @param  Carbon  $currentDate  Waktu pengambilan spesimen sekarang
     * @param  Carbon  $previousDate  Waktu pengambilan spesimen sebelumnya
     * @param  float  $confidenceZ  Z-score multiplier (default 1.96 = 95%)
     * @return array{
     *     analyte: string,
     *     current_value: float,
     *     previous_value: float,
     *     absolute_diff: float,
     *     percent_diff: float,
     *     elapsed_days: float,
     *     rcv_limit_percent: float,
     *     is_delta_exceeded: bool,
     *     is_window_valid: bool,
     *     severity: string,
     *     suspect_misidentification: bool,
     *     recommendation: string
     * }
     */
    public function evaluateDelta(
        string $analyteCode,
        float $currentValue,
        float $previousValue,
        Carbon $currentDate,
        Carbon $previousDate,
        float $confidenceZ = 1.96
    ): array {
        $analyteKey = strtoupper($analyteCode);
        $profile = self::PARAMETER_PROFILES[$analyteKey] ?? [
            'cv_a' => 3.0,
            'cv_i' => 10.0,
            'max_days' => 7,
            'absolute_delta_limit' => null,
        ];

        $elapsedHours = $currentDate->diffInRealHours($previousDate);
        $elapsedDays = round($elapsedHours / 24.0, 2);
        $isWindowValid = $elapsedDays <= $profile['max_days'];

        $absoluteDiff = round(abs($currentValue - $previousValue), 4);
        $percentDiff = 0.0;
        if ($previousValue > 0.00001) {
            $percentDiff = round((($currentValue - $previousValue) / $previousValue) * 100.0, 2);
        }

        $rcvPercent = $this->calculateReferenceChangeValue($profile['cv_a'], $profile['cv_i'], $confidenceZ);

        $absPercentDiff = abs($percentDiff);
        $exceedsRcv = $absPercentDiff > $rcvPercent;
        $exceedsAbsolute = false;

        if (isset($profile['absolute_delta_limit']) && $profile['absolute_delta_limit'] !== null) {
            $exceedsAbsolute = $absoluteDiff > $profile['absolute_delta_limit'];
        }

        $isDeltaExceeded = ($exceedsRcv || $exceedsAbsolute) && $isWindowValid;

        // Klasifikasi keparahan & kecurigaan tertukarnya spesimen
        $suspectMisidentification = false;
        $severity = 'NORMAL';
        $recommendation = 'Hasil delta check stabil dan konsisten dengan variasi fisiologis pasien.';

        if ($isDeltaExceeded) {
            // Jika perubahan melebihi 2x RCV atau terjadi dalam waktu < 24 jam dengan selisih ekstrim
            if ($absPercentDiff > ($rcvPercent * 2.0) && $elapsedDays <= 1.0) {
                $severity = 'CRITICAL_MISMATCH';
                $suspectMisidentification = true;
                $recommendation = 'PERINGATAN TINGGI: Perubahan drastis >2x RCV dalam <24 jam. Tahan hasil dan lakukan verifikasi identitas tabung serta flebotomi ulang.';
            } elseif ($absPercentDiff > ($rcvPercent * 1.5)) {
                $severity = 'HIGH';
                $recommendation = 'Delta check melampaui 1.5x ambang RCV. Lakukan pemeriksaan ulang pada instrumen (rerun) sebelum validasi.';
            } else {
                $severity = 'WARNING';
                $recommendation = 'Delta check melebihi variasi biologis standar. Konfirmasikan riwayat klinis dan terapi pasien.';
            }
        } elseif (! $isWindowValid && ($exceedsRcv || $exceedsAbsolute)) {
            $severity = 'UNVERIFIED_WINDOW';
            $recommendation = 'Perubahan melebihi ambang namun jarak waktu antar-spesimen melebihi rentang validasi delta check.';
        }

        return [
            'analyte' => $analyteKey,
            'current_value' => $currentValue,
            'previous_value' => $previousValue,
            'absolute_diff' => $absoluteDiff,
            'percent_diff' => $percentDiff,
            'elapsed_days' => $elapsedDays,
            'rcv_limit_percent' => $rcvPercent,
            'is_delta_exceeded' => $isDeltaExceeded,
            'is_window_valid' => $isWindowValid,
            'severity' => $severity,
            'suspect_misidentification' => $suspectMisidentification,
            'recommendation' => $recommendation,
        ];
    }

    /**
     * Dapatkan daftar seluruh profil parameter kimia klinis yang didukung.
     *
     * @return array<string, array{cv_a: float, cv_i: float, max_days: int, absolute_delta_limit?: float}>
     */
    public function getSupportedProfiles(): array
    {
        return self::PARAMETER_PROFILES;
    }
}
