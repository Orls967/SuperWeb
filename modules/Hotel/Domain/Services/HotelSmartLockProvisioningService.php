<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

use Carbon\CarbonImmutable;
use Modules\Hotel\Domain\Adapters\SmartLockSimulationAdapter;
use Modules\Hotel\Domain\Models\HotelDigitalKey;
use Modules\Hotel\Domain\StateMachines\DigitalKeyStateMachine;

/**
 * Layanan Penerbitan & Revokasi Kunci Digital Kamar Berbasis Smart Lock IoT (S1).
 */
final class HotelSmartLockProvisioningService
{
    /**
     * @return array{digital_key: HotelDigitalKey, turnstile_ready: bool}
     */
    public function issueKeyForGuest(
        string|int $reservationId,
        string|int $guestUserId,
        string|int $roomId,
        CarbonImmutable $validUntil
    ): array {
        $resIdInt = is_numeric($reservationId) ? (int) $reservationId : crc32((string) $reservationId);
        $roomIdInt = is_numeric($roomId) ? (int) $roomId : crc32((string) $roomId);

        $sessionData = SmartLockSimulationAdapter::generateDigitalSessionKey(
            reservationId: $resIdInt,
            roomId: $roomIdInt,
            validUntil: $validUntil
        );

        $digitalKey = HotelDigitalKey::create([
            'reservation_id' => (string) $reservationId,
            'guest_user_id' => (string) $guestUserId,
            'room_id' => (string) $roomId,
            'status' => DigitalKeyStateMachine::STATUS_ACTIVE,
            'session_token_hash' => $sessionData['session_token_hash'],
            'qr_payload_encrypted' => $sessionData['qr_payload_encrypted'],
            'valid_until' => $validUntil,
        ]);

        return [
            'digital_key' => $digitalKey,
            'turnstile_ready' => true,
        ];
    }

    public function revokeKey(HotelDigitalKey $key, string $reason = 'checkout'): void
    {
        $sm = new DigitalKeyStateMachine($key->status);
        $nextStatus = $sm->transitionTo(DigitalKeyStateMachine::STATUS_REVOKED);
        $key->update([
            'status' => $nextStatus,
        ]);
    }
}
