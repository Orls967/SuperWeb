<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Services;

use DomainException;

/**
 * Mesin Kodifikasi Klinis & Pengelompokan Kasus INA-CBGs Tingkat Lanjut (InaCbgClinicalGrouperEngine).
 * Mengimplementasikan:
 *   1. Klasifikasi Major Diagnostic Category (MDC) berdasarkan organ tubuh & sistem fisiologis.
 *   2. Analisis Complication and Comorbidity (CC) / Major Complication and Comorbidity (MCC).
 *   3. Penyesuaian tarif outlier berdasarkan batas bawah (trimmer low) dan batas atas (trimmer high) Length of Stay (LOS).
 *   4. Perhitungan biaya hari tambahan rawat intensif (ICU/NICU/PICU add-on rate).
 */
class InaCbgClinicalGrouperEngine
{
    /**
     * Peta Major Diagnostic Category (MDC) dari ICD-10.
     *
     * @var array<string, array{mdc: string, name: string}>
     */
    private const MDC_MAP = [
        'I' => ['mdc' => 'MDC-05', 'name' => 'Penyakit & Gangguan Sistem Sirkulasi / Kardiovaskular'],
        'J' => ['mdc' => 'MDC-04', 'name' => 'Penyakit & Gangguan Sistem Pernapasan / Respirasi'],
        'K' => ['mdc' => 'MDC-06', 'name' => 'Penyakit & Gangguan Sistem Pencernaan'],
        'E' => ['mdc' => 'MDC-10', 'name' => 'Penyakit Endokrin, Nutrisi, & Metabolik'],
        'N' => ['mdc' => 'MDC-11', 'name' => 'Penyakit & Gangguan Sistem Perkemihan & Ginjal'],
        'O' => ['mdc' => 'MDC-14', 'name' => 'Kehamilan, Persalinan, & Nifas'],
        'S' => ['mdc' => 'MDC-08', 'name' => 'Cedera, Keracunan, & Gangguan Muskuloskeletal'],
        'C' => ['mdc' => 'MDC-17', 'name' => 'Neoplasma Ganas & Onkologi'],
        'A' => ['mdc' => 'MDC-18', 'name' => 'Penyakit Menular & Infeksius'],
        'B' => ['mdc' => 'MDC-18', 'name' => 'Penyakit Menular & Parasit'],
    ];

    /**
     * Daftar diagnosa sekunder yang berkategori Major Complication/Comorbidity (MCC).
     *
     * @var array<string, int>
     */
    private const MCC_DIAGNOSES = [
        'R57.0' => 3, // Cardiogenic Shock
        'R57.2' => 3, // Septic Shock
        'J96.0' => 3, // Acute Respiratory Failure
        'I46.9' => 3, // Cardiac Arrest
        'N17.9' => 3, // Acute Kidney Failure
        'K72.0' => 3, // Acute Hepatic Failure
        'A41.9' => 3, // Sepsis Unspecified
        'I63.9' => 3, // Cerebral Infarction / Stroke
    ];

    /**
     * Daftar diagnosa sekunder yang berkategori Complication/Comorbidity (CC).
     *
     * @var array<string, int>
     */
    private const CC_DIAGNOSES = [
        'E11.9' => 1, // DM Tipe 2 Tanpa Komplikasi
        'I10' => 1,   // Hipertensi Esensial
        'N18.3' => 2, // GGK Stadium 3
        'J44.9' => 2, // PPOK
        'I50.9' => 2, // Gagal Jantung Kongestif
        'D64.9' => 1, // Anemia
        'E87.1' => 1, // Hiponatremia
        'E87.6' => 1, // Hipokalemia
    ];

