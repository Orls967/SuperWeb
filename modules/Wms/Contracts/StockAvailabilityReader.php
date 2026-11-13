<?php

declare(strict_types=1);

namespace Modules\Wms\Contracts;

/**
 * Kontrak pembaca ketersediaan stok gudang (tersedia = on_hand - reserved - quarantined).
 */
interface StockAvailabilityReader
{
    /**
     * Kuantitas tersedia (integer base unit) untuk suatu produk di gudang tertentu (atau semua gudang jika null).
     */
    public function getAvailableQty(int $productId, string|int|null $warehouseId = null): int;

    /**
     * Rincian stok per status untuk produk di suatu gudang.
     *
     * @return array{on_hand_base: int, reserved_base: int, quarantined_base: int, available_base: int}
     */
    public function getBreakdown(int $productId, string|int $warehouseId): array;
}
