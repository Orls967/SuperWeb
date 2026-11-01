<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Lp;

use Brick\Math\BigRational;

/**
 * DTO hasil komputasi Linear Programming (Simplex Rasional Eksak) beserta sertifikat dual (PR2).
 */
final readonly class LpResult
{
    /**
     * @param  LpStatus  $status  Status penyelesaian (OPTIMAL, INFEASIBLE, UNBOUNDED)
     * @param  list<BigRational>  $x  Solusi primal x* (kosong bila tidak layak)
     * @param  BigRational|null  $objectiveValue  Nilai c*x (null bila tidak optimal)
     * @param  list<BigRational>  $y  Sertifikat dual y* atau sertifikat Farkas bila tidak layak
     */
    public function __construct(
        public LpStatus $status,
        public array $x,
        public ?BigRational $objectiveValue,
        public array $y,
    ) {}
}
