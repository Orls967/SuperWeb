<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Flow;

/**
 * DTO hasil komputasi aliran maksimum Dinic beserta sertifikat min-cut (PR2).
 */
final readonly class FlowResult
{
    /**
     * @param  int  $maxFlow  Nilai total aliran maksimum
     * @param  array<int, int>  $flowPerEdge  Peta indeks sisi asli => aliran terkirim
     * @param  list<int>  $sourceCutSet  Himpunan simpul S pada potongan minimum (reachable dari source)
     */
    public function __construct(
        public int $maxFlow,
        public array $flowPerEdge,
        public array $sourceCutSet,
    ) {}
}
