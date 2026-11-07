<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Algorithms;

final class RoyaltyTierResolverResult
{
    public readonly int $rateBps;

    /**
     * @param  array<string, mixed>  $certificate
     */
    public function __construct(
        public readonly string $commodity,
        public readonly string $productType,
        public readonly string $miningMethod,
        public readonly int $calorieKcal,
        public readonly int $hbaOrHmaCents,
        public readonly int $volumeTons,
        public readonly int $benchmarkPriceCents,
        public readonly int $exchangeRateIdr,
        public readonly int $royaltyRateBps,
        public readonly int $royaltyUsdCents,
        public readonly int $royaltyIdr,
        public readonly array $certificate
    ) {
        $this->rateBps = $royaltyRateBps;
    }
}
