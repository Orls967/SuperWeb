<?php

declare(strict_types=1);

namespace Modules\Hotel\Application\Actions;

use App\Security\BlindIndex;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Hotel\Application\Services\HotelInventoryService;
use Modules\Hotel\Domain\Models\HotelGuestIdentity;
use Modules\Hotel\Domain\Models\HotelHousekeepingLog;
use Modules\Hotel\Domain\Models\HotelReservation;
use Modules\Hotel\Domain\Models\HotelRoom;
use Modules\Hotel\Domain\Models\HotelRoomNight;
use Modules\Shared\Application\BaseAction;

class CheckInGuestAction extends BaseAction
{
    public function __construct(
        protected HotelInventoryService $inventoryService
    ) {}

    /**
     * @return array{
     *     reservation: HotelReservation,
     *     room: HotelRoom,
     *     identity: HotelGuestIdentity
     * }
     */
    public function handle(
        string $reservationId,
        string $roomId,
        string $identityType,
        string $identityNumber,
        string $fullName,
        ?string $phone = null
    ): array {
        return DB::transaction(function () use (
            $reservationId,
            $roomId,
            $identityType,
            $identityNumber,
            $fullName,
            $phone
        ) {
            $reservation = HotelReservation::lockForUpdate()->findOrFail($reservationId);

            if ($reservation->status !== 'confirmed') {
                throw new \InvalidArgumentException("Reservasi berstatus '{$reservation->status}' tidak dapat di-check-in.");
            }

            $room = HotelRoom::lockForUpdate()->findOrFail($roomId);

            // H-9: Check-in HANYA ke kamar available atau inspected
            if (! in_array($room->status, ['available', 'inspected'])) {
                throw new \InvalidArgumentException(
                    "Kamar {$room->room_number} belum siap untuk check-in (status saat ini: {$room->status})."
                );
            }

            $prevRoomStatus = $room->status;

            // H-11: Simpan identitas tamu terenkripsi + blind index HMAC-SHA256
            $bidx = BlindIndex::forNik($identityNumber);
            $identity = HotelGuestIdentity::create([
                'id' => (string) Str::uuid(),
                'user_id' => (int) $reservation->guest_user_id,
                'reservation_id' => $reservation->id,
                'identity_type' => strtoupper($identityType),
                'identity_number_encrypted' => $identityNumber,
                'identity_number_bidx' => $bidx,
                'full_name' => $fullName,
                'phone' => $phone,
            ]);

            // Mutasi status kamar menjadi occupied
            $room->update(['status' => 'occupied']);

            HotelHousekeepingLog::create([
                'id' => (string) Str::uuid(),
                'room_id' => $room->id,
                'from_status' => $prevRoomStatus,
                'to_status' => 'occupied',
                'changed_by_user_id' => (int) $reservation->guest_user_id,
                'notes' => "Tamu check-in: {$fullName} ({$reservation->reservation_number})",
            ]);

            // Perbarui reservasi dan alokasi kamar fisik
            $reservation->update([
                'status' => 'checked_in',
                'room_id' => $room->id,
            ]);

            HotelRoomNight::where('reservation_id', $reservation->id)
                ->update(['room_id' => $room->id]);

            $this->audit(
                action: 'hotel.guest.checked_in',
                auditable: $reservation,
                oldValues: ['status' => 'confirmed'],
                newValues: [
                    'reservation_number' => $reservation->reservation_number,
                    'room_number' => $room->room_number,
                    'identity_type' => $identityType,
                ],
                correlationId: "htl_in_{$reservation->reservation_number}",
                impactType: 'hotel_checkin'
            );

            return [
                'reservation' => $reservation,
                'room' => $room,
                'identity' => $identity,
            ];
        });
    }

