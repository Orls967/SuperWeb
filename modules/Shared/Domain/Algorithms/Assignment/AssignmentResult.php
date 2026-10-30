<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Assignment;

/**
 * DTO hasil penyelesaian masalah penugasan linear (Hungarian algorithm)
 * beserta sertifikat dual (u, v) (PR2).
 */
final readonly class AssignmentResult
{
    /**
     * @param  array<int, int>  $assignment  Peta indeks baris => indeks kolom
     * @param  int  $totalCost  Total biaya optimal
     * @param  list<int>  $u  Potensial dual baris (u_i)
     * @param  list<int>  $v  Potensial dual kolom (v_j)
     */
    public function __construct(
        public array $assignment,
        public int $totalCost,
        public array $u,
        public array $v,
    ) {}
}
