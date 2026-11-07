<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Services;

/**
 * Kalkulator Biaya Kupasan Tanah Penutup (Overburden Cost per BCM) & Stripping Ratio
 * dalam satuan integer rupiah dan basis points (D93-1, D93-7).
 */
final class MiningCostPerBcmCalculator
{
    /**
     * @param  int  $overburdenBcm  Volume kupasan tanah penutup (Bank Cubic Meter)
     * @param  int  $oreTonnageKg  Tonase bijih tertambang (kg)
     * @param  int  $totalHaulingCostIdr  Total biaya kontraktor angkut (IDR)
     * @param  int  $totalFuelCostIdr  Total biaya bahan bakar solar (IDR)
     * @param  int  $totalDrillBlastCostIdr  Total biaya pemboran & peledakan (IDR)
     * @return array{
     *     stripping_ratio_bps: int,
     *     overburden_allocation_idr: int,
     *     ore_allocation_idr: int,
     *     cost_per_bcm_idr: int,
     *     cost_per_ton_ore_idr: int,
     *     total_cost_idr: int
     * }
     */
    public function calculate(
        int $overburdenBcm,
        int $oreTonnageKg,
        int $totalHaulingCostIdr,
        int $totalFuelCostIdr,
        int $totalDrillBlastCostIdr = 0,
    ): array {
        $totalCostIdr = max(0, $totalHaulingCostIdr) + max(0, $totalFuelCostIdr) + max(0, $totalDrillBlastCostIdr);
        $oreTons = max(1, intdiv(max(0, $oreTonnageKg), 1000));

        // Stripping ratio dalam bps (1.00x = 10.000 bps)
        $strippingRatioBps = intdiv(max(0, $overburdenBcm) * 10000, $oreTons);

        // Alokasi biaya 7000 bps (70%) ke pengupasan OB, 3000 bps (30%) ke ekstraksi bijih
        $obPortionIdr = intdiv($totalCostIdr * 7000, 10000);
        $orePortionIdr = $totalCostIdr - $obPortionIdr;

        $costPerBcmIdr = $overburdenBcm > 0 ? intdiv($obPortionIdr, $overburdenBcm) : 0;
        $costPerTonOreIdr = intdiv($orePortionIdr, $oreTons);

        return [
            'stripping_ratio_bps' => $strippingRatioBps,
            'overburden_allocation_idr' => $obPortionIdr,
            'ore_allocation_idr' => $orePortionIdr,
            'cost_per_bcm_idr' => $costPerBcmIdr,
            'cost_per_ton_ore_idr' => $costPerTonOreIdr,
            'total_cost_idr' => $totalCostIdr,
        ];
    }
}
