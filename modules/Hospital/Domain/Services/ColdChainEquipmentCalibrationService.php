<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Services;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * ColdChainEquipmentCalibrationService
 *
 * Mengelola riwayat kalibrasi probe suhu termometer PT100/NTC/Data Logger
 * pada refrigerator & ultra-low temperature freezer farmasi (ISO/IEC 17025 & BPOM).
 * Menganalisis sensor drift, ketidakpastian pengukuran (uncertainty budget),
 * dan menerbitkan status kelayakan alat.
 */
class ColdChainEquipmentCalibrationService
{
    public const STATUS_CALIBRATED_ACCURATE = 'CALIBRATED_ACCURATE';

    public const STATUS_DRIFT_COMPENSATED = 'DRIFT_COMPENSATED';

    public const STATUS_CALIBRATION_DUE = 'CALIBRATION_DUE';

    public const STATUS_OUT_OF_SPECIFICATION = 'OUT_OF_SPECIFICATION';

    /**
     * Toleransi maksimum penyimpangan probe farmasi standar BPOM (±0.50 °C).
     */
    public const MAX_ALLOWED_ERROR_CELSIUS = 0.50;

    /**
     * Periode validitas kalibrasi dalam hari (biasanya 365 hari = 1 tahun).
     */
    public const CALIBRATION_INTERVAL_DAYS = 365;

    /**
     * Evaluasi kelayakan sensor berdasarkan data sertifikat kalibrasi terkini.
     *
     * @param  string  $sensorDeviceCode  ID atau serial number probe
     * @param  DateTimeImmutable  $lastCalibrationDate  Tanggal sertifikat kalibrasi terakhir
     * @param  DateTimeImmutable  $evaluationDate  Tanggal asesmen saat ini
     * @param  array<int, array{set_point: float, measured_average: float, expanded_uncertainty: float}>  $calibrationPoints
     * @return array{
     *     sensor_code: string,
     *     status: string,
     *     is_valid_for_operation: bool,
     *     max_systematic_error: float,
     *     max_measurement_uncertainty: float,
     *     days_until_expiration: int,
     *     requires_recalibration: bool,
     *     recommended_correction_offset: float,
     *     justification: string
     * }
     */
    public function evaluateCalibrationStatus(
        string $sensorDeviceCode,
        DateTimeImmutable $lastCalibrationDate,
        DateTimeImmutable $evaluationDate,
        array $calibrationPoints
    ): array {
        if (empty($sensorDeviceCode)) {
            throw new InvalidArgumentException('Sensor device code tidak boleh kosong');
        }
        if (empty($calibrationPoints)) {
            throw new InvalidArgumentException('Titik kalibrasi minimal 1 titik uji');
        }

        $daysElapsed = (int) $lastCalibrationDate->diff($evaluationDate)->format('%r%a');
        $daysUntilExpiration = self::CALIBRATION_INTERVAL_DAYS - $daysElapsed;

        $maxAbsoluteError = 0.0;
        $maxUncertainty = 0.0;
        $sumError = 0.0;

        foreach ($calibrationPoints as $pt) {
            $error = $pt['measured_average'] - $pt['set_point'];
            $absError = abs($error);
            if ($absError > $maxAbsoluteError) {
                $maxAbsoluteError = $absError;
            }
            if ($pt['expanded_uncertainty'] > $maxUncertainty) {
                $maxUncertainty = $pt['expanded_uncertainty'];
            }
            $sumError += $error;
        }

        $avgError = $sumError / count($calibrationPoints);
        $recommendedCorrectionOffset = -1.0 * round($avgError, 3);

        $status = self::STATUS_CALIBRATED_ACCURATE;
        $isValidForOperation = true;
        $requiresRecalibration = false;
        $justification = 'Probe terkalibrasi presisi dan memenuhi kriteria toleransi CPOB.';

        if ($daysUntilExpiration <= 0) {
            $status = self::STATUS_CALIBRATION_DUE;
            $isValidForOperation = false;
            $requiresRecalibration = true;
            $justification = sprintf('Masa sertifikat kalibrasi telah kedaluwarsa (%d hari lampau).', abs($daysUntilExpiration));
        } elseif ($maxAbsoluteError > self::MAX_ALLOWED_ERROR_CELSIUS) {
            $status = self::STATUS_OUT_OF_SPECIFICATION;
            $isValidForOperation = false;
            $requiresRecalibration = true;
            $justification = sprintf(
                'Penyimpangan maksimum probe (%.2f°C) melampaui batas toleransi (±%.2f°C). Alat harus ditarik dari lini cold chain.',
                $maxAbsoluteError,
                self::MAX_ALLOWED_ERROR_CELSIUS
            );
        } elseif ($maxAbsoluteError > 0.25) {
            $status = self::STATUS_DRIFT_COMPENSATED;
            $isValidForOperation = true;
            $requiresRecalibration = false;
            $justification = sprintf(
                'Terdeteksi drift sensor moderat (%.2f°C). Direkomendasikan kalibrasi offset firmware sebesar %.2f°C.',
                $maxAbsoluteError,
                $recommendedCorrectionOffset
            );
        }

        return [
            'sensor_code' => $sensorDeviceCode,
            'status' => $status,
            'is_valid_for_operation' => $isValidForOperation,
            'max_systematic_error' => round($maxAbsoluteError, 3),
            'max_measurement_uncertainty' => round($maxUncertainty, 3),
            'days_until_expiration' => $daysUntilExpiration,
            'requires_recalibration' => $requiresRecalibration,
            'recommended_correction_offset' => $recommendedCorrectionOffset,
            'justification' => $justification,
        ];
    }
}
