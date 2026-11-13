<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Contracts;

/**
 * Kapasitas dan material kumulatif per periode untuk perhitungan CTP, serta penerimaan MPS terjadwal (ATP).
 * Diimplementasikan di Manufacturing (pemilik data mfg_*); ControlTower hanya memakai kontraknya.
 */
interface CapacityAndMaterialProvider
{
    /**
     * @return array{capacity_cumulative:list<int>, material_cumulative:list<int>}
     *                                                                             Indeks 0 = periode 1; panjang daftar = $periods; kumulatif tidak turun; bilangan bulat satuan dasar.
     */
    public function cumulativeByPeriod(string $itemCode, int $periods, string $asOfDate): array;

    /**
     * Penerimaan MPS terjadwal per periode (indeks 0 = periode 1), panjang = $periods.
     *
     * @return list<int>
     */
    public function mpsReceipts(string $itemCode, int $periods, string $asOfDate): array;
}
