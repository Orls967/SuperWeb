<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Math;

use Brick\Math\BigRational;

final readonly class CumulativeRoundingCertificate
{
    /**
     * @param  list<int>  $schedule
     * @param  list<BigRational>  $exactPortions
     * @param  list<int>|null  $weights
     */
    public function __construct(
        public int $total,
        public int $periods,
        public array $schedule,
        public array $exactPortions,
        public ?array $weights = null,
    ) {}
}
