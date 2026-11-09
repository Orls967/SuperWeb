<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\Services;

use Modules\Hospital\Domain\Models\ImagingStudy;
use Modules\Hospital\Domain\Models\RadiationDosimetryLog;

/**
 * Layanan Pemantauan Proteksi Radiasi & Dosimetri Pasien CT-Scan (Fase 105).
 */
class CtRadiationDoseTracker
{
    /**
     * Ambang batas Diagnostic Reference Levels (DRL) BAPETEN.
     *
     * @var array<string, array{ctdi_mgy: float, dlp_mgy_cm: float, k_factor: float}>
     */
    public const NATIONAL_DRL_THRESHOLDS = [
        'HEAD' => ['ctdi_mgy' => 60.0, 'dlp_mgy_cm' => 1000.0, 'k_factor' => 0.0021],
        'CHEST' => ['ctdi_mgy' => 15.0, 'dlp_mgy_cm' => 450.0, 'k_factor' => 0.0140],
        'ABDOMEN' => ['ctdi_mgy' => 20.0, 'dlp_mgy_cm' => 750.0, 'k_factor' => 0.0150],
        'PELVIS' => ['ctdi_mgy' => 20.0, 'dlp_mgy_cm' => 600.0, 'k_factor' => 0.0150],
        'CARDIAC' => ['ctdi_mgy' => 40.0, 'dlp_mgy_cm' => 600.0, 'k_factor' => 0.0140],
        'SPINE' => ['ctdi_mgy' => 25.0, 'dlp_mgy_cm' => 800.0, 'k_factor' => 0.0160],
    ];

    /**
     * Merekam dan mengevaluasi dosimetri radiasi studi CT-Scan.
     *
     * @return array{is_drl_exceeded: bool, effective_dose_msv: float, ssde_mgy: float, log: RadiationDosimetryLog}
     */
    public function recordDoseMetrics(
        ImagingStudy $study,
        float $ctdiVolMgy,
        float $scanLengthCm,
        ?float $patientEffectiveDiameterCm = null
    ): array {
        $bodyKey = $this->determineAnatomyKey($study->body_part);
        $drlConfig = self::NATIONAL_DRL_THRESHOLDS[$bodyKey] ?? ['ctdi_mgy' => 30.0, 'dlp_mgy_cm' => 800.0, 'k_factor' => 0.015];

        // 1. Hitung DLP = CTDIvol * scan_length
        $dlpMgyCm = $ctdiVolMgy * $scanLengthCm;

        // 2. Hitung Effective Dose (E) = DLP * k_factor
        $effectiveDoseMsv = $dlpMgyCm * $drlConfig['k_factor'];

        // 3. Hitung SSDE (Size-Specific Dose Estimate) berbasis AAPM Report 204
        $ssdeConversionFactor = $this->calculateAapm204Factor($patientEffectiveDiameterCm);
        $ssdeMgy = $ctdiVolMgy * $ssdeConversionFactor;

        // 4. Evaluasi apakah melebihi DRL
        $drlExceeded = ($ctdiVolMgy > $drlConfig['ctdi_mgy']) || ($dlpMgyCm > $drlConfig['dlp_mgy_cm']);

        $reviewNote = $drlExceeded
            ? "PERINGATAN: Dosis CT melampaui DRL {$bodyKey} (CTDI {$ctdiVolMgy} > {$drlConfig['ctdi_mgy']} atau DLP {$dlpMgyCm} > {$drlConfig['dlp_mgy_cm']}). Evaluasi ALARA diperlukan."
            : 'Dosis radiasi patuh terhadap DRL nasional.';

        $log = RadiationDosimetryLog::create([
            'study_id' => $study->id,
            'patient_id' => $study->patient_id,
            'ctdi_vol_mgy' => $ctdiVolMgy,
            'dlp_mgy_cm' => $dlpMgyCm,
            'effective_dose_msv' => round($effectiveDoseMsv, 3),
            'ssde_mgy' => round($ssdeMgy, 2),
            'drl_exceeded' => $drlExceeded,
            'safety_officer_review' => $reviewNote,
        ]);

        return [
            'is_drl_exceeded' => $drlExceeded,
            'effective_dose_msv' => round($effectiveDoseMsv, 3),
            'ssde_mgy' => round($ssdeMgy, 2),
            'log' => $log,
        ];
    }

    protected function determineAnatomyKey(string $bodyPart): string
    {
        $part = strtoupper($bodyPart);
        if (str_contains($part, 'HEAD') || str_contains($part, 'BRAIN')) {
            return 'HEAD';
        }
        if (str_contains($part, 'CHEST') || str_contains($part, 'THORAX') || str_contains($part, 'LUNG')) {
            return 'CHEST';
        }
        if (str_contains($part, 'ABDOMEN')) {
            return 'ABDOMEN';
        }
        if (str_contains($part, 'PELVIS')) {
            return 'PELVIS';
        }
        if (str_contains($part, 'HEART') || str_contains($part, 'CORONARY') || str_contains($part, 'CARDIAC')) {
            return 'CARDIAC';
        }
        if (str_contains($part, 'SPINE')) {
            return 'SPINE';
        }

        return 'ABDOMEN';
    }

    protected function calculateAapm204Factor(?float $diameterCm): float
    {
        if ($diameterCm === null || $diameterCm <= 0.0) {
            return 1.0;
        }

        // Formula eksponensial AAPM 204: f_size = a * exp(-b * diameter)
        // Untuk phantom 32 cm tubuh: a = 3.704, b = 0.0367
        return (float) (3.70436 * exp(-0.03671937 * $diameterCm));
    }
}
