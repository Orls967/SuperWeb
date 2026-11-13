<?php

declare(strict_types=1);

namespace Modules\ControlTower\Domain\ValueObjects;

final readonly class ForecastAccuracyMetrics
{
    public function __construct(
        public int $mapeBps,
        public int $madUnits,
        public int $trackingSignalBps,
    ) {}

    public function isWithinBiasThreshold(): bool
    {
        return abs($this->trackingSignalBps) <= 40_000;
    }
}
