<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Math;

/**
 * Audit certificate for dual-traversal WBS progress calculation.
 */
final readonly class WeightedTreeCertificate
{
    /**
     * @param  array<string, int>  $nodeProgressBps
     * @param  array<string, int>  $leafGlobalWeightsBps
     */
    public function __construct(
        public string $rootId,
        public int $bottomUpProgressBps,
        public int $topDownProgressBps,
        public int $discrepancyBps,
        public array $nodeProgressBps,
        public array $leafGlobalWeightsBps,
    ) {}
}
