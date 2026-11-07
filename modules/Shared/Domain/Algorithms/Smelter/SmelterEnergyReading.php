<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Smelter;

final class SmelterEnergyReading
{
    public function __construct(
        public readonly float $meteredElectricalPowerMwh,
        public readonly float $gridEmissionFactorTco2PerMwh = 0.7900,
        public readonly float $coalEmissionFactorTco2PerTon = 2.4200,
        public readonly float $dieselFuelLiters = 0.0,
        public readonly float $dieselEmissionFactorTco2PerKl = 2.6800,
        public readonly float $furnaceSurfaceHeatLossMwh = 1.250,
        public readonly float $electrodeConsumptionKgPerTonMetal = 18.5,
    ) {
        if ($this->meteredElectricalPowerMwh < 0.0) {
            throw new \InvalidArgumentException('Metered electrical power MWh cannot be negative.');
        }
        if ($this->gridEmissionFactorTco2PerMwh < 0.0) {
            throw new \InvalidArgumentException('Grid emission factor cannot be negative.');
        }
        if ($this->coalEmissionFactorTco2PerTon < 0.0) {
            throw new \InvalidArgumentException('Coal emission factor cannot be negative.');
        }
        if ($this->dieselFuelLiters < 0.0) {
            throw new \InvalidArgumentException('Diesel fuel liters cannot be negative.');
        }
    }
}
