<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Math;

use InvalidArgumentException;

/**
 * Value object representing an asset holding in portfolio rebalancing.
 */
final readonly class PortfolioAsset
{
    public function __construct(
        public string $id,
        public int $units,
        public int $nav,
        public int $lot,
        public int $targetBps,
        public int $bandBps,
    ) {
        if ($id === '') {
            throw new InvalidArgumentException('Asset ID cannot be empty');
        }
        if ($units < 0) {
            throw new InvalidArgumentException("Units cannot be negative for asset {$id}");
        }
        if ($nav <= 0) {
            throw new InvalidArgumentException("NAV must be strictly positive for asset {$id}");
        }
        if ($lot <= 0) {
            throw new InvalidArgumentException("Lot must be strictly positive for asset {$id}");
        }
        if ($targetBps < 0 || $targetBps > 10_000) {
            throw new InvalidArgumentException("Target Bps must be between 0 and 10000 for asset {$id}");
        }
        if ($bandBps < 0) {
            throw new InvalidArgumentException("Band Bps cannot be negative for asset {$id}");
        }
    }
}
