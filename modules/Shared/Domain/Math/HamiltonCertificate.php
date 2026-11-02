<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Math;

use Brick\Math\BigRational;

final readonly class HamiltonCertificate
{
    /**
     * @param  list<array{key: string, weight: int|BigRational, cap?: int}>  $items
     * @param  array<string, BigRational>  $quotas
     * @param  array<string, int>  $allocations
     */
    public function __construct(
        public int $total,
        public int $unallocated,
        public array $items,
        public array $quotas,
        public array $allocations,
    ) {}
}
