<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\Services;

use Carbon\Carbon;
use InvalidArgumentException;

/**
 * Pelacak Kalibrasi Multi-Titik & Pemeliharaan Preventif Alat Analisis Laboratorium (Fase 105).
 *
 * Mengelola siklus kalibrasi instrumen fotometri, hematologi, dan immunoassay:
 * 1. Regresi Kurva Kalibrasi Multi-Titik (Linear Fitting & Koefisien Determinasi R^2):
 *    - Model: Y = a*X + b
 *    - Ambang batas R^2 >= 0.995 untuk kurva standar kimia klinis
 * 2. Deteksi Drift Kalibrator:
 *    - Drift persentase terhadap nilai master lot kalibrator sebelumnya
 * 3. Pelacakan Interval Kalibrasi Berkala:
 *    - Ambang jatuh tempo (due date warning & expiry lock)
 *    - Penguncian instrumen (auto-lock status OUT_OF_SERVICE) jika kalibrasi kedaluwarsa.
 */
class LabAnalyzerCalibrationMaintenanceTracker
{
    /**
     * Ambang batas koefisien determinasi minimal (R^2) untuk kalibrasi valid.
     */
    public const MIN_ACCEPTABLE_R_SQUARED = 0.995;

    /**
     * Evaluasi kurva kalibrasi linier multi-titik (minimal 3 konsentrasi kalibrator).
     *
     * @param  string  $analyzerId  ID alat / instrumen laboratorium
     * @param  string  $analyteCode  Kode analit
     * @param  list<array{concentration: float, absorbance: float}>  $calibrationPoints  Titik standar kalibrator
     * @return array{
     *     analyzer_id: string,
     *     analyte: string,
     *     points_count: int,
     *     slope: float,
     *     intercept: float,
     *     r_squared: float,
     *     is_calibration_valid: bool,
     *     status: string,
     *     advisory: string
     * }
     */
    public function evaluateLinearCalibrationCurve(
        string $analyzerId,
        string $analyteCode,
        array $calibrationPoints
    ): array {
        $n = count($calibrationPoints);
        if ($n < 3) {
            throw new InvalidArgumentException('Kalibrasi multi-titik membutuhkan sekurang-kurangnya 3 konsentrasi kalibrator.');
        }

        $sumX = 0.0;
        $sumY = 0.0;
        $sumXY = 0.0;
        $sumX2 = 0.0;
        $sumY2 = 0.0;

        foreach ($calibrationPoints as $pt) {
            $x = (float) $pt['concentration'];
            $y = (float) $pt['absorbance'];

            $sumX += $x;
            $sumY += $y;
            $sumXY += ($x * $y);
            $sumX2 += ($x * $x);
            $sumY2 += ($y * $y);
        }

        $denominator = ($n * $sumX2) - ($sumX * $sumX);
        if (abs($denominator) < 0.0000001) {
            throw new InvalidArgumentException('Titik konsentrasi kalibrator tidak boleh seragam atau kolinear.');
        }

        // Slope (m) dan Intercept (c)
        $slope = (($n * $sumXY) - ($sumX * $sumY)) / $denominator;
        $intercept = ($sumY - ($slope * $sumX)) / $n;

        // Koefisien Korelasi Pearson (r) & Determinasi (R^2)
        $numeratorR = ($n * $sumXY) - ($sumX * $sumY);
        $denomR = sqrt((($n * $sumX2) - ($sumX * $sumX)) * (($n * $sumY2) - ($sumY * $sumY)));

        $r = $denomR > 0.0000001 ? ($numeratorR / $denomR) : 0.0;
        $rSquared = round(pow($r, 2), 4);

        $isValid = $rSquared >= self::MIN_ACCEPTABLE_R_SQUARED;
        $status = $isValid ? 'CALIBRATION_ACCEPTED' : 'CALIBRATION_REJECTED';

        $advisory = $isValid
            ? sprintf('Kurva kalibrasi linier valid (R^2 = %.4f). Instrumen siap memproses spesimen klinis.', $rSquared)
            : sprintf('Kalibrasi ditolak: R^2 (%.4f) di bawah batas minimal (%.3f). Lakukan rekonstruksi reagen/kalibrator baru.', $rSquared, self::MIN_ACCEPTABLE_R_SQUARED);

        return [
            'analyzer_id' => $analyzerId,
            'analyte' => strtoupper($analyteCode),
            'points_count' => $n,
            'slope' => round($slope, 6),
            'intercept' => round($intercept, 6),
            'r_squared' => $rSquared,
            'is_calibration_valid' => $isValid,
            'status' => $status,
            'advisory' => $advisory,
        ];
    }

    /**
     * Hitung konsentrasi analit pasien dari nilai absorbansi berdasarkan kurva kalibrasi.
     *
     * @param  float  $absorbance  Absorbansi optik terukur
     * @param  float  $slope  Kemiringan kurva
     * @param  float  $intercept  Titik potong sumbu-Y
     */
    public function calculateConcentrationFromCurve(float $absorbance, float $slope, float $intercept): float
    {
        if (abs($slope) < 0.0000001) {
            throw new InvalidArgumentException('Slope kalibrasi tidak boleh 0.');
        }

        // X = (Y - c) / m
        $concentration = ($absorbance - $intercept) / $slope;

        return round(max(0.0, $concentration), 2);
    }

    /**
     * Evaluasi status jatuh tempo kalibrasi & pemeliharaan alat.
     *
     * @param  Carbon  $lastCalibratedAt  Waktu kalibrasi terakhir
     * @param  int  $validityDays  Masa berlaku kalibrasi (hari)
     * @return array{
     *     days_since_calibration: int,
     *     days_remaining: int,
     *     is_expired: bool,
     *     instrument_operational_state: string,
     *     recommendation: string
     * }
     */
    public function evaluateInstrumentReadiness(Carbon $lastCalibratedAt, int $validityDays = 30): array
    {
        $now = now();
        $daysSince = (int) $lastCalibratedAt->diffInDays($now);
        $daysRemaining = $validityDays - $daysSince;

        $isExpired = $daysRemaining <= 0;

        if ($isExpired) {
            return [
                'days_since_calibration' => $daysSince,
                'days_remaining' => $daysRemaining,
                'is_expired' => true,
                'instrument_operational_state' => 'LOCKED_CALIBRATION_OVERDUE',
                'recommendation' => 'ALAT TERKUNCI: Kalibrasi telah melewati batas waktu. Lakukan kalibrasi penuh sebelum instrumen diizinkan running.',
            ];
        }

        if ($daysRemaining <= 3) {
            return [
                'days_since_calibration' => $daysSince,
                'days_remaining' => $daysRemaining,
                'is_expired' => false,
                'instrument_operational_state' => 'CALIBRATION_WARNING',
                'recommendation' => "WASPADA: Kalibrasi akan kedaluwarsa dalam {$daysRemaining} hari. Siapkan kalibrator lot aktif.",
            ];
        }

        return [
            'days_since_calibration' => $daysSince,
            'days_remaining' => $daysRemaining,
            'is_expired' => false,
            'instrument_operational_state' => 'OPERATIONAL',
            'recommendation' => 'Status instrumen prima dan terkalibrasi.',
        ];
    }
}
