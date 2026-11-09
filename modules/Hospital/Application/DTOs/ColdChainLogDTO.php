<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\DTOs;

final class ColdChainLogDTO
{
    public function __construct(
        public readonly string $sensorDeviceCode,
        public readonly string $fridgeUnitCode,
        public readonly string $storageZone,
        public readonly float $temperatureC,
        public readonly ?float $humidityPct,
        public readonly bool $isExcursion,
        public readonly string $severity,
        public readonly string $recordedAtIso,
        public readonly bool $requiresImmediateQuarantine = false,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'sensor_device_code' => $this->sensorDeviceCode,
            'fridge_unit_code' => $this->fridgeUnitCode,
            'storage_zone' => $this->storageZone,
            'temperature_c' => $this->temperatureC,
            'humidity_pct' => $this->humidityPct,
            'is_excursion' => $this->isExcursion,
            'severity' => $this->severity,
            'recorded_at' => $this->recordedAtIso,
            'requires_immediate_quarantine' => $this->requiresImmediateQuarantine,
        ];
    }
}
