<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Adapters;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Crypt;

/**
 * Adaptor Simulasi S1 Smart Lock QR & Biometrik Pintu Kamar Hotel.
 */
final class SmartLockSimulationAdapter
{
    /**
     * @return array{session_token_hash: string, qr_payload_encrypted: string, simulated_level: string}
     */
    public static function generateDigitalSessionKey(int $reservationId, int $roomId, CarbonImmutable $validUntil): array
    {
        $rawSession = "HTL-KEY:RES-{$reservationId}:RM-{$roomId}:EXP-{$validUntil->timestamp}";
        $hash = hash('sha256', $rawSession);
        $encryptedQr = Crypt::encryptString("ENCRYPTED-LOCK-PAYLOAD:{$hash}");

        return [
            'session_token_hash' => $hash,
            'qr_payload_encrypted' => $encryptedQr,
            'simulated_level' => 'S1',
        ];
    }

    public static function verifyKeyAtTurnstile(string $sessionTokenHash, CarbonImmutable $validUntil): bool
    {
        return !CarbonImmutable::now()->isAfter($validUntil);
    }
}
