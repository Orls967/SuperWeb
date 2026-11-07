<?php

declare(strict_types=1);

namespace Modules\Hotel\Application\DTOs;

final readonly class AwardNightBookingDTO
{
    public function __construct(
        public string $bookingId,
        public string $travelPassId,
        public string $propertyReference,
        public string $roomTypeReference,
        public string $stayDate,
        public int $pointsRedeemed,
        public int $propertyReimbursementIdr,
        public bool $isFloorApplied,
        public string $confirmationCode
    ) {}
}
