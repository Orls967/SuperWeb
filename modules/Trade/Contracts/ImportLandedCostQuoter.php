<?php

declare(strict_types=1);

namespace Modules\Trade\Contracts;

/**
 * G7-K19 Import Landed Cost Quoter Contract.
 *
 * Menghitung estimasi landed cost impor produk bar & spirit beralkohol (MMEA)
 * sesuai regulasi kepabeanan dan PMK 160/2023.
 */
interface ImportLandedCostQuoter
{
    /**
     * Menghitung rincian landed cost impor MMEA (Minuman Mengandung Etil Alkohol).
     *
     * @param  int  $cifIdr  Nilai Cost, Insurance, and Freight dalam IDR penuh
     * @param  int  $volumeLiters  Volume total dalam liter
     * @param  string  $alcoholTier  Golongan MMEA ('A', 'B', 'C')
     * @param  int  $dutyBps  Bea Masuk dalam basis poin (default 500 bps = 5%)
     * @param  int  $vatBps  PPN Impor dalam basis poin (default 1100 bps = 11%)
     * @param  int  $pph22Bps  PPh 22 Impor dalam basis poin (default 250 bps = 2.5%)
     * @return array{
     *     cif_idr: int,
     *     customs_duty_idr: int,
     *     excise_tax_idr: int,
     *     import_vat_idr: int,
     *     import_pph22_idr: int,
     *     total_landed_cost_idr: int,
     *     effective_tax_rate_bps: int
     * }
     */
    public function quoteMmeaLandedCost(
        int $cifIdr,
        int $volumeLiters,
        string $alcoholTier = 'C',
        int $dutyBps = 500,
        int $vatBps = 1100,
        int $pph22Bps = 250
    ): array;
}
