<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Math;

use InvalidArgumentException;

/**
 * Value object representing a node in a Work Breakdown Structure (WBS) weighted tree.
 */
final class WeightedTreeNode
{
    /**
     * @param  string  $id  Unique node identifier
     * @param  int  $weightBps  Weight among siblings in bps (0..10000)
     * @param  int|null  $progressBps  Explicit progress for leaf node in bps (0..10000); null for internal nodes
     * @param  list<WeightedTreeNode>  $children  Child nodes
     */
    public function __construct(
        public string $id,
        public int $weightBps = 10_000,
        public ?int $progressBps = null,
        public array $children = [],
    ) {
        if ($id === '') {
            throw new InvalidArgumentException('Node ID cannot be empty');
        }
        if ($weightBps < 0 || $weightBps > 10_000) {
            throw new InvalidArgumentException("Weight bps must be between 0 and 10000 for node {$id}: got {$weightBps}");
        }
        if ($progressBps !== null && ($progressBps < 0 || $progressBps > 10_000)) {
            throw new InvalidArgumentException("Progress bps must be between 0 and 10000 for node {$id}: got {$progressBps}");
        }
    }

    public function isLeaf(): bool
    {
        return empty($this->children);
    }
}
