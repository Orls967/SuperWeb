<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Actions;

use InvalidArgumentException;
use Modules\Mining\Domain\Models\MiningEquipment;
use Modules\Mining\Domain\Models\MiningEquipmentTelemetry;

final class RecordTireTemperaturePressureAction
{
    /**
     * Memantau temperatur dan tekanan ban armada truk angkut (TKPH - Ton Kilometer Per Hour).
     *
     * @param array{
     *     equipment_id: string,
     *     tire_position: string, // FL, FR, R1L, R1R, R2L, R2R
     *     pressure_psi: int,
     *     temperature_c: int,
     *     payload_ton: float,
     *     shift_speed_kmh: float,
     * } $payload
     * @return array{status: string, tkph: float, is_warning: bool, message: string}
     */
    public function execute(array $payload): array
    {
        $equipment = MiningEquipment::findOrFail($payload['equipment_id']);

        if ($equipment->type !== 'HAUL_TRUCK') {
            throw new InvalidArgumentException("Monitoring ban hanya berlaku untuk armada HAUL_TRUCK, got {$equipment->type}");
        }

        // Hitung TKPH = Rata-rata beban ban (ton) * Kecepatan rata-rata (km/h)
        // Beban ban rata-rata = (Tare / 6) + (Payload / 6) untuk 6 roda
        $tareTon = ($equipment->tare_weight_kg ?? 70000) / 1000.0;
        $emptyTireLoad = $tareTon / 6.0;
        $loadedTireLoad = ($tareTon + $payload['payload_ton']) / 6.0;
        $meanTireLoad = ($emptyTireLoad + $loadedTireLoad) / 2.0;

        $tkph = round($meanTireLoad * $payload['shift_speed_kmh'], 2);

        // Batas aman ban tambang standar (biasanya TKPH rating 350-450)
        $tkphRating = 400.0;
        $maxTempC = 95;
        $minPressurePsi = 90;
        $maxPressurePsi = 130;

        $isWarning = false;
        $reasons = [];

        if ($payload['temperature_c'] >= $maxTempC) {
            $isWarning = true;
            $reasons[] = "Suhu ban {$payload['tire_position']} ({$payload['temperature_c']}°C) melebihi batas kritis {$maxTempC}°C";
        }

        if ($payload['pressure_psi'] < $minPressurePsi || $payload['pressure_psi'] > $maxPressurePsi) {
            $isWarning = true;
            $reasons[] = "Tekanan ban {$payload['tire_position']} ({$payload['pressure_psi']} psi) di luar rentang aman [{$minPressurePsi}-{$maxPressurePsi}]";
        }

        if ($tkph > $tkphRating) {
            $isWarning = true;
            $reasons[] = "Nilai operasi TKPH ({$tkph}) melebihi batas rating ban ({$tkphRating})";
        }

        return [
            'status' => $isWarning ? 'ALERT_HOT_TIRE' : 'NORMAL',
            'tkph' => $tkph,
            'is_warning' => $isWarning,
            'message' => $isWarning ? implode('; ', $reasons) : 'Parameter ban dalam batas toleransi aman',
        ];
    }
}
