<?php

declare(strict_types=1);

namespace Modules\Hotel\Application\Services;

use Modules\Hotel\Application\Actions\CancelReservationAction;
use Modules\Hotel\Application\Actions\ReserveRoomWithInventoryNightLockAction;
use Modules\Hotel\Contracts\RoomBlockReserver;

final class RoomBlockReserverService implements RoomBlockReserver
{
    public function __construct(
        private readonly ReserveRoomWithInventoryNightLockAction $reserveAction,
        private readonly CancelReservationAction $cancelAction
    ) {}

    public function reserveBlock(
        string $propertyId,
        string $roomId,
        string $guestUserId,
        string $checkInDate,
        string $checkOutDate,
        int $dailyRateIdr
    ): array {
        $result = $this->reserveAction->handle(
            propertyId: $propertyId,
            roomId: $roomId,
            guestUserId: $guestUserId,
            checkInDate: $checkInDate,
            checkOutDate: $checkOutDate,
            dailyRateIdr: $dailyRateIdr
        );

        $reservation = $result['reservation'];
        $folio = $result['folio'];

        return [
            'reservation_id' => $reservation->id,
            'reservation_code' => (string) ($reservation->reservation_code ?? $reservation->id),
            'folio_id' => $folio->id,
            'nights' => $result['nights'],
            'total_room_charge_idr' => $result['total_room_charge_idr'],
            'status' => (string) ($reservation->status ?? 'CONFIRMED'),
        ];
    }

    public function releaseBlock(string $reservationId, string $reason): bool
    {
        return $this->cancelAction->handle($reservationId, $reason);
    }
}
