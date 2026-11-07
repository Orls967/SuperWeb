<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Events;

final readonly class AwardNightRedeemedEvent
{
    public function __construct(
        public string $bookingId,
        public string $travelPassId,
        public string $propertyReference,
        public string $roomTypeReference,
        public int $pointsRedeemed,
        public int $propertyReimbursementIdr,
        public bool $isFloorApplied
    ) {}
}
