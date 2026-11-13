<?php

declare(strict_types=1);

namespace Modules\ControlTower\Domain\ValueObjects;

final readonly class EchelonPosition
{
    public function __construct(
        public string $itemCode,
        public string $echelonNode,
        public int $onHandQty,
        public int $inTransitQty,
        public int $reservedQty,
        public int $safetyStockQty,
    ) {}

    public function netAvailableQty(): int
    {
        return max(0, $this->onHandQty + $this->inTransitQty - $this->reservedQty);
    }

    public function isBelowSafetyStock(): bool
    {
        return $this->netAvailableQty() < $this->safetyStockQty;
    }
}
