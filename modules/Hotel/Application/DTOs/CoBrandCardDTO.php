<?php

declare(strict_types=1);

namespace Modules\Hotel\Application\DTOs;

final class CoBrandCardDTO
{
    public function __construct(
        public string $cashbackId,
        public string $travelPassId,
        public string $bankCode,
        public int $spendAmountIdr,
        public int $cashbackPoints,
        public int $cashbackValueIdr,
        public int $bankSubsidyIdr,
        public int $hotelCostIdr,
        public string $cardTier,
        public string $merchantCategory,
        public string $status,
        public string $processedAt
    ) {}
}
