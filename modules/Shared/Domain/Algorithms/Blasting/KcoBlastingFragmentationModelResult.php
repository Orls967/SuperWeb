<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Blasting;

final readonly class KcoBlastingFragmentationModelResult
{
    public function __construct(
        public float $meanFragmentSizeX50Mm,
        public float $uniformityIndexN,
        public float $characteristicSizeXcPct,
        public float $oversizePercent,          // Fraksi butir > 1000 mm (oversize)
        public float $finesPercent,             // Fraksi butir < 25 mm (fines)
        public float $powderFactorKgPerM3,
        public bool $isSecondaryBreakingRequired,
        public float $recommendedBurdenAdjustmentM,
        public float $recommendedSpacingAdjustmentM,
        public float $recommendedPowderFactorAdjustmentKgPerM3,
        public string $hmacSignature
    ) {}
}
