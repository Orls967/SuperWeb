<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Events;

final readonly class LoyaltyTierUpgradedEvent
{
    public function __construct(
        public string $travelPassId,
        public string $oldTier,
        public string $newTier,
        public int $qualifyingNights,
        public int $qualifyingSpendIdr
    ) {}
}
