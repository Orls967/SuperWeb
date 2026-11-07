<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Underground;

final readonly class HardyCrossVentilationNetworkSolverResult
{
    /**
     * @param  array<string, float>  $finalFlowsQ  Debit aliran final per branch (m^3/s)
     * @param  array<string, float>  $finalHeadLossesPa  Penurunan tekanan final per branch (Pa)
     */
    public function __construct(
        public array $finalFlowsQ,
        public array $finalHeadLossesPa,
        public int $iterationsCount,
        public bool $converged,
        public float $maxResidualFlow,
        public float $totalFanPowerKw,
        public string $hmacSignature
    ) {}
}
