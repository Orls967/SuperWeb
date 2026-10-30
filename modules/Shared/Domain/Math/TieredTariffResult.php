<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Math;

/**
 * Result of tiered progressive tariff calculation.
 */
final readonly class TieredTariffResult
{
    /**
     * @param  int  $totalUnits  Total units consumed
     * @param  int  $totalCost  Total calculated cost in IDR integer rupiah
     * @param  int  $touMultiplierBps  Time-Of-Use multiplier in bps (10000 = 1.0x)
     * @param  list<array{minUnits: int, maxUnits: ?int, units: int, ratePerUnit: int, baseCost: int, effectiveCost: int}>  $tierBreakdowns
     */
    public function __construct(
        public int $totalUnits,
        public int $totalCost,
        public int $touMultiplierBps,
        public array $tierBreakdowns,
    ) {}
}
