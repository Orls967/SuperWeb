<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Services;

use InvalidArgumentException;

/**
 * HospitalRadioprotectionDosimetryService
 *
 * Mengelola keselamatan radiasi dan dosimetri pekerja radiasi RS (BAPETEN No. 4/2013):
 *   1. Nilai Batas Dosis (NBD) seluruh tubuh pekerja radiasi: 20 mSv per tahun (rata-rata 5 tahun) atau 50 mSv dalam 1 tahun tunggal.
 *   2. NBD lensa mata: 20 mSv per tahun.
 *   3. NBD ekstremitas (tangan/kaki): 500 mSv per tahun.
 *   4. Ambang investigasi internal RS: 1.5 mSv per bulan atau 4.5 mSv per kuartal.
 * Menghitung dosis kumulatif badge TLD dan merekomendasikan mutasi/rotasi kerja jika terdeteksi overdosis.
 */
class HospitalRadioprotectionDosimetryService
{
    public const ANNUAL_WHOLE_BODY_NBD_MSV = 20.0;
    public const SINGLE_YEAR_MAX_NBD_MSV = 50.0;
    public const QUARTERLY_INVESTIGATION_THRESHOLD_MSV = 4.5;
    public const MONTHLY_INVESTIGATION_THRESHOLD_MSV = 1.5;

    public const STATUS_NORMAL_SAFE = 'DOSIMETRY_NORMAL_SAFE';
    public const STATUS_ELEVATED_INVESTIGATION = 'ELEVATED_REQUIRES_INVESTIGATION';
    public const STATUS_OVEREXPOSURE_ROTATION = 'OVEREXPOSURE_MANDATORY_ROTATION';

    /**
     * Evaluasi paparan dosis radiasi pekerja instalasi radiologi/kedokteran nuklir.
     *
     * @param string $workerId NIP atau identitas staf radiasi
     * @param string $workZone Unit kerja ('DIAGNOSTIC_XRAY', 'CT_SCAN', 'CATH_LAB_FLUOROSCOPY', 'NUCLEAR_MEDICINE', 'RADIOTHERAPY_LINAC')
     * @param float $currentQuarterReadingMsv Dosis terukur TLD kuartal ini (mSv)
     * @param float $yearToDateCumulativeMsv Dosis kumulatif tahun berjalan sebelum kuartal ini (mSv)
     * @param bool $isPregnantStaff Apakah pekerja dalam kondisi hamil (NBD janin 1 mSv sepanjang masa gestasi)
     * @return array{
     *     worker_id: string,
     *     work_zone: string,
     *     total_cumulative_year_msv: float,
     *     status: string,
     *     is_investigation_required: bool,
     *     is_immediate_rotation_mandated: bool,
     *     remaining_annual_allowance_msv: float,
     *     radiation_safety_officer_action: string
     * }
     */
    public function evaluateWorkerDosimetry(
        string $workerId,
        string $workZone,
        float $currentQuarterReadingMsv,
        float $yearToDateCumulativeMsv,
        bool $isPregnantStaff
    ): array {
        if (empty($workerId)) {
            throw new InvalidArgumentException('Worker ID tidak boleh kosong');
        }
        if ($currentQuarterReadingMsv < 0 || $yearToDateCumulativeMsv < 0) {
            throw new InvalidArgumentException('Nilai dosis tidak boleh bernilai negatif');
        }

        $totalYear = round($yearToDateCumulativeMsv + $currentQuarterReadingMsv, 3);
        $annualLimit = $isPregnantStaff ? 1.0 : self::ANNUAL_WHOLE_BODY_NBD_MSV;
        $remainingAllowance = max(0.0, round($annualLimit - $totalYear, 3));

        $status = self::STATUS_NORMAL_SAFE;
        $investigationRequired = false;
        $rotationMandated = false;
        $action = 'Paparan radiasi terkendali dengan baik di bawah batas ambang regulasi.';

        if ($isPregnantStaff && $totalYear >= 1.0) {
            $status = self::STATUS_OVEREXPOSURE_ROTATION;
            $investigationRequired = true;
            $rotationMandated = true;
            $action = 'DARURAT PROTEKSI JANIN: Batas 1 mSv tercapai. Pekerja hamil WAJIB segera dibebastugaskan dari medan radiasi.';
        } elseif ($totalYear >= self::ANNUAL_WHOLE_BODY_NBD_MSV) {
            $status = self::STATUS_OVEREXPOSURE_ROTATION;
            $investigationRequired = true;
            $rotationMandated = true;
            $action = sprintf(
                'NBD Tahunan BAPETEN (20 mSv) telah terlampaui (Kumulatif: %.2f mSv). Staf wajib dipindahtugaskan ke zona non-radiasi sampai akhir tahun dan diperiksa kesehatan hematologi.',
                $totalYear
            );
        } elseif ($currentQuarterReadingMsv >= self::QUARTERLY_INVESTIGATION_THRESHOLD_MSV || $totalYear >= 15.0) {
            $status = self::STATUS_ELEVATED_INVESTIGATION;
            $investigationRequired = true;
            $action = sprintf(
                'Dosis kuartalan (%.2f mSv) melampaui level investigasi 4.5 mSv. Petugas Proteksi Radiasi (PPR) wajib melakukan audit apron Pb, pelindung tiroid, dan kebocoran tabung sinar-X.',
                $currentQuarterReadingMsv
            );
        }

        return [
            'worker_id' => $workerId,
            'work_zone' => $workZone,
            'total_cumulative_year_msv' => $totalYear,
            'status' => $status,
            'is_investigation_required' => $investigationRequired,
            'is_immediate_rotation_mandated' => $rotationMandated,
            'remaining_annual_allowance_msv' => $remainingAllowance,
            'radiation_safety_officer_action' => $action,
        ];
    }
}
