<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Services;

use InvalidArgumentException;

/**
 * MedicalGasPipelineSystemMonitorService
 *
 * Mengawasi integritas jaringan pipa gas medis sentral (Permenkes No. 4/2016 & HTM 02-01):
 *   1. Oksigen Medis (O2, tekanan kerja standar 4.0 - 5.0 bar).
 *   2. Medical Compressed Air (Tekanan kerja 4.0 bar untuk ventilator).
 *   3. Surgical Air (Tekanan kerja 7.0 bar untuk instrumen bedah ortopedi).
 *   4. Vacuum Medis (Hisap lendir/cairan luka, tekanan negatif -0.6 s/d -0.8 bar).
 *   5. Nitrous Oxide (N2O, anestesi inhalasi).
 * Menghitung konsumsi gas bangsal dan mendeteksi alarm kebocoran pipa.
 */
class MedicalGasPipelineSystemMonitorService
{
    public const GAS_TYPE_OXYGEN = 'MEDICAL_OXYGEN_O2';
    public const GAS_TYPE_MEDICAL_AIR_4BAR = 'MEDICAL_AIR_4_BAR';
    public const GAS_TYPE_SURGICAL_AIR_7BAR = 'SURGICAL_AIR_7_BAR';
    public const GAS_TYPE_VACUUM = 'MEDICAL_VACUUM_NEGATIVE_PRESSURE';
    public const GAS_TYPE_NITROUS_OXIDE = 'NITROUS_OXIDE_N2O';

    /**
     * Rentang tekanan normal per tipe gas [min_bar, max_bar].
     *
     * @var array<string, array{min: float, max: float}>
     */
    private const NORMAL_PRESSURE_RANGES = [
        self::GAS_TYPE_OXYGEN => ['min' => 4.0, 'max' => 5.2],
        self::GAS_TYPE_MEDICAL_AIR_4BAR => ['min' => 3.8, 'max' => 4.8],
        self::GAS_TYPE_SURGICAL_AIR_7BAR => ['min' => 6.5, 'max' => 7.8],
        self::GAS_TYPE_VACUUM => ['min' => -0.85, 'max' => -0.55], // Tekanan negatif
        self::GAS_TYPE_NITROUS_OXIDE => ['min' => 4.0, 'max' => 5.0],
    ];

    /**
     * Evaluasi pembacaan sensor manifold / zona pipa gas medis.
     *
     * @param string $zoneCode Kode zona (misal: 'ZONE-ICU-03', 'ZONE-OR-SUITE-A')
     * @param string $gasType Jenis gas
     * @param float $measuredPressureBar Tekanan terukur di manifold (bar)
     * @param float $flowRateLitersPerMin Laju aliran gas aktual (L/menit)
     * @param int $activeVentilatorsCount Jumlah ventilator/outlet aktif yang terhubung
     * @return array{
     *     zone_code: string,
     *     gas_type: string,
     *     status: string,
     *     is_alarm_triggered: bool,
     *     alarm_severity: string,
     *     measured_pressure: float,
     *     leak_suspicion: bool,
     *     remediation_guideline: string
     * }
     */
    public function evaluatePipelineTelemetry(
        string $zoneCode,
        string $gasType,
        float $measuredPressureBar,
        float $flowRateLitersPerMin,
        int $activeVentilatorsCount
    ): array {
        if (!isset(self::NORMAL_PRESSURE_RANGES[$gasType])) {
            throw new InvalidArgumentException("Tipe gas medis tidak dikenali: {$gasType}");
        }

        $range = self::NORMAL_PRESSURE_RANGES[$gasType];
        $isAlarm = false;
        $alarmSeverity = 'NONE';
        $status = 'NORMAL_OPERATING_PRESSURE';
        $leakSuspicion = false;
        $remediation = 'Kondisi distribusi gas sentral stabil.';

        // Evaluasi tekanan
        if ($measuredPressureBar < $range['min']) {
            $isAlarm = true;
            $alarmSeverity = 'CRITICAL_LOW_PRESSURE';
            $status = 'LOW_PRESSURE_BREACH';
            $remediation = sprintf('Tekanan gas drop di bawah batas aman (%.2f < %.2f bar). Aktifkan manifold darurat cadangan dan periksa stasiun penurun tekanan!', $measuredPressureBar, $range['min']);
        } elseif ($measuredPressureBar > $range['max']) {
            $isAlarm = true;
            $alarmSeverity = 'HIGH_PRESSURE_WARNING';
            $status = 'HIGH_PRESSURE_OVERFLOW';
            $remediation = sprintf('Tekanan gas melampaui batas atas (%.2f > %.2f bar). Kalibrasi regulator tekanan manifold sekunder.', $measuredPressureBar, $range['max']);
        }

        // Deteksi dugaan kebocoran bila aliran tinggi padahal ventilator sedikit
        $expectedMaxFlow = max(10.0, $activeVentilatorsCount * 30.0); // Rata-rata 30 L/min per ventilator
        if ($flowRateLitersPerMin > ($expectedMaxFlow * 2.5)) {
            $leakSuspicion = true;
            if (!$isAlarm) {
                $isAlarm = true;
                $alarmSeverity = 'POSSIBLE_PIPE_RUPTURE';
            }
            $remediation .= ' Terdeteksi konsumsi gas abnormal melebihi beban klinis. Indikasi kuat kebocoran sambungan pipa zona!';
        }

        return [
            'zone_code' => $zoneCode,
            'gas_type' => $gasType,
            'status' => $status,
            'is_alarm_triggered' => $isAlarm,
            'alarm_severity' => $alarmSeverity,
            'measured_pressure' => $measuredPressureBar,
            'leak_suspicion' => $leakSuspicion,
            'remediation_guideline' => trim($remediation),
        ];
    }
}
