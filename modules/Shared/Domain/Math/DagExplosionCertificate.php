<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Math;

use Brick\Math\BigRational;

final readonly class DagExplosionCertificate
{
    /**
     * @param  array<string, BigRational>  $kahnTotals
     * @param  array<string, BigRational>  $dfsTotals
     * @param  array<string, int>  $ceiledTotals
     */
    public function __construct(
        public array $kahnTotals,
        public array $dfsTotals,
        public array $ceiledTotals,
    ) {}
}
