<?php

declare(strict_types=1);

namespace Modules\Hotel\Application\DTOs;

readonly class ReserveRoomDTO
{
    public function __construct(
        public string $propertyId,
        public string $roomTypeId,
        public string $ratePlanId,
        public string $guestUserId,
        public string $checkInDate,
        public string $checkOutDate,
        public string $idempotencyKey,
        public ?string $roomId = null,
        public ?int $depositAmountIdr = null,
    ) {}
}
