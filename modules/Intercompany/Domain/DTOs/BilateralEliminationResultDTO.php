<?php

declare(strict_types=1);

namespace Modules\Intercompany\Domain\DTOs;

use Modules\Intercompany\Domain\Algorithms\Certificates\BilateralEliminationCertificate;

final class BilateralEliminationResultDTO
{
    /**
     * @param  array<int, string>  $periods
     * @param  array<int, EliminationMatchItemDTO>  $results
     */
    public function __construct(
        public readonly array $periods,
        public readonly int $matchedPairCount,
        public readonly int $totalEliminatedBalanceIdr,
        public readonly int $totalEliminatedPnlIdr,
        public readonly int $totalInTransitIdr,
        public readonly int $totalDiscrepancyIdr,
        public readonly array $results,
        public readonly BilateralEliminationCertificate $certificate
    ) {}
}
