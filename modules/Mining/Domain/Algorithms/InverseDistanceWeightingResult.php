<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Algorithms;

final readonly class InverseDistanceWeightingResult
{
    /**
     * @param array{x: int, y: int, z: int} $targetPoint
     * @param int $interpolatedGradePpm
     * @param int $sampleCount
     * @param array<int, array{sample_index: int, distance_mm: int, weight_bps: int}> $weightsBreakdown
     * @param string $certificateHash
     */
    public function __construct(
        public array $targetPoint,
        public int $interpolatedGradePpm,
        public int $sampleCount,
        public array $weightsBreakdown,
        public string $certificateHash,
    ) {
    }
}

