<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Algorithms;

final class CoalPriceAdjusterResult
{
    /**
     * @param  array<string, mixed>  $certificate
     */
    public function __construct(
        public readonly int $basePriceCents,
        public readonly int $actualGarKcal,
        public readonly int $referenceGarKcal,
        public readonly int $actualAshPermil,
        public readonly int $baseAshLimitPermil,
        public readonly int $actualSulfurPermil,
        public readonly int $baseSulfurLimitPermil,
        public readonly int $volumeTons,
        public readonly int $garAdjustedPriceCents,
        public readonly int $ashPenaltyCents,
        public readonly int $sulfurPenaltyCents,
        public readonly int $finalPriceCentsPerTon,
        public readonly int $totalInvoiceUsdCents,
        public readonly bool $isRejected,
        public readonly ?string $rejectionReason,
        public readonly array $certificate
    ) {}
}
