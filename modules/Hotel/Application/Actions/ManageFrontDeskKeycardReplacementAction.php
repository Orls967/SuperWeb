<?php

declare(strict_types=1);

namespace Modules\Hotel\Application\Actions;

use Carbon\CarbonImmutable;
use Modules\Hotel\Domain\Models\HotelDigitalKey;
use Modules\Hotel\Domain\Models\HotelReservation;
use Modules\Hotel\Domain\Services\HotelFrontOfficeKeycardEncoderService;
use Modules\Hotel\Domain\Services\HotelSmartLockProvisioningService;
use Modules\Hotel\Domain\StateMachines\DigitalKeyStateMachine;

/**
 * Tindakan Penggantian Kunci Kamar Hilang/Rusak & Revokasi Kunci Lama (Keycard Replacement).
 */
final class ManageFrontDeskKeycardReplacementAction
{
    public function __construct(
        private readonly HotelSmartLockProvisioningService $provisioningService,
        private readonly HotelFrontOfficeKeycardEncoderService $encoderService
    ) {}

    /**
     * @return array{reservation_id: string, new_digital_key_id: int, physical_card_uid: string, previous_keys_revoked: int}
     */
    public function execute(string|int $reservationId, string $replacementReason = 'LOST_KEY'): array
    {
        $reservation = HotelReservation::findOrFail((string) $reservationId);

        // 1. Revokasi semua kunci aktif sebelumnya untuk reservasi ini
        $activeKeys = HotelDigitalKey::where('reservation_id', (string) $reservationId)
            ->where('status', DigitalKeyStateMachine::STATUS_ACTIVE)
            ->get();

        $revokedCount = 0;
        foreach ($activeKeys as $key) {
            $this->provisioningService->revokeKey($key, $replacementReason);
            $revokedCount++;
        }

        // 2. Terbitkan sesi digital key baru
        $expiry = CarbonImmutable::parse($reservation->check_out_date)->setTime(12, 0);
        $newKeyRes = $this->provisioningService->issueKeyForGuest(
            reservationId: $reservation->id,
            guestUserId: $reservation->guest_user_id ?: 'guest-1',
            roomId: $reservation->room_id ?: 'room-1',
            validUntil: $expiry
        );

        // 3. Encode fisik baru bila diperlukan
        $rfidRes = $this->encoderService->encodePhysicalRfidKey(
            roomId: $reservation->room_id ?: 'room-1',
            validUntil: $expiry
        );

        return [
            'reservation_id' => (string) $reservation->id,
            'new_digital_key_id' => (int) $newKeyRes['digital_key']->id,
            'physical_card_uid' => $rfidRes['card_uid'],
            'previous_keys_revoked' => $revokedCount,
        ];
    }
}
