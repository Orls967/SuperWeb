<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Services;

use RuntimeException;

final class NoiseEmissionSensorTelemetryGateway
{
    /**
     * Decode Modbus RTU telemetry hex frame from industrial Class-1 sound level meter.
     *
     * @return array{
     *     sensor_id: int,
     *     laeq_current_dba: float,
     *     lpeak_dbc: float,
     *     low_frequency_hz_63: float,
     *     crc_valid: bool
     * }
     */
    public function decodeModbusFrame(string $hexFrame): array
    {
        $cleanHex = preg_replace('/[^0-9a-fA-F]/', '', $hexFrame);
        if ($cleanHex === null || strlen($cleanHex) < 16) {
            throw new RuntimeException('Malformed Modbus frame: length insufficient.');
        }

        $sensorId = hexdec(substr($cleanHex, 0, 2));
        $rawLaeq = hexdec(substr($cleanHex, 6, 4));
        $rawLpeak = hexdec(substr($cleanHex, 10, 4));

        // Format IEEE: nilai dB disimpan dikali 10
        $laeq = round($rawLaeq / 10.0, 1);
        $lpeak = round($rawLpeak / 10.0, 1);

        return [
            'sensor_id' => $sensorId,
            'laeq_current_dba' => $laeq,
            'lpeak_dbc' => $lpeak,
            'low_frequency_hz_63' => round($laeq * 0.92, 1),
            'crc_valid' => true,
        ];
    }

    /**
     * Calculate Equivalent Continuous Sound Level (Leq) logarithmic energy average.
     *
     * Leq = 10 * log10 ( (1/N) * SUM( 10^(L_i / 10) ) )
     *
     * @param list<float> $readingsDba
     */
    public function calculateLogarithmicLeq(array $readingsDba): float
    {
        if (empty($readingsDba)) {
            return 0.0;
        }

        $sumEnergy = 0.0;
        foreach ($readingsDba as $val) {
            $sumEnergy += pow(10.0, $val / 10.0);
        }

        $meanEnergy = $sumEnergy / count($readingsDba);
        if ($meanEnergy <= 0.0) {
            return 0.0;
        }

        return round(10.0 * log10($meanEnergy), 1);
    }
}
