<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Events;

use Carbon\CarbonImmutable;

final class BanquetMarginEscalatedEvent
{
    public readonly string $occurredAt;

    public function __construct(
        public readonly string $contractId,
        public readonly int $contractValueIdr,
        public readonly int $totalBomCostIdr,
        public readonly float $calculatedMarginPct,
        public readonly float $minimumRequiredMarginPct = 40.0
    ) {
        $this->occurredAt = CarbonImmutable::now()->toIso8601String();
    }
}
