<?php

declare(strict_types=1);

namespace Modules\Proptech\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Proptech\Domain\Models\Flex\FlexBooking;
use Modules\Shared\Application\BaseAction;

class GenerateSmartLockAccessPinAction extends BaseAction
{
    /**
     * Generate time-bounded smart lock IoT access PIN and door relay cryptographic token.
     *
     * @return array{booking_id: int, pin_code: string, door_relay_token: string, valid_from: string, valid_until: string}
     */
    public function handle(
        int $flexBookingId,
        string $passportHash
    ): array {
        return DB::transaction(function () use ($flexBookingId, $passportHash) {
            $booking = FlexBooking::lockForUpdate()->findOrFail($flexBookingId);

            if (! in_array($booking->status, ['CONFIRMED', 'CHECKED_IN'], true)) {
                throw new \RuntimeException("Booking is not in confirmed or checked-in status (current: {$booking->status}).");
            }

            // Cryptographic deterministic 6-digit PIN based on booking and secret
            $seed = hash('sha256', "SMARTLOCK_{$booking->id}_{$booking->booking_code}_{$passportHash}");
            $pinInt = hexdec(substr($seed, 0, 8)) % 1000000;
            $pinCode = str_pad((string) $pinInt, 6, '0', STR_PAD_LEFT);

            $doorRelayToken = hash('sha256', "DOOR_RELAY_{$booking->id}_{$pinCode}");

            $booking->checked_in_passport_hash = $passportHash;
            $booking->status = 'CHECKED_IN';
            $booking->checked_in_at = now();
            $booking->save();

            $this->audit(
                action: 'proptech.smart_lock.pin_generated',
                auditable: $booking,
                oldValues: ['status' => 'CONFIRMED'],
                newValues: [
                    'booking_id' => $booking->id,
                    'passport_hash' => $passportHash,
                    'door_relay_token' => $doorRelayToken,
                ],
                correlationId: "prp_lock_{$booking->id}",
                impactType: 'iot_access_control'
            );

            return [
                'booking_id' => $booking->id,
                'pin_code' => $pinCode,
                'door_relay_token' => $doorRelayToken,
                'valid_from' => $booking->start_time ? $booking->start_time->toIso8601String() : now()->toIso8601String(),
                'valid_until' => $booking->end_time ? $booking->end_time->toIso8601String() : now()->addHours(2)->toIso8601String(),
            ];
        });
    }
}
