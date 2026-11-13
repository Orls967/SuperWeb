<?php

declare(strict_types=1);

namespace Modules\ControlTower\Contracts;

/**
 * Kontrak janji pesanan (ATP/CTP) milik ControlTower. Modul pesanan memanggil kontrak ini,
 * tanpa membaca tabel sct_* secara langsung (W8-P6 KATALOG KONTRAK).
 */
interface PromiseQuoteInterface
{
    /**
     * Menghitung dan menyimpan janji untuk kuantitas bulat satuan dasar pada periode permintaan (1..T).
     * Idempoten per $idempotencyKey: kunci sama dengan payload sama mengembalikan janji yang sama,
     * payload berbeda melempar IdempotencyConflictException.
     *
     * @return array{promise_code:string, status:string, promised_period:?int, late_periods:int, atp_cumulative:int, ctp_cumulative:int, reason_code:?string, certificate_hash:string}
     *                                                                                                                                                                                 status: on_time|late|cannot_promise
     */
    public function quote(
        string $itemCode,
        int $quantity,
        int $requestedPeriod,
        string $idempotencyKey,
        ?string $orderReference = null,
        ?string $asOfDate = null,
    ): array;
}
