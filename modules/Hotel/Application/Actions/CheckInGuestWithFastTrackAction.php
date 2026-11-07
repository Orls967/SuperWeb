<?php

declare(strict_types=1);

namespace Modules\Hotel\Application\Actions;

use Carbon\CarbonImmutable;
use Modules\Hotel\Domain\Models\HotelReservation;
use Modules\Hotel\Domain\Models\HotelRoom;
use Modules\Hotel\Domain\Services\HotelRoomAssignmentOptimizerService;
use Modules\Hotel\Domain\Services\HotelSmartLockProvisioningService;
use Modules\Hotel\Domain\StateMachines\ReservationStateMachine;

/**
 * Tindakan Check-In Cepat (Fast-Track Mobile Check-In) dengan Kunci Digital Otomatis.
 */
final class CheckInGuestWithFastTrackAction
{
    public function __construct(
        private readonly HotelRoomAssignmentOptimizerService $roomOptimizer,
        private readonly HotelSmartLockProvisioningService $keyService
    ) {}

    /**
     * @param  array<string, mixed>  $preferences
     * @return array{reservation_id: string, room_number: string, digital_key_id: int}
     */
    public function execute(string|int $reservationId, array $preferences = []): array
    {
        $reservation = HotelReservation::findOrFail((string) $reservationId);

        $optimal = $this->roomOptimizer->findOptimalRoom(
            propertyId: $reservation->property_id,
            roomTypeId: $reservation->room_type_id,
            guestPreferences: $preferences
        );

        if (! $optimal['assigned_room_id']) {
            abort(422, 'Tidak ada kamar siap huni yang dapat dialokasikan.');
        }

        $room = HotelRoom::findOrFail($optimal['assigned_room_id']);
        $room->update(['status' => 'occupied']);

        $sm = new ReservationStateMachine($reservation->status);
        $reservation->update([
            'status' => $sm->transitionTo(ReservationStateMachine::STATUS_CHECKED_IN),
        ]);

        $checkoutExpiry = CarbonImmutable::parse($reservation->check_out_date)->setTime(12, 0);
        $keyRes = $this->keyService->issueKeyForGuest(
            reservationId: $reservation->id,
            guestUserId: $reservation->guest_user_id ?: 'guest-1',
            roomId: $room->id,
            validUntil: $checkoutExpiry
        );

        return [
            'reservation_id' => (string) $reservation->id,
            'room_number' => (string) $room->room_number,
            'digital_key_id' => (int) $keyRes['digital_key']->id,
        ];
    }
}
