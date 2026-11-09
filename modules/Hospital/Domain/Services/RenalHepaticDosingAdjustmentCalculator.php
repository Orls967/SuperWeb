<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Services;

use InvalidArgumentException;

/**
 * Renal & Hepatic Dosing Adjustment Calculator.
 * Mengkalkulasi penyesuaian dosis obat berdasarkan status klirens organ eliminasi:
 * 1. Estimasi laju filtrasi glomerulus (eGFR) via formula Cockcroft-Gault dan MDRD.
 * 2. Skor Child-Pugh (Kelas A, B, C) untuk disfungsi hepar kronis.
 * 3. Rekomendasi reduksi dosis persentase dan interval pemberian obat.
 */
class RenalHepaticDosingAdjustmentCalculator
{
    /**
     * Hitung Cockcroft-Gault Creatinine Clearance (CrCl).
     */
    public function calculateCreatinineClearance(
        int $ageYears,
        float $actualWeightKg,
        float $serumCreatinineMgDl,
        bool $isFemale = false,
        ?float $heightCm = null
    ): array {
        if ($serumCreatinineMgDl <= 0) {
            throw new InvalidArgumentException('Serum creatinine must be positive.');
        }

        // Hitung Ideal Body Weight (IBW) jika tinggi badan ada
        $ibwKg = $actualWeightKg;
        if ($heightCm !== null && $heightCm > 152.4) {
            $inchesOver5ft = ($heightCm - 152.4) / 2.54;
            $baseIbw = $isFemale ? 45.5 : 50.0;
            $calculatedIbw = $baseIbw + (2.3 * $inchesOver5ft);

            // Jika berat aktual > 120% IBW, gunakan Adjusted Body Weight (ABW)
            if ($actualWeightKg > (1.2 * $calculatedIbw)) {
                $ibwKg = $calculatedIbw + (0.4 * ($actualWeightKg - $calculatedIbw));
            } else {
                $ibwKg = $calculatedIbw;
            }
        }

        $crCl = ((140 - $ageYears) * $ibwKg) / (72 * $serumCreatinineMgDl);
        if ($isFemale) {
            $crCl *= 0.85;
        }

        $crClRounded = round(max(5.0, $crCl), 2);

        $stage = match (true) {
            $crClRounded >= 90.0 => 'CKD_STAGE_1_NORMAL',
            $crClRounded >= 60.0 => 'CKD_STAGE_2_MILD',
            $crClRounded >= 30.0 => 'CKD_STAGE_3_MODERATE',
            $crClRounded >= 15.0 => 'CKD_STAGE_4_SEVERE',
            default => 'CKD_STAGE_5_KIDNEY_FAILURE',
        };

        return [
            'cr_cl_ml_min' => $crClRounded,
            'body_weight_used_kg' => round($ibwKg, 2),
            'ckd_stage' => $stage,
        ];
    }

    /**
     * Menghitung Skor Child-Pugh untuk Penilaian Disfungsi Hati.
     *
     * @param  string  $ascitesGrade  NONE, MILD_SLIGHT, MODERATE_SEVERE
     * @param  string  $encephalopathyGrade  NONE, GRADE_1_2, GRADE_3_4
     * @return array{score: int, class: string, one_year_survival_pct: int}
     */
    public function calculateChildPugh(
        float $serumBilirubinMgDl,
        float $serumAlbuminGDl,
        float $inrValue,
        string $ascitesGrade = 'NONE',
        string $encephalopathyGrade = 'NONE'
    ): array {
        $score = 0;

        // 1. Bilirubin
        if ($serumBilirubinMgDl < 2.0) {
            $score += 1;
        } elseif ($serumBilirubinMgDl <= 3.0) {
            $score += 2;
        } else {
            $score += 3;
        }

        // 2. Albumin
        if ($serumAlbuminGDl > 3.5) {
            $score += 1;
        } elseif ($serumAlbuminGDl >= 2.8) {
            $score += 2;
        } else {
            $score += 3;
        }

        // 3. INR
        if ($inrValue < 1.7) {
            $score += 1;
        } elseif ($inrValue <= 2.2) {
            $score += 2;
        } else {
            $score += 3;
        }

        // 4. Asites
        $score += match (strtoupper($ascitesGrade)) {
            'MILD_SLIGHT' => 2,
            'MODERATE_SEVERE' => 3,
            default => 1,
        };

        // 5. Ensefalopati Hepatik
        $score += match (strtoupper($encephalopathyGrade)) {
            'GRADE_1_2' => 2,
            'GRADE_3_4' => 3,
            default => 1,
        };

        $class = match (true) {
            $score <= 6 => 'CLASS_A',
            $score <= 9 => 'CLASS_B',
            default => 'CLASS_C',
        };

        $survival = match ($class) {
            'CLASS_A' => 100,
            'CLASS_B' => 80,
            default => 45,
        };

        return [
            'score' => $score,
            'class' => $class,
            'one_year_survival_pct' => $survival,
        ];
    }

