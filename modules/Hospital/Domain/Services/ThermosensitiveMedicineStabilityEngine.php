<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Services;

/**
 * Thermosensitive Medicine Stability & Mean Kinetic Temperature (MKT) Engine.
 * Menjamin integritas biofarmasi sediaan peka suhu (vaksin, insulin, albumin, antibodi monoklonal):
 * 1. Kalkulasi Mean Kinetic Temperature (MKT) berbasis persamaan Arrhenius:
 *    MKT = (dH / R) / -ln( sum(exp(-dH / R*T_i)) / n )
 *    dengan energi aktivasi standar dH = 83.144 kJ/mol dan konstanta gas R = 8.3144 J/(mol*K).
 * 2. Evaluasi akumulasi durasi pelanggaran suhu (thermal excursion / breach duration).
 * 3. Keputusan karantina bila paparan suhu di atas 8°C berlangsung > 5 menit.
 */
class ThermosensitiveMedicineStabilityEngine
{
    private const DELTA_H = 83144.0; // J/mol (Standar USP untuk degradasi biologis)

    private const GAS_CONSTANT_R = 8.3144; // J/(mol*K)

    private const KELVIN_OFFSET = 273.15;

    private const MAX_CUMULATIVE_BREACH_MINUTES = 5;

    /**
     * Menghitung Mean Kinetic Temperature (MKT) dalam derajat Celsius dari deret pembacaan suhu.
     *
     * @param  array<int, float>  $temperatureReadingsCelsius
     * @return float Suhu MKT dalam derajat Celsius
     */
    public function calculateMktCelsius(array $temperatureReadingsCelsius): float
    {
        $n = count($temperatureReadingsCelsius);
        if ($n === 0) {
            return 4.0; // Default suhu referensi pendingin
        }

        $sumExp = 0.0;
        $dhOverR = self::DELTA_H / self::GAS_CONSTANT_R;

        foreach ($temperatureReadingsCelsius as $tempC) {
            $tempK = $tempC + self::KELVIN_OFFSET;
            if ($tempK <= 0) {
                $tempK = 1.0;
            }
            $sumExp += exp(-$dhOverR / $tempK);
        }

        $meanExp = $sumExp / $n;
        if ($meanExp <= 0) {
            return 4.0;
        }

        $mktKelvin = $dhOverR / (-log($meanExp));

        return round($mktKelvin - self::KELVIN_OFFSET, 2);
    }

    /**
     * Mengevaluasi apakah serangkaian log telemetri memicu karantina dingin.
     *
     * @param  array<int, array{temp_c: float, timestamp: string}>  $telemetryLogs
     * @return array{
     *     is_quarantined: bool,
     *     mkt_celsius: float,
     *     cumulative_breach_minutes: int,
     *     violation_reason: string|null
     * }
     */
    public function evaluateColdChainExposure(
        array $telemetryLogs,
        float $allowedMinC = 2.0,
        float $allowedMaxC = 8.0
    ): array {
        if (empty($telemetryLogs)) {
            return [
                'is_quarantined' => false,
                'mkt_celsius' => 4.0,
                'cumulative_breach_minutes' => 0,
                'violation_reason' => null,
            ];
        }

        $temps = array_column($telemetryLogs, 'temp_c');
        $mkt = $this->calculateMktCelsius($temps);

        $breachMinutes = 0;
        foreach ($temps as $temp) {
            if ($temp < $allowedMinC || $temp > $allowedMaxC) {
                $breachMinutes++;
            }
        }

        $isQuarantined = $breachMinutes >= self::MAX_CUMULATIVE_BREACH_MINUTES || $mkt > ($allowedMaxC + 2.0);
        $reason = null;

        if ($isQuarantined) {
            $reason = sprintf(
                'Karantina Rantai Dingin: Paparan suhu di luar ambang [%.1f°C - %.1f°C] selama %d menit dengan MKT %.2f°C.',
                $allowedMinC,
                $allowedMaxC,
                $breachMinutes,
                $mkt
            );
        }

        return [
            'is_quarantined' => $isQuarantined,
            'mkt_celsius' => $mkt,
            'cumulative_breach_minutes' => $breachMinutes,
            'violation_reason' => $reason,
        ];
    }
}
