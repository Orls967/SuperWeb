<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\ValueObjects;

use InvalidArgumentException;

final class BoothSpatialDimension
{
    public function __construct(
        public readonly float $widthMeters,
        public readonly float $depthMeters,
        public readonly float $heightMeters = 2.5
    ) {
        if ($this->widthMeters <= 0.0 || $this->depthMeters <= 0.0 || $this->heightMeters <= 0.0) {
            throw new InvalidArgumentException('Booth spatial dimensions must be strictly positive');
        }
    }

    public function areaSquareMeters(): float
    {
        return round($this->widthMeters * $this->depthMeters, 2);
    }

    public function cubicVolumeMeters(): float
    {
        return round($this->widthMeters * $this->depthMeters * $this->heightMeters, 2);
    }

    public function isStandardModule3x3(): bool
    {
        return abs($this->widthMeters - 3.0) < 0.01 && abs($this->depthMeters - 3.0) < 0.01;
    }
}
