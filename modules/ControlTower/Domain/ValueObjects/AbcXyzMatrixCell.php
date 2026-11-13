<?php

declare(strict_types=1);

namespace Modules\ControlTower\Domain\ValueObjects;

final readonly class AbcXyzMatrixCell
{
    public function __construct(
        public string $abcClass,
        public string $xyzClass,
        public int $recommendedServiceLevelBps,
        public int $reviewFrequencyDays,
    ) {}

    public function code(): string
    {
        return $this->abcClass.$this->xyzClass;
    }
}
