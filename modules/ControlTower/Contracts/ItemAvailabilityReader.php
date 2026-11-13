<?php

declare(strict_types=1);

namespace Modules\ControlTower\Contracts;

/**
 * Pembaca posisi stok per kode item untuk ControlTower. Implementasi membaca stok gudang lewat
 * Modules\Wms\Contracts\StockAvailabilityReader (bukan tabel ringkasan, bukan posting ledger).
 */
interface ItemAvailabilityReader
{
    /**
     * @return array{on_hand:int, available:int, quarantine:int, hold:int, oldest_lot_age_days:int}
     *                                                                                              Semua kuantitas bilangan bulat satuan dasar; stok non-available tidak ikut dihitung ke available.
     */
    public function positionFor(string $itemCode, string $asOfDate): array;
}
