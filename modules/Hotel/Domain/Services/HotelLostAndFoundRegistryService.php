<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

/**
 * Layanan Registrasi Barang Temuan Tamu Tertinggal (Lost & Found).
 */
final class HotelLostAndFoundRegistryService
{
    /**
     * @return array{tracking_code: string, storage_locker: string, status: string}
     */
    public function recordItemFound(
        string|int $roomId,
        string $itemDescription,
        string $finderStaffName,
        string $storageLocation = 'LOCKER_A3'
    ): array {
        $trackingCode = 'LNF-'.date('Ymd').'-'.substr(md5($itemDescription.$roomId), 0, 6);

        return [
            'tracking_code' => strtoupper($trackingCode),
            'storage_locker' => $storageLocation,
            'status' => 'SECURED_IN_VAULT',
        ];
    }
}
