<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Math;

/**
 * Result of WBS tree progress computation.
 */
final readonly class WeightedTreeResult
{
    /**
     * @param  int  $progressBps  Overall root progress in bps (0..10000)
     * @param  array<string, int>  $nodeProgressBps  Progress calculated for every node in the tree
     * @param  WeightedTreeCertificate  $certificate  Dual-traversal audit certificate
     */
    public function __construct(
        public int $progressBps,
        public array $nodeProgressBps,
        public WeightedTreeCertificate $certificate,
    ) {}
}
