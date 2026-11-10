<?php

declare(strict_types=1);

namespace Modules\Intercompany\Domain\DTOs;

final class IntercompanyAssetTransferDTO
{
    public function __construct(
        public readonly string $transferCode,
        public readonly string $assetCode,
        public readonly string $sourceEntity,
        public readonly string $targetEntity,
        public readonly int $historicalCostIdr,
        public readonly int $accumulatedDepreciationIdr,
        public readonly int $netBookValueIdr,
        public readonly int $transferPriceIdr,
        public readonly int $gainLossIdr,
        public readonly string $status
    ) {}
}
