<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

use Carbon\CarbonImmutable;
use Modules\Hotel\Domain\Models\HotelDigitalKey;
use Modules\Hotel\Domain\StateMachines\DigitalKeyStateMachine;

/**
 * Layanan Verifikasi Kredensial Pintu Masuk / Turnstile Lift Kamar Hotel.
 */
final class HotelTurnstileAccessValidationService
{
    /**
     * @return array{access_granted: bool, reason: string, guest_user_id: string|null}
     */
    public function validateTurnstileScan(string $sessionTokenHash, string $scannedDoorUnit): array
    {
        $key = HotelDigitalKey::where('session_token_hash', $sessionTokenHash)->first();

        if ($key === null) {
            return [
                'access_granted' => false,
                'reason' => 'Kunci digital tidak dikenali atau token palsu',
                'guest_user_id' => null,
            ];
        }

        if ($key->status !== DigitalKeyStateMachine::STATUS_ACTIVE) {
            return [
                'access_granted' => false,
                'reason' => "Status kunci kamar tidak aktif ({$key->status})",
                'guest_user_id' => $key->guest_user_id,
            ];
        }

        if (CarbonImmutable::now()->isAfter($key->valid_until)) {
            return [
                'access_granted' => false,
                'reason' => 'Masa berlaku kunci kamar digital telah kedaluwarsa',
                'guest_user_id' => $key->guest_user_id,
            ];
        }

        return [
            'access_granted' => true,
            'reason' => 'Akses pintu kamar/lift disetujui',
            'guest_user_id' => $key->guest_user_id,
        ];
    }
}
