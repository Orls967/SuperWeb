<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\Algorithms\Bundling;

final readonly class DynamicBundlePriceCertificate
{
    public function __construct(
        public int $totalFloorCostIdr,
        public int $totalBasePriceIdr,
        public int $surgeBps,
        public int $leadTimeDiscountBps,
        public int $finalBundlePriceIdr,
        public string $hmacSignature,
    ) {}

    public function isValid(): bool
    {
        return DynamicBundlePriceVerifier::verify($this);
    }
}
