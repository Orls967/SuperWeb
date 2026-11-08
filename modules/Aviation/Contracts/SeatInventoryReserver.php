<?php

declare(strict_types=1);

namespace Modules\Aviation\Contracts;

/**
 * G7-K26: Modules\Aviation\Contracts\SeatInventoryReserver
 *
 * Kontrak inventori kursi penerbangan (dibuat di Fase 177; dipakai Fase 178 dan Fase 113).
 */
interface SeatInventoryReserver
{
    /**
     * Reservasi sementara kursi dengan batas waktu TTL.
     *
     * @param int $flightId
     * @param string $cabin
     * @param int $seats
     * @param string $idempotencyKey
     * @return array{hold_id: int, fare_minor: int, currency: string, expires_at: string}
     */
    public function hold(int $flightId, string $cabin, int $seats, string $idempotencyKey): array;

    /**
     * Konfirmasi pembayaran dan terbitkan tiket definitif.
     *
     * @param int $holdId
     * @param list<int> $passengerPartyIds
     * @param string $idempotencyKey
     * @return list<int> Daftar ID tiket yang berhasil diterbitkan
     */
    public function confirm(int $holdId, array $passengerPartyIds, string $idempotencyKey): array;

    /**
     * Lepaskan hold kursi yang dibatalkan atau kedaluwarsa.
     *
     * @param int $holdId
     * @param string $idempotencyKey
     * @return void
     */
    public function release(int $holdId, string $idempotencyKey): void;
}

