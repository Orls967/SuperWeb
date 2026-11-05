<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Solvency;

final readonly class SolvencyResult
{
    /**
     * @param  array<string, int>  $riskChargesIdr
     * @param  array<string, array<string, int>>  $correlationMatrixBps
     * @param  string  $scaledSum  ⌊Σ ρ·R·R / 10000⌋ sebagai string desimal (bisa melebihi int64)
     */
    public function __construct(
        public array $riskChargesIdr,
        public array $correlationMatrixBps,
        public int $operationalIdr,
        public string $scaledSum,
        public int $diversifiedIdr,
        public int $rbcIdr,
        public int $availableCapitalIdr,
        public int $solvencyRatioBps,
    ) {}
}
