<?php

declare(strict_types=1);

namespace Modules\Egy\Application\DTOs;

/**
 * Data Telemetri Pengaturan Frekuensi Primer & Sekunder (P-f Droop & AGC).
 */
final class DroopRegulationTelemetryDTO
{
    /**
     * @param  array<string, int>  $generatorPowerContributionsKw
     */
    public function __construct(
        public readonly string $microgridCode,
        public readonly int $measuredFrequencyMhz,
        public readonly int $frequencyDeviationMhz,
        public readonly int $targetActivePowerOutputKw,
        public readonly int $rocofMhzPerSecond,
        public readonly bool $isLossOfMainsTripped,
        public readonly int $measuredVoltageVolt,
        public readonly int $reactivePowerContributionKvar,
        public readonly array $generatorPowerContributionsKw,
        public readonly string $telemetryHash,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'microgrid_code' => $this->microgridCode,
            'measured_frequency_mhz' => $this->measuredFrequencyMhz,
            'frequency_deviation_mhz' => $this->frequencyDeviationMhz,
            'target_active_power_kw' => $this->targetActivePowerOutputKw,
            'rocof_mhz_per_sec' => $this->rocofMhzPerSecond,
            'loss_of_mains_tripped' => $this->isLossOfMainsTripped,
            'measured_voltage_volt' => $this->measuredVoltageVolt,
            'reactive_power_kvar' => $this->reactivePowerContributionKvar,
            'generator_contributions' => $this->generatorPowerContributionsKw,
            'telemetry_hash' => $this->telemetryHash,
        ];
    }
}
