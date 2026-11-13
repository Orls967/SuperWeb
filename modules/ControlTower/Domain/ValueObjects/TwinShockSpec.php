<?php

declare(strict_types=1);

namespace Modules\ControlTower\Domain\ValueObjects;

final readonly class TwinShockSpec
{
    public function __construct(
        public string $shockType,
        public int $magnitudeBps,
        public int $durationDays,
        public string $targetNode,
    ) {}

    public function isSevere(): bool
    {
        return $this->magnitudeBps >= 2500 || $this->durationDays >= 14;
    }
}