    /**
     * Batas ambang Trimmer LOS (Length of Stay) standar untuk evaluasi outlier.
     *
     * @var array<string, array{low_trimmer_days: int, high_trimmer_days: int, daily_outlier_rate_idr: int}>
     */
    private const CBG_TRIMMER_STANDARDS = [
        'I-4-10' => ['low_trimmer_days' => 2, 'high_trimmer_days' => 14, 'daily_outlier_rate_idr' => 1_250_000],
        'I-4-12' => ['low_trimmer_days' => 2, 'high_trimmer_days' => 12, 'daily_outlier_rate_idr' => 950_000],
        'O-6-10' => ['low_trimmer_days' => 1, 'high_trimmer_days' => 5, 'daily_outlier_rate_idr' => 650_000],
        'O-6-12' => ['low_trimmer_days' => 2, 'high_trimmer_days' => 7, 'daily_outlier_rate_idr' => 850_000],
        'J-4-16' => ['low_trimmer_days' => 2, 'high_trimmer_days' => 10, 'daily_outlier_rate_idr' => 800_000],
        'K-1-10' => ['low_trimmer_days' => 2, 'high_trimmer_days' => 8, 'daily_outlier_rate_idr' => 900_000],
        'E-4-10' => ['low_trimmer_days' => 2, 'high_trimmer_days' => 9, 'daily_outlier_rate_idr' => 750_000],
    ];

