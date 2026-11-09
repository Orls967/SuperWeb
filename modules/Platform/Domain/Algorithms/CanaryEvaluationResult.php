<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Algorithms;

use Modules\Platform\Domain\Enums\CanaryRecommendation;

final class CanaryEvaluationResult
{
    public function __construct(
        public readonly float $baselineErrorRate,
        public readonly float $canaryErrorRate,
        public readonly float $relativeRisk,
        public readonly float $zScore,
        public readonly CanaryRecommendation $recommendation,
        public readonly string $analysisSummary
    ) {}

    public function shouldRollback(): bool
    {
        return $this->recommendation === CanaryRecommendation::ROLLBACK;
    }
}
