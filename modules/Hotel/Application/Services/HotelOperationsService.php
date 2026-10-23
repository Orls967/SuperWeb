<?php

declare(strict_types=1);

namespace Modules\Hotel\Application\Services;

use Carbon\Carbon;
use Illuminate\Support\Str;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Banking\Support\Posting;
use Modules\Hotel\Domain\Models\HotelFolio;
use Modules\Hotel\Domain\Models\HotelProperty;
use Modules\Hotel\Domain\Models\HotelReservation;
use Modules\Hotel\Domain\Models\HotelRoom;
use RuntimeException;

class HotelOperationsService
{
    public function __construct(
        protected ?Ledger $ledger = null
    ) {}

    /**
     * Central reservation booking with anti-oversell check and dynamic rate guardrail
     */
    public function createReservation(
        string $propertyId,
        string $roomType,
        string $guestUserId,
        string $checkInDate,
        string $checkOutDate,
        int $requestedRateIdr,
        ?int $contractRateIdr = null
    ): HotelReservation {
        $property = HotelProperty::findOrFail($propertyId);

        // Anti-oversell: find available room of that type
        $room = HotelRoom::where('property_id', $propertyId)
            ->where('room_type', $roomType)
            ->where('status', 'available')
            ->first();

        $status = 'confirmed';

        if (! $room) {
            $totalRoomsOfType = HotelRoom::where('property_id', $propertyId)
                ->where('room_type', $roomType)
                ->count();
            $activeReservations = HotelReservation::where('property_id', $propertyId)
                ->where('status', '!=', 'cancelled')
                ->where('status', '!=', 'checked_out')
                ->count();

            $maxAllowed = (int) floor($totalRoomsOfType * 1.03);

            if ($totalRoomsOfType > 0 && $activeReservations < $maxAllowed) {
                $room = HotelRoom::where('property_id', $propertyId)
                    ->where('room_type', $roomType)
                    ->first();
                $status = 'confirmed_overbooked';
            } else {
                throw new RuntimeException("No available {$roomType} rooms in property {$property->name}");
            }
        }

        // Rate guardrail: contract rate always wins; floor is room base_rate_idr
        $floorRate = $room->base_rate_idr;
        if ($contractRateIdr !== null) {
            $effectiveRate = $contractRateIdr;
        } else {
            $effectiveRate = max($floorRate, $requestedRateIdr);
        }

        $checkIn = Carbon::parse($checkInDate);
        $checkOut = Carbon::parse($checkOutDate);
        $nights = max(1, $checkIn->diffInDays($checkOut));
        $totalRoomCharge = $effectiveRate * $nights;

        $reservation = HotelReservation::create([
            'id' => (string) Str::uuid(),
            'property_id' => $property->id,
            'room_id' => $room->id,
            'reservation_number' => 'RES-'.strtoupper(Str::random(8)),
            'guest_user_id' => $guestUserId,
            'check_in_date' => $checkInDate,
            'check_out_date' => $checkOutDate,
            'daily_rate_idr' => $effectiveRate,
            'total_room_charge_idr' => $totalRoomCharge,
            'status' => $status,
        ]);

        // Create initial folio
        HotelFolio::create([
            'id' => (string) Str::uuid(),
            'reservation_id' => $reservation->id,
            'guest_user_id' => $guestUserId,
            'total_room_charges' => $totalRoomCharge,
            'total_addon_charges' => 0,
            'total_paid' => 0,
            'outstanding_balance' => $totalRoomCharge,
            'status' => 'open',
        ]);

        // Mark room as reserved/occupied if newly assigned
        if ($room->status === 'available') {
            $room->update(['status' => 'reserved']);
        }

        return $reservation;
    }

    /**
     * Reconfirm an overbooked reservation (H-3 reconfirmation flow)
     */
    public function reconfirmReservation(string $reservationId): HotelReservation
    {
        $reservation = HotelReservation::findOrFail($reservationId);
        if ($reservation->status === 'confirmed_overbooked') {
            $reservation->update(['status' => 'confirmed_reconfirmed']);
        }

        return $reservation->fresh();
    }

