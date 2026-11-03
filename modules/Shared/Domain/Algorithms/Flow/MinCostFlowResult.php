<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Flow;

/**
 * DTO hasil komputasi Min Cost Flow beserta sertifikat dualitas Johnson (PR2).
 */
final readonly class MinCostFlowResult
{
    /**
     * @param  int  $totalFlow  Total aliran terkirim
     * @param  int  $totalCost  Total biaya yang dikeluarkan
     * @param  array<int, int>  $flowPerEdge  Peta indeks sisi asli => aliran terkirim
     * @param  array<int, int>  $nodePotentials  Peta indeks simpul => potensial pi
     */
    public function __construct(
        public int $totalFlow,
        public int $totalCost,
        public array $flowPerEdge,
        public array $nodePotentials,
    ) {}
}
