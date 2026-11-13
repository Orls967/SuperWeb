<?php

declare(strict_types=1);

namespace Modules\ControlTower\Domain\ValueObjects;

final readonly class DisruptionBlastRadius
{
    /**
     * @param  list<string>  $impactedOrders
     */
    public function __construct(
        public string $disruptedNode,
        public array $impactedOrders,
        public int $revenueAtRiskIdr,
        public string $severity,
    ) {}

    public function impactedCount(): int
    {
        return count($this->impactedOrders);
    }
}
