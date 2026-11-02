<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Lp;

enum LpStatus: string
{
    case OPTIMAL = 'OPTIMAL';
    case INFEASIBLE = 'INFEASIBLE';
    case UNBOUNDED = 'UNBOUNDED';
    case INFEASIBLE_AFTER_ROUNDING = 'TIDAK_LAYAK_SETELAH_PEMBULATAN';
}