    /**
     * Memindahkan tamu in-house ke kamar lain (pindah kamar).
     */
    public function moveRoom(string $reservationId, string $newRoomId, int $userId): array
    {
        return DB::transaction(function () use ($reservationId, $newRoomId, $userId) {
            $reservation = HotelReservation::lockForUpdate()->findOrFail($reservationId);

            if ($reservation->status !== 'checked_in') {
                throw new \InvalidArgumentException('Pindah kamar hanya dapat dilakukan untuk tamu in-house.');
            }

            $oldRoom = HotelRoom::lockForUpdate()->findOrFail($reservation->room_id);
            $newRoom = HotelRoom::lockForUpdate()->findOrFail($newRoomId);

            if (! in_array($newRoom->status, ['available', 'inspected'])) {
                throw new \InvalidArgumentException("Kamar baru {$newRoom->room_number} tidak tersedia (status: {$newRoom->status}).");
            }

            // Kamar lama berubah menjadi dirty
            $oldRoom->update(['status' => 'dirty']);
            HotelHousekeepingLog::create([
                'id' => (string) Str::uuid(),
                'room_id' => $oldRoom->id,
                'from_status' => 'occupied',
                'to_status' => 'dirty',
                'changed_by_user_id' => $userId,
                'notes' => "Pindah kamar ke {$newRoom->room_number}",
            ]);

            // Kamar baru menjadi occupied
            $newRoom->update(['status' => 'occupied']);
            HotelHousekeepingLog::create([
                'id' => (string) Str::uuid(),
                'room_id' => $newRoom->id,
                'from_status' => $newRoom->status,
                'to_status' => 'occupied',
                'changed_by_user_id' => $userId,
                'notes' => "Tamu pindah dari {$oldRoom->room_number}",
            ]);

            // Pindahkan inventaris malam sisa
            HotelRoomNight::where('reservation_id', $reservation->id)
                ->update(['room_id' => $newRoom->id]);

            $reservation->update(['room_id' => $newRoom->id]);

            return [
                'reservation' => $reservation,
                'old_room' => $oldRoom,
                'new_room' => $newRoom,
            ];
        });
    }

    /**
     * Memperpanjang masa menginap tamu in-house.
     */
    public function extendStay(string $reservationId, int $additionalNights): array
    {
        return DB::transaction(function () use ($reservationId, $additionalNights) {
            $reservation = HotelReservation::lockForUpdate()->findOrFail($reservationId);

            if ($reservation->status !== 'checked_in') {
                throw new \InvalidArgumentException('Perpanjangan masa menginap hanya dapat dilakukan untuk tamu in-house.');
            }

            $currentOut = Carbon::parse($reservation->check_out_date);
            $newOut = $currentOut->copy()->addDays($additionalNights);

            $this->inventoryService->lockAndAssignNights(
                propertyId: $reservation->property_id,
                roomTypeId: $reservation->room_type_id,
                roomId: $reservation->room_id,
                reservationId: $reservation->id,
                checkInDate: $currentOut->toDateString(),
                checkOutDate: $newOut->toDateString()
            );

            $reservation->update([
                'check_out_date' => $newOut->toDateString(),
                'total_room_charge_idr' => $reservation->total_room_charge_idr + ($additionalNights * $reservation->daily_rate_idr),
            ]);

            return [
                'reservation' => $reservation,
                'new_check_out_date' => $newOut->toDateString(),
            ];
        });
    }

    /**
     * Memperbarui status kebersihan kamar (Housekeeping State Machine).
     * Dirty -> Cleaning -> Inspected -> Available
     */
    public function updateHousekeepingStatus(string $roomId, string $toStatus, int $userId, ?string $notes = null): HotelRoom
    {
        return DB::transaction(function () use ($roomId, $toStatus, $userId, $notes) {
            $room = HotelRoom::lockForUpdate()->findOrFail($roomId);
            $fromStatus = $room->status;

            $validTransitions = [
                'dirty' => ['cleaning'],
                'cleaning' => ['inspected'],
                'inspected' => ['available'],
                'available' => ['out_of_order', 'occupied'],
                'out_of_order' => ['available', 'dirty'],
                'occupied' => ['dirty'],
            ];

            if (isset($validTransitions[$fromStatus]) && ! in_array($toStatus, $validTransitions[$fromStatus])) {
                throw new \InvalidArgumentException(
                    "Transisi status housekeeping tidak valid: dari '{$fromStatus}' ke '{$toStatus}'."
                );
            }

            $room->update(['status' => $toStatus]);

            HotelHousekeepingLog::create([
                'id' => (string) Str::uuid(),
                'room_id' => $room->id,
                'from_status' => $fromStatus,
                'to_status' => $toStatus,
                'changed_by_user_id' => $userId,
                'notes' => $notes ?? "Status diperbarui menjadi {$toStatus}",
            ]);

            return $room;
        });
    }
}
