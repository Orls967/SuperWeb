<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\Algorithms;

/**
 * Sertifikat komputasi harga dinamis tiket countdown tier.
 */
final readonly class TierCountdownPriceCertificate
{
    public function __construct(
        public int $basePriceIdr,
        public int $demandBps,
        public int $rainProbabilityBps,
        public int $floorPriceIdr,
        public int $ceilingPriceIdr,
        public int $calculatedPriceIdr,
        public int $finalPriceIdr,
        public string $hmacSignature,
    ) {}
}

