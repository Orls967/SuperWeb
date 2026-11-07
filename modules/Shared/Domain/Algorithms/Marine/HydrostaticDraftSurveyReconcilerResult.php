<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Marine;

final readonly class HydrostaticDraftSurveyReconcilerResult
{
    public function __construct(
        public float $initialDraftQuarterMeanM,
        public float $finalDraftQuarterMeanM,
        public float $initialDisplacementTon,
        public float $finalDisplacementTon,
        public float $calculatedNetCargoTon,
        public float $weighbridgeTonnageTon,
        public float $varianceTon,
        public float $variancePercent,
        public bool $isWithinTolerance,
        public string $hmacSignature
    ) {}
}
