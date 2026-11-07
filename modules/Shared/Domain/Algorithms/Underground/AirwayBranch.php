<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Underground;

final readonly class AirwayBranch
{
    public function __construct(
        public string $branchId,
        public string $fromNode,
        public string $toNode,
        public float $resistanceR,
        public float $initialFlowQ,
        public float $fanPressurePa = 0.0,
        public bool $isFanBranch = false,
        public float $lengthM = 100.0,
        public float $areaM2 = 16.0
    ) {}

    public function frictionHeadLoss(float $currentFlowQ): float
    {
        $sign = $currentFlowQ >= 0.0 ? 1.0 : -1.0;

        return $sign * $this->resistanceR * ($currentFlowQ ** 2);
    }
}