    /**
     * Check-in guest, issue smart lock token, deactivate energy setback
     */
    public function checkIn(string $reservationId): HotelReservation
    {
        $reservation = HotelReservation::findOrFail($reservationId);
        $room = HotelRoom::findOrFail($reservation->room_id);

        $smartLockToken = 'LOCK-'.hash('sha256', $reservation->id.now()->toIso8601String());
        $expiresAt = Carbon::parse($reservation->check_out_date)->endOfDay();

        $room->update([
            'status' => 'occupied',
            'smart_lock_token' => $smartLockToken,
            'smart_lock_expires_at' => $expiresAt,
            'energy_setback_active' => false, // guest present, HVAC normal
        ]);

        $reservation->update(['status' => 'checked_in']);

        return $reservation;
    }

    /**
     * Verify smart lock access
     */
    public function verifySmartLockAccess(string $roomId, string $providedToken): bool
    {
        $room = HotelRoom::findOrFail($roomId);

        if (! $room->smart_lock_token || $room->smart_lock_token !== $providedToken) {
            return false;
        }

        if ($room->smart_lock_expires_at && now()->greaterThan($room->smart_lock_expires_at)) {
            return false;
        }

        return true;
    }

    /**
     * Trigger room energy twin setback when guest leaves or room vacant
     */
    public function setRoomEnergySetback(string $roomId, bool $active): HotelRoom
    {
        $room = HotelRoom::findOrFail($roomId);
        $room->update(['energy_setback_active' => $active]);

        return $room;
    }

    /**
     * Check-out and settle folio via Ledger
     */
    public function checkOutAndSettleFolio(string $reservationId, int $paymentAmount): HotelFolio
    {
        $reservation = HotelReservation::findOrFail($reservationId);
        $folio = HotelFolio::where('reservation_id', $reservationId)->firstOrFail();
        $room = HotelRoom::findOrFail($reservation->room_id);

        $folio->increment('total_paid', $paymentAmount);
        $newBalance = max(0, ($folio->total_room_charges + $folio->total_addon_charges) - $folio->total_paid);

        $folio->update([
            'outstanding_balance' => $newBalance,
            'status' => $newBalance === 0 ? 'settled' : 'open',
        ]);

        if ($this->ledger && $paymentAmount > 0) {
            $this->ledger->post(new PostingDTO(
                type: 'HOTEL_FOLIO_SETTLEMENT',
                description: "Hotel folio settlement for reservation {$reservation->reservation_number}",
                idempotencyKey: "htl_folio_settle:{$folio->id}:{$paymentAmount}",
                entries: Posting::lines()
                    ->debit('htl:settlement_clearing:IDR', $paymentAmount)
                    ->credit('htl:room_revenue:IDR', $paymentAmount)
                    ->toEntries('IDR'),
                referenceType: 'htl_folios',
                referenceId: $folio->id,
            ));
        }

        $room->update([
            'status' => 'available',
            'smart_lock_token' => null,
            'smart_lock_expires_at' => null,
            'energy_setback_active' => true, // setback on vacant room
        ]);

        $reservation->update(['status' => 'checked_out']);

        return $folio->fresh();
    }

    /**
     * Two-source hotel operations audit.
     * Cross-checks rooms marked 'occupied' vs reservations marked 'checked_in'.
     */
    public function auditHotel(): array
    {
        $propertyCount = HotelProperty::count();
        $roomCount = HotelRoom::count();
        $reservationCount = HotelReservation::count();
        $folioCount = HotelFolio::count();
        $discrepancies = 0;

        // Two-source: rooms marked 'occupied' must have a checked_in reservation
        $occupiedRooms = HotelRoom::where('status', 'occupied')->get();
        foreach ($occupiedRooms as $room) {
            $hasCheckedIn = HotelReservation::where('room_id', $room->id)
                ->where('status', 'checked_in')
                ->exists();
            if (! $hasCheckedIn) {
                $discrepancies++;
            }
        }

        return [
            'status' => $discrepancies === 0 ? 'HEALTHY' : 'UNHEALTHY',
            'property_count' => $propertyCount,
            'room_count' => $roomCount,
            'reservation_count' => $reservationCount,
            'folio_count' => $folioCount,
            'discrepancies' => $discrepancies,
        ];
    }
}
