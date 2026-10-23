<?php

declare(strict_types=1);

namespace Modules\Trade\Application\Actions;

use InvalidArgumentException;
use Modules\Trade\Domain\Models\ImportOrder;

class CalculateIncotermsLandedCostAction
{
    /**
     * Calculate landed cost for import order based on CIF value, Bea Masuk (BM), PPN impor, and PPh 22 impor.
     *
     * @return array{
     *     import_order: ImportOrder,
     *     cif_idr: int,
     *     bm_idr: int,
     *     ppn_idr: int,
     *     pph22_idr: int,
     *     total_landed_cost_idr: int
     * }
     */
    public function execute(
        ImportOrder $importOrder,
        int $cifIdr,
        float $dutyPercent = 5.0,
        float $vatPercent = 11.0,
        float $pph22Percent = 2.5
    ): array {
        if ($cifIdr <= 0) {
            throw new InvalidArgumentException('Nilai CIF IDR harus lebih besar dari 0.');
        }

        // Bea Masuk (BM)
        $bmIdr = (int) round(($cifIdr * $dutyPercent) / 100.0);
        $nilaiImpor = $cifIdr + $bmIdr;

        // PPN Impor (11%) & PPh 22 Impor (2.5% API)
        $ppnIdr = (int) round(($nilaiImpor * $vatPercent) / 100.0);
        $pph22Idr = (int) round(($nilaiImpor * $pph22Percent) / 100.0);

        $totalLandedCostIdr = $cifIdr + $bmIdr + $ppnIdr + $pph22Idr;

        $importOrder->update([
            'cif_idr' => $cifIdr,
            'customs_duty_bm_idr' => $bmIdr,
            'import_vat_ppn_idr' => $ppnIdr,
            'import_tax_pph22_idr' => $pph22Idr,
            'total_landed_cost_idr' => $totalLandedCostIdr,
        ]);

        return [
            'import_order' => $importOrder->fresh(),
            'cif_idr' => $cifIdr,
            'bm_idr' => $bmIdr,
            'ppn_idr' => $ppnIdr,
            'pph22_idr' => $pph22Idr,
            'total_landed_cost_idr' => $totalLandedCostIdr,
        ];
    }
}
