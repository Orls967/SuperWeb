<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\ValueObjects;

final class BundleMarginProfile
{
    public function __construct(
        public readonly int $totalRetailValueIdr,
        public readonly int $discountBps,
        public readonly int $bundlePriceIdr,
        public readonly int $floorPriceIdr,
        public readonly bool $isFloorTriggered
    ) {
        if ($this->totalRetailValueIdr <= 0) {
            throw new \InvalidArgumentException('totalRetailValueIdr harus positif.');
        }
        if ($this->bundlePriceIdr < $this->floorPriceIdr) {
            throw new \DomainException('bundlePriceIdr tidak boleh lebih rendah dari floorPriceIdr.');
        }
    }

    public function getCustomerSavingsIdr(): int
    {
        return max(0, $this->totalRetailValueIdr - $this->bundlePriceIdr);
    }

    public function getSavingsPercentage(): float
    {
        return round(($this->getCustomerSavingsIdr() / $this->totalRetailValueIdr) * 100, 2);
    }
}
