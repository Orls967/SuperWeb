<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Islamic;

final readonly class SalamPositionBookResult
{
    /**
     * @param  array<string, mixed>  $contractSummaries
     */
    public function __construct(
        public string $commodityCode,
        public int $totalLongQuantity,
        public int $totalShortQuantity,
        public int $netOpenPositionQuantity,
        public int $totalLongCostCents,
        public int $totalShortCommitmentCents,
        public int $markToMarketValueCents,
        public int $unrealizedPnlCents,
        public int $fulfillmentRatioBps,
        public array $contractSummaries,
        public string $evaluatedAt,
        public string $certificateHash,
    ) {}
}
