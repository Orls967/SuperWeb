<?php

declare(strict_types=1);

namespace Modules\Intercompany\Domain\DTOs;

final class IntercompanyStockTransferDTO
{
    public function __construct(
        public readonly string $shipmentCode,
        public readonly string $sourceEntity,
        public readonly string $targetEntity,
        public readonly string $customsPebNumber,
        public readonly string $customsPibNumber,
        public readonly int $cifValueIdr,
        public readonly int $importDutyIdr,
        public readonly int $importVatIdr,
        public readonly int $totalLandedCostIdr,
        public readonly string $status
    ) {}
}
