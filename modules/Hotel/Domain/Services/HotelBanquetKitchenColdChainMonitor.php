<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

class HotelBanquetKitchenColdChainMonitor
{
    /**
     * @var array<string, array{min_temp_celsius: float, max_temp_celsius: float, alert_priority: string}>
     */
    private const STORAGE_SPECS = [
        'MEAT_WALK_IN_CHILLER' => [
            'min_temp_celsius' => 0.0,
            'max_temp_celsius' => 3.0,
            'alert_priority' => 'CRITICAL_HAZARD',
        ],
        'SEAFOOD_ICE_STORAGE' => [
            'min_temp_celsius' => -1.0,
            'max_temp_celsius' => 2.0,
            'alert_priority' => 'HIGH_SPOILAGE_RISK',
        ],
        'DAIRY_AND_CHEESE_CHAMBER' => [
            'min_temp_celsius' => 2.0,
            'max_temp_celsius' => 5.0,
            'alert_priority' => 'MODERATE',
        ],
    ];

    /**
     * Memantau temperatur ruang pendingin cold-chain dapur banquet dan mendeteksi anomali suhu.
     *
     * @return array{storage_chamber: string, recorded_temp_celsius: float, is_temperature_safe: bool, alert_priority: ?string, corrective_action: string}
     */
    public function auditColdChainTemperature(string $storageChamberCode, float $currentTempCelsius): array
    {
        $spec = self::STORAGE_SPECS[$storageChamberCode] ?? [
            'min_temp_celsius' => 0.0,
            'max_temp_celsius' => 4.0,
            'alert_priority' => 'STANDARD',
        ];

        $isSafe = ($currentTempCelsius >= $spec['min_temp_celsius']) && ($currentTempCelsius <= $spec['max_temp_celsius']);

        $action = 'NORMAL_MONITORING';
        $alertPriority = null;

        if (! $isSafe) {
            $alertPriority = $spec['alert_priority'];
            $action = ($currentTempCelsius > $spec['max_temp_celsius'])
                ? 'EMERGENCY_REFRIGERATION_FAILOVER_DISPATCHED'
                : 'CHECK_THERMOSTAT_FREEZING_HAZARD';
        }

        return [
            'storage_chamber' => $storageChamberCode,
            'recorded_temp_celsius' => $currentTempCelsius,
            'is_temperature_safe' => $isSafe,
            'alert_priority' => $alertPriority,
            'corrective_action' => $action,
        ];
    }
}
