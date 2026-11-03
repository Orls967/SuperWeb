<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Math;

final readonly class RouteResult
{
    /**
     * @param  list<int>  $tour  Sequence of vertex indices from 0 returning to 0
     * @param  int  $cost  Total distance / cost of the tour
     * @param  int  $lowerBound  Lower bound (exact cost if n <= 12, or 1-tree MST lower bound if n > 12)
     * @param  int  $gapBps  Relative gap above lower bound in basis points
     * @param  bool  $isExact  True if guaranteed globally optimal via Held-Karp
     */
    public function __construct(
        public array $tour,
        public int $cost,
        public int $lowerBound,
        public int $gapBps,
        public bool $isExact,
        public RouteCertificate $certificate,
    ) {}
}
