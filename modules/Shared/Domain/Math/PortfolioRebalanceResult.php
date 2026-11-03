<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Math;

/**
 * Result of portfolio rebalancing.
 */
final readonly class PortfolioRebalanceResult
{
    /**
     * @param  int  $remainingCash  Remaining unspent cash in IDR
     * @param  array<string, int>  $units  Final units held per asset ID
     * @param  list<array{id: string, action: 'BUY'|'SELL', lots: int, units: int, pricePerUnit: int, totalAmount: int}>  $orders  Executed rebalancing orders
     * @param  int  $initialTotalValue  Initial portfolio valuation in IDR
     * @param  int  $finalTotalValue  Final portfolio valuation in IDR
     * @param  array<string, int>  $finalWeightsBps  Final weight per asset ID in bps
     */
    public function __construct(
        public int $remainingCash,
        public array $units,
        public array $orders,
        public int $initialTotalValue,
        public int $finalTotalValue,
        public array $finalWeightsBps,
    ) {}
}
