<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Blasting;

final readonly class BlastGeometry
{
    public function __construct(
        public float $burdenM,
        public float $spacingM,
        public float $holeDiameterMm,
        public float $benchHeightM,
        public float $holeDepthM,
        public float $stemmingLengthM,
        public float $drillingAccuracyDeviationM = 0.1
    ) {}

    public function holeVolumeM3(): float
    {
        $radiusM = ($this->holeDiameterMm / 2000.0);

        return M_PI * ($radiusM ** 2) * $this->holeDepthM;
    }

    public function rockVolumePerHoleM3(): float
    {
        return $this->burdenM * $this->spacingM * $this->benchHeightM;
    }

    public function chargeLengthM(): float
    {
        return max(0.5, $this->holeDepthM - $this->stemmingLengthM);
    }
}
