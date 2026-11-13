<?php

declare(strict_types=1);

namespace Modules\ControlTower\Domain\ValueObjects;

final readonly class PromiseTimeBucket
{
    public function __construct(
        public string $periodLabel,
        public int $beginningInventoryQty,
        public int $scheduledReceiptsQty,
        public int $customerOrdersQty,
    ) {}

    public function netAtpQty(): int
    {
        return max(0, $this->beginningInventoryQty + $this->scheduledReceiptsQty - $this->customerOrdersQty);
    }
}
