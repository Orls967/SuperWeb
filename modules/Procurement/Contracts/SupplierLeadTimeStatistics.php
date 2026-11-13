<?php

declare(strict_types=1);

namespace Modules\Procurement\Contracts;

/**
 * Statistik waktu tunggu pemasok (hari bulat) dari PO dan penerimaan barang (prc_*).
 * Diimplementasikan di Procurement; dipakai ControlTower untuk safety stock, skor risiko, dan kartu skor pemasok.
 */
interface SupplierLeadTimeStatistics
{
    /**
     * @return array{mean_days:int, sigma_days:int, samples:int, on_time_permil:int}
     *                                                                               Tanpa sampel: seluruh nilai 0. sigma = simpangan baku populasi dibulatkan ke hari bulat terdekat.
     */
    public function forSupplierAndItem(string $supplierRef, string $itemCode): array;

    /**
     * Pemasok utama item (pemasok dengan sampel penerimaan terbanyak, tie ke supplier_ref terkecil) atau null.
     */
    public function primarySupplierForItem(string $itemCode): ?string;

    /**
     * @return list<array{supplier_ref:string, supplier_name:string, item_code:string, mean_days:int, sigma_days:int, samples:int, on_time_permil:int}>
     *                                                                                                                                                  Terurut (supplier_ref, item_code).
     */
    public function allStatistics(): array;

    /**
     * Riwayat bulanan terbaru ke lama dibalik menjadi lama ke baru, maksimal $months bulan sampai $asOfDate.
     *
     * @return list<array{period:string, mean_days:int, on_time_permil:int, samples:int}>
     */
    public function monthlyHistory(string $supplierRef, int $months, string $asOfDate): array;
}
