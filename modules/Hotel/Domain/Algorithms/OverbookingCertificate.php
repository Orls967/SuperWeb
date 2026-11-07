<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Algorithms;

final readonly class OverbookingCertificate
{
    /**
     * @param list<int> $valuesByK
     */
    public function __construct(
        public int $capacity,
        public int $optimalK,
        public int $expectedNetValueIdr,
        public float $expectedWalkRooms,
        public int $policyCeilingK,
        public array $valuesByK,
        public int $timestamp
    ) {}
}
