<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Events;

use Carbon\CarbonImmutable;

final class EscrowMilestoneReleasedEvent
{
    public readonly string $occurredAt;

    public function __construct(
        public readonly string $packageBookingId,
        public readonly string $vendorType,
        public readonly int $releasedAmountIdr,
        public readonly int $remainingEscrowBalanceIdr
    ) {
        $this->occurredAt = CarbonImmutable::now()->toIso8601String();
    }
}