    /**
     * Mengelompokkan kasus klinis secara komprehensif.
     *
     * @param  string  $primaryIcd10  Kode diagnosa primer
     * @param  array<int, string>  $secondaryIcd10List  Daftar kode diagnosa sekunder
     * @param  array<int, string>  $procedureIcd9List  Daftar tindakan medis
     * @param  int  $lengthOfStayDays  Durasi rawat inap aktual dalam hari
     * @param  int  $icuDays  Durasi rawat di ruang intensif (ICU/ICCU)
     * @param  string  $entitledClass  Kelas hak BPJS (CLASS_1, CLASS_2, CLASS_3)
     * @param  string  $hospitalType  Kelas RS (A, B, C, D)
     * @return array<string, mixed>
     */
    public function adjudicateClinicalGrouper(
        string $primaryIcd10,
        array $secondaryIcd10List,
        array $procedureIcd9List,
        int $lengthOfStayDays,
        int $icuDays = 0,
        string $entitledClass = 'CLASS_1',
        string $hospitalType = 'B'
    ): array {
        if ($lengthOfStayDays <= 0) {
            throw new DomainException('Durasi perawatan (Length of Stay) harus minimal 1 hari.');
        }

        $cleanPrimary = strtoupper(trim(explode('.', $primaryIcd10)[0]));
        $firstChar = substr($cleanPrimary, 0, 1);
        $mdcInfo = self::MDC_MAP[$firstChar] ?? ['mdc' => 'MDC-99', 'name' => 'Lain-lain / Multiorgan'];

        // Analisis skor komorbiditas (Cumulative Comorbidity Score)
        $comorbidityScore = 0;
        $mccFound = [];
        $ccFound = [];

        foreach ($secondaryIcd10List as $sec) {
            $cleanSec = strtoupper(trim($sec));
            if (isset(self::MCC_DIAGNOSES[$cleanSec])) {
                $score = self::MCC_DIAGNOSES[$cleanSec];
                $comorbidityScore += $score;
                $mccFound[] = $cleanSec;
            } elseif (isset(self::CC_DIAGNOSES[$cleanSec])) {
                $score = self::CC_DIAGNOSES[$cleanSec];
                $comorbidityScore += $score;
                $ccFound[] = $cleanSec;
            } else {
                $comorbidityScore += 1;
            }
        }

        // Tentukan Severity Level (I, II, III)
        $severityLevel = 'I';
        if (! empty($mccFound) || $comorbidityScore >= 6) {
            $severityLevel = 'III';
        } elseif (! empty($ccFound) || $comorbidityScore >= 2 || count($procedureIcd9List) >= 2) {
            $severityLevel = 'II';
        }

        // Tentukan Base CBG Group Code
        $baseCbgGroup = match ($cleanPrimary) {
            'I21', 'I50' => 'I-4-10',
            'O80' => 'O-6-10',
            'O82' => 'O-6-12',
            'J18' => 'J-4-16',
            'K35' => 'K-1-10',
            'E11' => 'E-4-10',
            default => 'Z-4-10',
        };

        $finalCbgCode = "{$baseCbgGroup}-{$severityLevel}";

        // Hitung Base Rate Tarif
        $baseRate = $this->calculateBaseRate($baseCbgGroup, $severityLevel, $hospitalType);

        // Hitung Penyesuaian Kelas Hak (Class 1 / 2 / 3)
        $classMultiplier = match (strtoupper($entitledClass)) {
            'CLASS_1' => 1.35,
            'CLASS_2' => 1.10,
            default => 0.90,
        };
        $standardTariff = (int) round($baseRate * $classMultiplier);

        // Evaluasi Trimmer / Outlier Days
        $trimmer = self::CBG_TRIMMER_STANDARDS[$baseCbgGroup] ?? [
            'low_trimmer_days' => 2,
            'high_trimmer_days' => 14,
            'daily_outlier_rate_idr' => 850_000,
        ];

        $outlierDays = 0;
        $outlierTariffIdr = 0;
        $outlierType = 'NORMAL';

        if ($lengthOfStayDays > $trimmer['high_trimmer_days']) {
            $outlierDays = $lengthOfStayDays - $trimmer['high_trimmer_days'];
            $outlierTariffIdr = $outlierDays * $trimmer['daily_outlier_rate_idr'];
            $outlierType = 'HIGH_OUTLIER';
        } elseif ($lengthOfStayDays < $trimmer['low_trimmer_days']) {
            $outlierType = 'LOW_TRIMMER';
        }

        // Add-on Rawat Khusus ICU/ICCU (Rp 1.500.000/hari tambahan di luar paket reguler)
        $icuAddonTariffIdr = $icuDays * 1_500_000;

        $finalAdjudicatedTariffIdr = $standardTariff + $outlierTariffIdr + $icuAddonTariffIdr;

        return [
            'primary_icd10' => $primaryIcd10,
            'mdc_code' => $mdcInfo['mdc'],
            'mdc_name' => $mdcInfo['name'],
            'cbg_code' => $finalCbgCode,
            'severity_level' => $severityLevel,
            'comorbidity_score' => $comorbidityScore,
            'mcc_count' => count($mccFound),
            'cc_count' => count($ccFound),
            'procedure_count' => count($procedureIcd9List),
            'base_rate_idr' => $baseRate,
            'standard_class_tariff_idr' => $standardTariff,
            'length_of_stay_days' => $lengthOfStayDays,
            'outlier_type' => $outlierType,
            'outlier_days' => $outlierDays,
            'outlier_tariff_idr' => $outlierTariffIdr,
            'icu_days' => $icuDays,
            'icu_addon_tariff_idr' => $icuAddonTariffIdr,
            'final_adjudicated_claim_idr' => $finalAdjudicatedTariffIdr,
            'is_dispute_risk' => $outlierType === 'HIGH_OUTLIER' && $comorbidityScore < 3,
        ];
    }

    private function calculateBaseRate(string $cbgPrefix, string $severity, string $hospitalClass): int
    {
        $raw = match ($cbgPrefix) {
            'I-4-10' => 12_500_000,
            'I-4-12' => 10_000_000,
            'O-6-12' => 8_500_000,
            'O-6-10' => 5_500_000,
            'J-4-16' => 6_500_000,
            'K-1-10' => 7_800_000,
            'E-4-10' => 6_000_000,
            default => 4_500_000,
        };

        $sevMul = match ($severity) {
            'III' => 1.85,
            'II' => 1.35,
            default => 1.0,
        };

        $hospMul = match (strtoupper($hospitalClass)) {
            'A' => 1.25,
            'B' => 1.0,
            'C' => 0.85,
            default => 0.75,
        };

        return (int) round($raw * $sevMul * $hospMul);
    }
}
