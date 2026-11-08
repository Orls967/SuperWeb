<?php

declare(strict_types=1);

namespace Modules\Marine\Application\DTOs;

use DomainException;

/**
 * DTO telemetri sensor rantai dingin kontainer reefer (Fase 171).
 */
class ReeferColdChainTelemetryDTO
{
    public function __construct(
        public readonly int $exportShipmentId,
        public readonly string $recordedAt,
        public readonly float $ambientTemperatureCelsius,
        public readonly float $supplyAirTemperatureCelsius,
        public readonly float $returnAirTemperatureCelsius,
        public readonly float $cargoProbeTemperatureCelsius,
        public readonly float $humidityPercentage,
        public readonly float $latitude,
        public readonly float $longitude,
        public readonly bool $compressorRunning = true,
        public readonly bool $defrostCycleActive = false
    ) {
        $this->validate();
    }

    protected function validate(): void
    {
        if ($this->exportShipmentId <= 0) {
            throw new DomainException('Export Shipment ID harus valid.');
        }

        if ($this->humidityPercentage < 0.0 || $this->humidityPercentage > 100.0) {
            throw new DomainException('Kelembaban relatif harus di antara 0% dan 100%.');
        }
    }

    public function isTemperatureBreached(): bool
    {
        // Ambang batas dingin kritis udang beku: -15.0°C (target -22.0°C)
        return $this->cargoProbeTemperatureCelsius > -15.0;
    }

    public function getThermalDeviationFromSetpoint(float $setpointCelsius = -22.0): float
    {
        return round($this->cargoProbeTemperatureCelsius - $setpointCelsius, 2);
    }
}
