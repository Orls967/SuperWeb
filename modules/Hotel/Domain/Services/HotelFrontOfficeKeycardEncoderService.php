<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

use Carbon\CarbonImmutable;

/**
 * Layanan Pemrograman Kartu Kunci Fisik RFID (Keycard Encoder Service).
 */
final class HotelFrontOfficeKeycardEncoderService
{
    /**
     * @return array{card_uid: string, sector_encoded: bool, expiry_timestamp: int}
     */
    public function encodePhysicalRfidKey(string|int $roomId, CarbonImmutable $validUntil): array
    {
        $cardUid = 'RFID-'.strtoupper(substr(hash('sha256', (string) $roomId.$validUntil->timestamp), 0, 10));

        return [
            'card_uid' => $cardUid,
            'sector_encoded' => true,
            'expiry_timestamp' => $validUntil->timestamp,
        ];
    }
}
