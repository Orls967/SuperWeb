<?php

declare(strict_types=1);

namespace Modules\Intercompany\Domain\DTOs;

final class EliminationMatchItemDTO
{
    public function __construct(
        public readonly string $sellerEntity,
        public readonly string $buyerEntity,
        public readonly int $receivableAmountIdr,
        public readonly int $payableAmountIdr,
        public readonly int $eliminatedBalanceIdr,
        public readonly int $eliminatedPnlIdr,
        public readonly int $discrepancyIdr,
        public readonly int $inTransitAmountIdr,
        public readonly string $status
    ) {}
}
