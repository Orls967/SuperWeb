<?php

declare(strict_types=1);

namespace Modules\Hotel\Application\Actions;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Hotel\Domain\Models\HotelFolio;
use Modules\Hotel\Domain\Models\HotelReservation;
use Modules\Hotel\Domain\Models\HotelRoom;
use Modules\Shared\Application\BaseAction;

class ReserveRoomWithInventoryNightLockAction extends BaseAction
{
    /**
     * Reserve room with pessimistic inventory lock and auto-provisioned guest folio.
     *
     * @return array{reservation: HotelReservation, folio: HotelFolio, nights: int, total_room_charge_idr: int}
     */
    public function handle(
        string $propertyId,
        string $roomId,
        string $guestUserId,
        string $checkInDate,
        string $checkOutDate,
        int $dailyRateIdr
    ): array {
        return DB::transaction(function () use ($propertyId, $roomId, $guestUserId, $checkInDate, $checkOutDate, $dailyRateIdr) {
            $room = HotelRoom::lockForUpdate()->findOrFail($roomId);

            $in = Carbon::parse($checkInDate);
            $out = Carbon::parse($checkOutDate);

            if ($out->lte($in)) {
                throw new \InvalidArgumentException('Check-out date must be after check-in date.');
            }

            // Anti-overlap verification
            $hasOverlap = HotelReservation::where('room_id', $room->id)
                ->whereIn('status', ['confirmed', 'checked_in'])
                ->where(function ($q) use ($in, $out) {
                    $q->whereBetween('check_in_date', [$in, $out])
                        ->orWhereBetween('check_out_date', [$in, $out]);
                })
                ->exists();

            if ($hasOverlap) {
                throw new \RuntimeException("Room {$room->room_number} is already booked for the selected dates.");
            }

            $nights = max(1, $in->diffInDays($out));
            $totalRoomChargeIdr = $nights * $dailyRateIdr;

            $resNumber = 'HTL-RES-'.strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));

            $reservation = HotelReservation::create([
                'id' => (string) Str::uuid(),
                'property_id' => $propertyId,
                'room_id' => $room->id,
                'reservation_number' => $resNumber,
                'guest_user_id' => $guestUserId,
                'check_in_date' => $in->toDateString(),
                'check_out_date' => $out->toDateString(),
                'daily_rate_idr' => $dailyRateIdr,
                'total_room_charge_idr' => $totalRoomChargeIdr,
                'status' => 'confirmed',
            ]);

            $folio = HotelFolio::create([
                'id' => (string) Str::uuid(),
                'reservation_id' => $reservation->id,
                'guest_user_id' => $guestUserId,
                'total_room_charges' => $totalRoomChargeIdr,
                'total_addon_charges' => 0,
                'total_paid' => 0,
                'outstanding_balance' => $totalRoomChargeIdr,
                'status' => 'open',
            ]);

            $this->audit(
                action: 'hotel.reservation.created',
                auditable: $reservation,
                oldValues: null,
                newValues: [
                    'property_id' => $propertyId,
                    'room_number' => $room->room_number,
                    'nights' => $nights,
                    'total_room_charge_idr' => $totalRoomChargeIdr,
                ],
                correlationId: "htl_res_{$resNumber}",
                impactType: 'hotel_reservation'
            );

            return [
                'reservation' => $reservation,
                'folio' => $folio,
                'nights' => $nights,
                'total_room_charge_idr' => $totalRoomChargeIdr,
            ];
        });
    }
}
