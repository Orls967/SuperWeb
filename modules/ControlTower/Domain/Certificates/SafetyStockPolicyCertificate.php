<?php

declare(strict_types=1);

namespace Modules\ControlTower\Domain\Certificates;

final readonly class SafetyStockPolicyCertificate
{
    public function __construct(
        public string $itemCode,
        public string $abcClass,
        public string $xyzClass,
        public int $serviceLevelBps,
        public int $safetyStockQty,
        public int $reorderPointQty,
        public string $digest,
    ) {}
}
