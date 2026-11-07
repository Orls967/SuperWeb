<?php

declare(strict_types=1);

namespace Modules\Hotel\Application\DTOs;

final class SpaSessionBookingDTO
{
    public function __construct(
        public readonly string $bookingReference,
        public readonly string $treatmentName,
        public readonly string $guestName,
        public readonly string $therapistName,
        public readonly string $roomChamber,
        public readonly int $totalChargeIdr,
        public readonly bool $isPostedToRoomFolio,
        public readonly ?string $folioId
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'booking_reference' => $this->bookingReference,
            'treatment_name' => $this->treatmentName,
            'guest_name' => $this->guestName,
            'therapist_name' => $this->therapistName,
            'room_chamber' => $this->roomChamber,
            'total_charge_idr' => $this->totalChargeIdr,
            'is_posted_to_room_folio' => $this->isPostedToRoomFolio,
            'folio_id' => $this->folioId,
        ];
    }
}