    /**
     * Rekomendasi penyesuaian dosis obat berdasarkan klirens ginjal.
     */
    public function getRenalDosingRecommendation(string $drugCode, float $crCl): array
    {
        $code = strtoupper(trim($drugCode));

        return match ($code) {
            'METFORMIN' => match (true) {
                $crCl >= 60 => ['dose_fraction' => 1.0, 'is_contraindicated' => false, 'notes' => 'Dosis normal 100%'],
                $crCl >= 45 => ['dose_fraction' => 0.75, 'is_contraindicated' => false, 'notes' => 'Dosis maksimal 1500 mg/hari'],
                $crCl >= 30 => ['dose_fraction' => 0.50, 'is_contraindicated' => false, 'notes' => 'Dosis maksimal 1000 mg/hari dengan monitor eGFR berkala'],
                default => ['dose_fraction' => 0.0, 'is_contraindicated' => true, 'notes' => 'Kontraindikasi mutlak pada CrCl < 30 mL/min karena risiko Lactic Acidosis'],
            },
            'GENTAMICIN' => match (true) {
                $crCl >= 60 => ['dose_fraction' => 1.0, 'interval_hours' => 24, 'is_contraindicated' => false, 'notes' => 'Interval standar 24 jam'],
                $crCl >= 40 => ['dose_fraction' => 1.0, 'interval_hours' => 36, 'is_contraindicated' => false, 'notes' => 'Perpanjang interval menjadi 36 jam'],
                $crCl >= 20 => ['dose_fraction' => 1.0, 'interval_hours' => 48, 'is_contraindicated' => false, 'notes' => 'Perpanjang interval menjadi 48 jam dengan TDM'],
                default => ['dose_fraction' => 0.5, 'interval_hours' => 48, 'is_contraindicated' => true, 'notes' => 'Risiko nefrotoksisitas akut berat'],
            },
            'ALLOPURINOL' => match (true) {
                $crCl >= 50 => ['dose_fraction' => 1.0, 'is_contraindicated' => false, 'notes' => 'Dosis penuh 300 mg/hari'],
                $crCl >= 20 => ['dose_fraction' => 0.66, 'is_contraindicated' => false, 'notes' => 'Dosis maksimal 200 mg/hari'],
                $crCl >= 10 => ['dose_fraction' => 0.33, 'is_contraindicated' => false, 'notes' => 'Dosis maksimal 100 mg/hari'],
                default => ['dose_fraction' => 0.33, 'interval_hours' => 48, 'is_contraindicated' => false, 'notes' => '100 mg setiap 48 jam'],
            },
            'GABAPENTIN' => match (true) {
                $crCl >= 60 => ['dose_fraction' => 1.0, 'is_contraindicated' => false, 'notes' => 'Dosis standar 900-1800 mg/hari dibagi 3 dosis'],
                $crCl >= 30 => ['dose_fraction' => 0.50, 'is_contraindicated' => false, 'notes' => '300-900 mg/hari dibagi 2 dosis'],
                $crCl >= 15 => ['dose_fraction' => 0.25, 'is_contraindicated' => false, 'notes' => '300 mg/hari satu kali pemberian'],
                default => ['dose_fraction' => 0.15, 'is_contraindicated' => false, 'notes' => '300 mg selang sehari setelah hemodialisis'],
            },
            default => [
                'dose_fraction' => $crCl < 30 ? 0.75 : 1.0,
                'is_contraindicated' => false,
                'notes' => 'Evaluasi individual sesuai farmakope obat terkait',
            ],
        };
    }
}
