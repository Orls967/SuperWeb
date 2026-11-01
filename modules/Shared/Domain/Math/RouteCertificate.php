<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Math;

final readonly class RouteCertificate
{
    /**
     * @param  list<int>  $tour
     * @param  array<int, array<int, int>>  $dist
     */
    public function __construct(
        public int $n,
        public array $tour,
        public int $cost,
        public int $lowerBound,
        public int $gapBps,
        public bool $isExact,
        public array $dist,
    ) {}
}
