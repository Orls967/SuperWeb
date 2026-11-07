<?php

declare(strict_types=1);

namespace Modules\Trade\Application\Services;

use InvalidArgumentException;
use Modules\Trade\Contracts\ImportLandedCostQuoter;

final class ImportLandedCostQuoterService implements ImportLandedCostQuoter
{
    /**
     * Tarif Cukai MMEA Spesifik per Liter (PMK 160/2023) dalam Rupiah.
     */
    private const array EXCISE_RATES_PER_LITER = [
        'A' => 16_500,   // Kadar s/d 5%
        'B' => 42_500,   // Kadar > 5% s/d 20%
        'C' => 150_000,  // Kadar > 20% s/d 55%
    ];

    public function quoteMmeaLandedCost(
        int $cifIdr,
        int $volumeLiters,
        string $alcoholTier = 'C',
        int $dutyBps = 500,
        int $vatBps = 1100,
        int $pph22Bps = 250
    ): array {
        if ($cifIdr <= 0) {
            throw new InvalidArgumentException('Nilai CIF IDR harus lebih besar dari 0.');
        }

        if ($volumeLiters < 0) {
            throw new InvalidArgumentException('Volume liter tidak boleh negatif.');
        }

        $tier = strtoupper($alcoholTier);
        $exciseRate = self::EXCISE_RATES_PER_LITER[$tier] ?? self::EXCISE_RATES_PER_LITER['C'];

        // 1. Bea Masuk (BM)
        $customsDutyIdr = (int) intdiv($cifIdr * $dutyBps, 10_000);

        // 2. Cukai MMEA Spesifik per Liter
        $exciseTaxIdr = (int) ($volumeLiters * $exciseRate);

        // 3. Nilai Impor = CIF + Bea Masuk + Cukai
        $nilaiImporIdr = $cifIdr + $customsDutyIdr + $exciseTaxIdr;

        // 4. PPN Impor (11%) & PPh 22 Impor (2.5%)
        $importVatIdr = (int) intdiv($nilaiImporIdr * $vatBps, 10_000);
        $importPph22Idr = (int) intdiv($nilaiImporIdr * $pph22Bps, 10_000);

        // 5. Total Landed Cost
        $totalLandedCostIdr = $cifIdr + $customsDutyIdr + $exciseTaxIdr + $importVatIdr + $importPph22Idr;

        // Effective Tax Rate (bps terhadap CIF)
        $totalTaxes = $customsDutyIdr + $exciseTaxIdr + $importVatIdr + $importPph22Idr;
        $effectiveRateBps = (int) intdiv($totalTaxes * 10_000, $cifIdr);

        return [
            'cif_idr' => $cifIdr,
            'customs_duty_idr' => $customsDutyIdr,
            'excise_tax_idr' => $exciseTaxIdr,
            'import_vat_idr' => $importVatIdr,
            'import_pph22_idr' => $importPph22Idr,
            'total_landed_cost_idr' => $totalLandedCostIdr,
            'effective_tax_rate_bps' => $effectiveRateBps,
        ];
    }
}
