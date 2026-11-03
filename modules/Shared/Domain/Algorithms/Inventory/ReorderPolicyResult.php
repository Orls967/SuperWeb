<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Inventory;

final readonly class ReorderPolicyResult
{
    public function __construct(
        public int $safetyStock,
        public int $reorderPoint,
        public int $orderUpToLevel,
        public int $netPosition,
        public int $rawOrderQty,
        public int $orderQty,
        public int $totalCostIdr,
        public bool $autoPoAllowed,
        public bool $requiresApproval,
        public string $certificate,
    ) {}
}
