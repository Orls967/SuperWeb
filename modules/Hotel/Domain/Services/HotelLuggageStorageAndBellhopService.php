<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

/**
 * Layanan Penitipan Bagasi Tamu Pre-Checkin / Post-Checkout (Concierge Bellhop).
 */
final class HotelLuggageStorageAndBellhopService
{
    /**
     * @return array{luggage_tag_code: string, pieces_stored: int, storage_bay: string}
     */
    public function storeGuestLuggage(string $guestName, int $piecesCount, string $claimPhone): array
    {
        $tag = 'LUG-' . date('His') . '-' . substr(md5($guestName . $claimPhone), 0, 4);

        return [
            'luggage_tag_code' => strtoupper($tag),
            'pieces_stored' => $piecesCount,
            'storage_bay' => 'CONCIERGE_BAY_C',
        ];
    }
}
