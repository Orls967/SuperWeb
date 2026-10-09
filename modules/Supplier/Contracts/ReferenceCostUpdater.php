<?php

declare(strict_types=1);

namespace Modules\Supplier\Contracts;

/**
 * Jembatan kontrak Fase 32.8 — modul Resto mengikat implementasi.
 *
 * Dipanggil SupplierService ketika harga terakhir pemasok berubah, agar
 * MAC referensi bahan (moving average cost) ikut diperbarui TANPA Supplier
 * mengimpor Domain Resto (aturan batas arsitektur).
 */
interface ReferenceCostUpdater
{
    /**
     * Perbarui biaya referensi per satuan dasar untuk produk internal tertentu.
     *
     * @param  int  $internalProductId  id produk/bahan internal (store_products atau resto_ingredients)
     * @param  string  $unitPrice  harga per unit (skala integer/decimal string — tanpa float)
     * @param  string  $currency  kode mata uang (mis. 'IDR')
     * @return bool true bila biaya diperbarui, false bila produk tidak dikenal / bukan IDR
     */
    public function updateReferenceCost(int $internalProductId, string $unitPrice, string $currency): bool;
}
