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
    ) {
        if (trim($this->idempotencyKey) === '') {
            throw new \InvalidArgumentException('Idempotency-Key wajib disertakan.');
        }
        if ($this->checkInDate > $this->checkOutDate) {
            throw new \InvalidArgumentException('Tanggal check-in tidak boleh melampaui tanggal check-out.');
        }
        if ($this->depositAmountIdr !== null && $this->depositAmountIdr < 0) {
            throw new \InvalidArgumentException('Nominal deposit tidak boleh negatif.');
        }
    }
}
