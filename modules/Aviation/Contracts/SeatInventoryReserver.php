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
     * @return array{hold_id: int, fare_minor: int, currency: string, expires_at: string}
     */
    public function hold(int $flightId, string $cabin, int $seats, string $idempotencyKey): array;

    /**
     * Konfirmasi pembayaran dan terbitkan tiket definitif.
     *
     * @param  list<int>  $passengerPartyIds
     * @return list<int> Daftar ID tiket yang berhasil diterbitkan
     */
    public function confirm(int $holdId, array $passengerPartyIds, string $idempotencyKey): array;

    /**
     * Lepaskan hold kursi yang dibatalkan atau kedaluwarsa.
     */
    public function release(int $holdId, string $idempotencyKey): void;
}
