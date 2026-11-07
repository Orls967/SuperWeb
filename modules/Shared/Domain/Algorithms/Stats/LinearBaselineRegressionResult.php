<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Stats;

final class LinearBaselineRegressionResult
{
    /**
     * @param  array{numerator: int, denominator: int, float_val: float}  $slope
     * @param  array{numerator: int, denominator: int, float_val: float}  $intercept
     * @param  list<float>  $residuals
     */
    public function __construct(
        public readonly array $slope,
        public readonly array $intercept,
        public readonly array $residuals,
        public readonly float $newX,
        public readonly float $predictedY,
        public readonly float $actualY,
        public readonly float $excessY,
        public readonly int $excessBps,
        public readonly int $thresholdBps,
        public readonly bool $isAnomaly,
        public readonly string $certificateHash,
        public readonly array $certificateData
    ) {}
}
