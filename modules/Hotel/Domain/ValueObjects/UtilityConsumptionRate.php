<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\ValueObjects;

use InvalidArgumentException;

final class UtilityConsumptionRate
{
    public function __construct(
        public readonly float $electricityRatePerKwh = 2500.0,
        public readonly float $dedicatedBandwidthRatePerMbps = 150000.0,
        public readonly float $waterRatePerCubicMeter = 12000.0,
        public readonly float $compressedAirRatePerHour = 50000.0
    ) {
        if ($this->electricityRatePerKwh < 0 || $this->dedicatedBandwidthRatePerMbps < 0) {
            throw new InvalidArgumentException('Utility consumption rates cannot be negative');
        }
    }

    public function computeSurcharge(float $kwhUsage, float $mbpsUsage, float $waterM3 = 0.0, float $airHours = 0.0): float
    {
        $electric = max(0.0, $kwhUsage) * $this->electricityRatePerKwh;
        $bandwidth = max(0.0, $mbpsUsage) * $this->dedicatedBandwidthRatePerMbps;
        $water = max(0.0, $waterM3) * $this->waterRatePerCubicMeter;
        $air = max(0.0, $airHours) * $this->compressedAirRatePerHour;

        return round($electric + $bandwidth + $water + $air, 2);
    }
}
