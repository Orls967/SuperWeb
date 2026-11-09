<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\Actions\Operations;

use Modules\Hospital\Application\DTOs\ColdChainLogDTO;
use Modules\Hospital\Domain\Services\ColdChainMonitoringService;

final class RecordColdChainReadingAction
{
    public function __construct(
        private readonly ColdChainMonitoringService $coldChainService
    ) {}

    /**
     * @param  array{sensor_device_code: string, fridge_unit_code: string, storage_zone: string, temperature_c: float, humidity_pct?: ?float, recorded_at?: ?string}  $data
     */
    public function execute(array $data): ColdChainLogDTO
    {
        return $this->coldChainService->recordAndEvaluateTelemetry(
            sensorDeviceCode: $data['sensor_device_code'],
            fridgeUnitCode: $data['fridge_unit_code'],
            storageZone: $data['storage_zone'],
            temperatureC: (float) $data['temperature_c'],
            humidityPct: isset($data['humidity_pct']) ? (float) $data['humidity_pct'] : null,
            recordedAt: $data['recorded_at'] ?? null
        );
    }
}
