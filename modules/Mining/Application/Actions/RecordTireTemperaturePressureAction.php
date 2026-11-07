<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Actions;

use InvalidArgumentException;
use Modules\Mining\Domain\Models\MiningEquipment;

/**
 * Evaluasi keselamatan ban armada truk angkut tambang menggunakan indeks TKPH
 * (Ton-Kilometer Per Hour) dalam satuan integer skala bps/kg (D93-3).
 */
final class RecordTireTemperaturePressureAction
{
    private const DEFAULT_TARE_WEIGHT_KG = 72000;

    private const TIRE_COUNT = 6;

    private const TKPH_RATING_SCALED = 400000; // 400.000 kg-m/h setara 400 ton-km/h

    private const MAX_SAFE_TEMP_CELSIUS = 95;

    private const MIN_SAFE_PRESSURE_PSI = 90;

    private const MAX_SAFE_PRESSURE_PSI = 130;

    /**
     * @param  array{
     *     equipment_id: string,
     *     tire_position: string,
     *     pressure_psi: int,
     *     temperature_c: int,
     *     payload_kg: int,
     *     shift_speed_meters_per_hour: int
     * }  $payload
     * @return array{
     *     equipment_code: string,
     *     tire_position: string,
     *     status: string,
     *     tkph_scaled: int,
     *     tkph_utilization_bps: int,
     *     is_warning: bool,
     *     reasons: list<string>
     * }
     */
    public function execute(array $payload): array
    {
        $equipment = MiningEquipment::findOrFail($payload['equipment_id']);

        if ($equipment->type !== 'HAUL_TRUCK') {
            throw new InvalidArgumentException("Evaluasi TKPH ban hanya berlaku untuk armada HAUL_TRUCK, diterima: {$equipment->type}");
        }

        $tareKg = max(self::DEFAULT_TARE_WEIGHT_KG, (int) $equipment->capacity_kg);
        $payloadKg = max(0, (int) $payload['payload_kg']);
        $speedMph = max(0, (int) $payload['shift_speed_meters_per_hour']);

        // Beban ban rata-rata (kg) = ((Tare / 6) + ((Tare + Payload) / 6)) / 2 = (2 * Tare + Payload) / 12
        $meanTireLoadKg = intdiv((2 * $tareKg) + $payloadKg, 2 * self::TIRE_COUNT);

        // TKPH scaled (kg-km/h = ton-m/h) = meanTireLoadKg * speed_km_h = meanTireLoadKg * speedMph / 1000
        $tkphScaled = intdiv($meanTireLoadKg * $speedMph, 1000);
        $utilizationBps = intdiv($tkphScaled * 10000, self::TKPH_RATING_SCALED);

        $reasons = [];
        if ($payload['temperature_c'] >= self::MAX_SAFE_TEMP_CELSIUS) {
            $reasons[] = "Suhu ban posisi {$payload['tire_position']} ({$payload['temperature_c']} C) melampaui ambang aman ".self::MAX_SAFE_TEMP_CELSIUS.' C';
        }

        if ($payload['pressure_psi'] < self::MIN_SAFE_PRESSURE_PSI || $payload['pressure_psi'] > self::MAX_SAFE_PRESSURE_PSI) {
            $reasons[] = "Tekanan ban posisi {$payload['tire_position']} ({$payload['pressure_psi']} psi) di luar rentang [".self::MIN_SAFE_PRESSURE_PSI.'-'.self::MAX_SAFE_PRESSURE_PSI.'] psi';
        }

        if ($tkphScaled > self::TKPH_RATING_SCALED) {
            $reasons[] = "Indeks beban termal TKPH ({$tkphScaled}) melampaui batas pabrikan (".self::TKPH_RATING_SCALED.')';
        }

        $isWarning = count($reasons) > 0;

        return [
            'equipment_code' => (string) $equipment->equipment_code,
            'tire_position' => $payload['tire_position'],
            'status' => $isWarning ? 'ALERT_HOT_TIRE' : 'NORMAL',
            'tkph_scaled' => $tkphScaled,
            'tkph_utilization_bps' => $utilizationBps,
            'is_warning' => $isWarning,
            'reasons' => $reasons,
        ];
    }
}
