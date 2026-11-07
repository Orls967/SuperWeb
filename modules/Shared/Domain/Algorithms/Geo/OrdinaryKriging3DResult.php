<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Geo;

final readonly class OrdinaryKriging3DResult
{
    /**
     * @param array{x: int, y: int, z: int} $targetPoint
     * @param int $estimatedGradePpm
     * @param int $krigingVarianceBps
     * @param array<int, array{sample_index: int, weight_bps: int}> $weightsBreakdown
     * @param int $lagrangeMultiplierBps
     * @param string $certificateHash
     */
    public function __construct(
        public array $targetPoint,
        public int $estimatedGradePpm,
        public int $krigingVarianceBps,
        public array $weightsBreakdown,
        public int $lagrangeMultiplierBps,
        public string $certificateHash,
    ) {
    }
}

