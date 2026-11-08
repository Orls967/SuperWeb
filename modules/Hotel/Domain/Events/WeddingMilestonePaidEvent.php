<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Events;

final class WeddingMilestonePaidEvent
{
    public function __construct(
        public readonly string $scheduleId,
        public readonly string $contractNumber,
        public readonly int $milestoneIndex,
        public readonly string $milestoneName,
        public readonly int $amountPaid,
        public readonly int $remainingBalance
    ) {}
}
