<?php

declare(strict_types=1);

namespace Modules\Intercompany\Domain\DTOs;

final class CostSharingAllocationResultDTO
{
    public function __construct(
        public readonly string $agreementCode,
        public readonly string $period,
        public readonly string $providerEntity,
        public readonly string $beneficiaryEntity,
        public readonly int $allocationRatioPermille,
        public readonly int $baseCostIdr,
        public readonly int $markupAmountIdr,
        public readonly int $totalBilledIdr,
        public readonly string $status
    ) {}
}
