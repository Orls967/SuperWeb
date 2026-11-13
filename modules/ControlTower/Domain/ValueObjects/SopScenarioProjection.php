<?php

declare(strict_types=1);

namespace Modules\ControlTower\Domain\ValueObjects;

final readonly class SopScenarioProjection
{
    public function __construct(
        public string $scenarioType,
        public int $demandPlanQty,
        public int $supplyPlanQty,
        public int $capacityUtilizationBps,
        public int $grossMarginEstIdr,
        public int $cashImpactEstIdr,
    ) {}

    public function supplyGapQty(): int
    {
        return $this->supplyPlanQty - $this->demandPlanQty;
    }
}
