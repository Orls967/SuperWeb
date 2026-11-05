<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\LifeContingency;

final readonly class TermLifePremiumResult
{
    /**
     * @param  array<int, array{age: int, lx: float, dx: float, qx: float, px: float, cx: float, mx: float, dx_comm: float, nx_comm: float}>  $commutationTable
     */
    public function __construct(
        public int $annualNetPremiumIdr,
        public int $netSinglePremiumIdr,
        public int $sumInsuredIdr,
        public int $age,
        public int $termYears,
        public int $interestRateBps,
        public array $commutationTable,
        public string $certificateHash,
    ) {}
}
