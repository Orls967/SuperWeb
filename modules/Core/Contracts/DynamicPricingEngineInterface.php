<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

use Modules\Core\Application\DTOs\Ai\DynamicPricingResult;

interface DynamicPricingEngineInterface
{
    /**
     * Calculate deterministic dynamic price with elasticity, shelf-life decay, occupancy surge,
     * and floor/ceiling guardrail clamp.
     */
    public function calculatePrice(
        string $itemCode,
        int $basePriceIdr,
        int $currentStock,
        ?int $hoursToExpiry = null,
        float $spaceOccupancyRate = 0.50,
        int $recentSalesVelocity = 10,
        int $baselineSalesVelocity = 10,
        int $seed = 42
    ): DynamicPricingResult;
}
