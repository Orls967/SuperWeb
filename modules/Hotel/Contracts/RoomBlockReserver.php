<?php

declare(strict_types=1);

namespace Modules\Hotel\Contracts;

/**
 * G7-K16 Room Block Reserver Contract.
 *
 * Digunakan oleh modul Venue (festival multi-stage, beach club VIP packaging)
 * dan modul eksternal lainnya untuk memesan blok kamar hotel/glamping mitra secara atomik.
 */
interface RoomBlockReserver
{
    /**
     * Memesan kamar dengan pessimistic lock dan pembuatan guest folio.
     *
     * @param  string  $propertyId  ID Properti Hotel / Resort mitra
     * @param  string  $roomId  ID Kamar / Tenda Glamping
     * @param  string  $guestUserId  ID Tamu / Pembeli Paket
     * @param  string  $checkInDate  Tanggal Check-in (Y-m-d)
     * @param  string  $checkOutDate  Tanggal Check-out (Y-m-d)
     * @param  int  $dailyRateIdr  Tarif kamar harian dalam Rupiah penuh
     * @return array{
     *     reservation_id: int|string,
     *     reservation_code: string,
     *     folio_id: int|string,
     *     nights: int,
     *     total_room_charge_idr: int,
     *     status: string
     * }
     */
    public function reserveBlock(
        string $propertyId,
        string $roomId,
        string $guestUserId,
        string $checkInDate,
        string $checkOutDate,
        int $dailyRateIdr
    ): array;

    /**
     * Membatalkan pemesanan blok kamar dan melepaskan lock inventaris.
     */
    public function releaseBlock(string $reservationId, string $reason): bool;
}
