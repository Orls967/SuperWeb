<?php

declare(strict_types=1);

namespace Modules\Insurance\Application\Services;

use InvalidArgumentException;

/**
 * Rule Engine Underwriting Medis Asuransi Jiwa & Kesehatan.
 * Mengevaluasi BMI, riwayat komorbiditas, tekanan darah, dan merokok untuk menentukan
 * decision status: ACCEPT_STANDARD, ACCEPT_SUBSTANDARD (dengan loading rate bps), DEFER, atau DECLINE.
 */
final class MedicalUnderwritingEngine
{
    /**
     * @param  array{
     *     height_cm: int,
     *     weight_kg: int,
     *     systolic_bp: int,
     *     diastolic_bp: int,
     *     is_smoker: bool,
     *     has_diabetes: bool,
     *     has_hypertension: bool,
     *     has_heart_disease: bool,
     *     family_cancer_history: bool
     * }  $medicalData
     * @return array{
     *     decision: string,
     *     extra_mortality_loading_bps: int,
     *     bmi: float,
     *     risk_category: string,
     *     underwriting_notes: string
     * }
     */
    public function evaluate(array $medicalData): array
    {
        $heightM = $medicalData['height_cm'] / 100.0;
        if ($heightM <= 0.0) {
            throw new InvalidArgumentException('Tinggi badan harus lebih besar dari 0 cm.');
        }

        $bmi = round($medicalData['weight_kg'] / ($heightM * $heightM), 2);
        $loadingBps = 0;
        $reasons = [];

        // 1. Evaluasi BMI
        if ($bmi < 18.5) {
            $loadingBps += 250;
            $reasons[] = 'Underweight (BMI < 18.5)';
        } elseif ($bmi >= 30.0 && $bmi < 35.0) {
            $loadingBps += 500;
            $reasons[] = 'Obesitas Tingkat 1 (BMI 30 - 34.9)';
        } elseif ($bmi >= 35.0) {
            $loadingBps += 1000;
            $reasons[] = 'Obesitas Morbid (BMI >= 35)';
        }

        // 2. Tekanan Darah
        $sys = $medicalData['systolic_bp'];
        $dia = $medicalData['diastolic_bp'];
        if ($sys >= 160 || $dia >= 100) {
            $loadingBps += 1500;
            $reasons[] = "Hipertensi Derajat 2 ({$sys}/{$dia} mmHg)";
        } elseif ($sys >= 140 || $dia >= 90) {
            $loadingBps += 500;
            $reasons[] = "Hipertensi Derajat 1 ({$sys}/{$dia} mmHg)";
        }

        // 3. Kebiasaan Merokok
        if ($medicalData['is_smoker']) {
            $loadingBps += 750;
            $reasons[] = 'Perokok aktif (+750 bps mortality)';
        }

        // 4. Riwayat Penyakit Kritis
        if ($medicalData['has_heart_disease']) {
            return [
                'decision' => 'DECLINE',
                'extra_mortality_loading_bps' => 0,
                'bmi' => $bmi,
                'risk_category' => 'UNINSURABLE',
                'underwriting_notes' => 'Aplikasi ditolak karena adanya riwayat penyakit jantung koroner/struktural.',
            ];
        }

        if ($medicalData['has_diabetes']) {
            $loadingBps += 1000;
            $reasons[] = 'Diabetes Mellitus (+1000 bps)';
        }

        if ($medicalData['family_cancer_history']) {
            $loadingBps += 300;
            $reasons[] = 'Riwayat kanker keluarga (+300 bps)';
        }

        // Penentuan Keputusan Akhir
        if ($loadingBps === 0) {
            $decision = 'ACCEPT_STANDARD';
            $riskCategory = 'STANDARD_PREFERRED';
            $notes = 'Tertanggung memenuhi syarat kelas risiko standar tanpa pembebanan ekstra premi.';
        } elseif ($loadingBps <= 2500) {
            $decision = 'ACCEPT_SUBSTANDARD';
            $riskCategory = 'SUBSTANDARD_RATED';
            $notes = 'Tertanggung disetujui bersyarat dengan extra rating: '.implode('; ', $reasons);
        } else {
            $decision = 'DEFER';
            $riskCategory = 'HIGH_RISK_POSTPONED';
            $notes = 'Aplikasi ditunda untuk evaluasi medis lebih lanjut (total loading melebihi ambang batas 2500 bps).';
        }

        return [
            'decision' => $decision,
            'extra_mortality_loading_bps' => $loadingBps,
            'bmi' => $bmi,
            'risk_category' => $riskCategory,
            'underwriting_notes' => $notes,
        ];
    }
}

