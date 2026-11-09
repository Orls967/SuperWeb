<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\Services;

use InvalidArgumentException;

/**
 * Penganalisis Optimasi Dosis Radiasi & Kontrol Paparan Otomatis (AEC / ALARA Analyzer) (Fase 105).
 *
 * Menerapkan standar fisika medis dan radiologi digital IEC 62494-1:
 * 1. Deviation Index (DI):
 *    DI = 10 * log10(EI / EI_T)
 *    di mana:
 *    - EI: Exposure Index aktual terukur pada detektor
 *    - EI_T: Target Exposure Index untuk prosedur & anatomi tertentu
 * 2. Klasifikasi Kategori Paparan:
 *    - DI > +3.0       : Excessive Overexposure (Dosis berlebih, potensi bahaya radiasi)
 *    - +1.0 < DI <= +3.0 : Overexposed (Dosis lebih tinggi dari target)
 *    - -1.0 <= DI <= +1.0 : Target Exposure (Optimal ALARA)
 *    - -3.0 <= DI < -1.0 : Underexposed (Tingkat derau/noise meningkat)
 *    - DI < -3.0       : Severe Underexposure (Noise tinggi, risiko artefak kuantum mottle)
 * 3. Analisis Kualitas Citra:
 *    - Signal-to-Noise Ratio (SNR) = Mean_Signal / SD_Noise
 *    - Contrast-to-Noise Ratio (CNR) = |Mean_ROI - Mean_Background| / SD_Noise
 * 4. Rekomendasi Penyesuaian Faktor Eksposi (kVp & mAs):
 *    - Rule of 15% kVp untuk modulasi daya tembus dan reduksi dosis pasien.
 */
class RadiologyDoseOptimizationAecAnalyzer
{
    /**
     * Hitung Deviation Index (DI) berdasarkan standar IEC 62494-1.
     *
     * @param  float  $exposureIndex  Nilai EI aktual detektor
     * @param  float  $targetExposureIndex  Nilai target EI_T untuk anatomi & proyeksi
     * @return array{
     *     exposure_index: float,
     *     target_exposure_index: float,
     *     deviation_index: float,
     *     exposure_category: string,
     *     alara_compliance: bool,
     *     clinical_action: string,
     *     recommended_mas_multiplier: float
     * }
     */
    public function calculateDeviationIndex(float $exposureIndex, float $targetExposureIndex): array
    {
        if ($exposureIndex <= 0.0 || $targetExposureIndex <= 0.0) {
            throw new InvalidArgumentException('Nilai Exposure Index dan Target EI harus lebih besar dari 0.');
        }

        // DI = 10 * log10(EI / EI_T)
        $ratio = $exposureIndex / $targetExposureIndex;
        $di = round(10.0 * log10($ratio), 2);

        $category = 'TARGET_OPTIMAL';
        $alaraCompliant = true;
        $action = 'Eksposi optimal sesuai prinsip ALARA; tidak memerlukan koreksi teknik radiografi.';
        $masMultiplier = 1.0;

        if ($di > 3.0) {
            $category = 'EXCESSIVE_OVEREXPOSURE';
            $alaraCompliant = false;
            $action = 'Dosis radiasi pasien berlebih signifikan (>2x target). Wajib investigasi pengaturan AEC atau kesalahan pemilihan kamar ionisasi.';
            $masMultiplier = round(pow(10.0, -$di / 10.0), 2);
        } elseif ($di > 1.0) {
            $category = 'OVEREXPOSED';
            $alaraCompliant = false;
            $action = 'Paparan sedikit lebih tinggi dari target. Turunkan mAs sebesar 20-30% pada pemeriksaan berikutnya.';
            $masMultiplier = round(pow(10.0, -$di / 10.0), 2);
        } elseif ($di < -3.0) {
            $category = 'SEVERE_UNDEREXPOSURE';
            $alaraCompliant = false;
            $action = 'Under-exposed parah. Derau kuantum mottle tinggi dapat mengaburkan lesi mikro; pertimbangkan eksposi ulang.';
            $masMultiplier = round(pow(10.0, -$di / 10.0), 2);
        } elseif ($di < -1.0) {
            $category = 'UNDEREXPOSED';
            $alaraCompliant = true;
            $action = 'Paparan di bawah target tetapi citra masih memiliki kontras diagnostik memadai.';
            $masMultiplier = round(pow(10.0, -$di / 10.0), 2);
        }

        return [
            'exposure_index' => round($exposureIndex, 1),
            'target_exposure_index' => round($targetExposureIndex, 1),
            'deviation_index' => $di,
            'exposure_category' => $category,
            'alara_compliance' => $alaraCompliant,
            'clinical_action' => $action,
            'recommended_mas_multiplier' => $masMultiplier,
        ];
    }

    /**
     * Evaluasi rasio kontras ke derau (CNR) dan signal to noise (SNR) pada Region of Interest (ROI).
     *
     * @param  float  $meanSignalRoi  Rerata pixel intensity pada ROI lesi/target
     * @param  float  $meanSignalBackground  Rerata pixel intensity pada latar belakang jaringan sehat
     * @param  float  $sdNoise  Standar deviasi noise latar belakang
     * @return array{
     *     snr: float,
     *     cnr: float,
     *     is_rose_criterion_met: bool,
     *     image_quality_grade: string
     * }
     */
    public function evaluateImageQualityMetrics(
        float $meanSignalRoi,
        float $meanSignalBackground,
        float $sdNoise
    ): array {
        if ($sdNoise <= 0.0) {
            throw new InvalidArgumentException('Standar deviasi noise harus bernilai positif.');
        }

        $snr = round($meanSignalRoi / $sdNoise, 2);
        $contrastDiff = abs($meanSignalRoi - $meanSignalBackground);
        $cnr = round($contrastDiff / $sdNoise, 2);

        // Kriteria Rose (Albert Rose, 1948): Target dapat dideteksi 100% oleh mata manusia jika CNR >= 5.0
        $roseCriterion = $cnr >= 5.0;

        $grade = 'EXCELLENT';
        if ($cnr < 3.0) {
            $grade = 'POOR (HIGH NOISE)';
        } elseif ($cnr < 5.0) {
            $grade = 'ACCEPTABLE (BORDERLINE DETECTION)';
        }

        return [
            'snr' => $snr,
            'cnr' => $cnr,
            'is_rose_criterion_met' => $roseCriterion,
            'image_quality_grade' => $grade,
        ];
    }
}
