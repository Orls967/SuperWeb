<?php

declare(strict_types=1);

namespace Modules\ControlTower\Domain\ValueObjects;

final readonly class SupplyChainKpiBundle
{
    public function __construct(
        public int $otifBps,
        public int $cashToCashDays,
        public int $inventoryDosDays,
        public int $fulfillmentLeadTimeHours,
        public int $logisticsCostRatioBps,
    ) {}

    public function meetsWorldClassOtif(): bool
    {
        return $this->otifBps >= 9500;
    }
}
