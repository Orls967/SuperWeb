<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Reinsurance;

final readonly class TakafulSurplusResult
{
    /**
     * @param  array<string, int>  $allocations
     */
    public function __construct(
        public int $netContributionIdr,
        public int $netIncurredClaimsIdr,
        public int $technicalReserveIdr,
        public int $grossSurplusIdr,
        public int $participantSurplusShareIdr,
        public int $contingencyReserveShareIdr,
        public int $mudharabahOperatorShareIdr,
        public array $allocations,
        public string $certificate,
    ) {}
}
