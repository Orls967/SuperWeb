<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Services;

final class MiningCostPerBcmCalculator
{
    /**
     * Menghitung Stripping Ratio (SR), biaya overburden per BCM (Bank Cubic Meter),
     * dan biaya penambangan bijih per ton.
     *
     * @param int $overburdenBcm Volume kupasan tanah penutup (BCM)
     * @param int $oreTonnageKg Tonase bijih tertambang (kg)
     * @param int $totalHaulingCostIdr Total biaya kontraktor hauling
     * @param int $totalFuelCostIdr Total biaya BBM solar
     * @param int $totalDrillBlastCostIdr Total biaya pemboran & peledakan
     * @return array{
     *     stripping_ratio: float,
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
        $totalCost = $totalHaulingCostIdr + $totalFuelCostIdr + $totalDrillBlastCostIdr;

        $oreTon = $oreTonnageKg / 1000.0;
        $sr = $oreTon > 0 ? round($overburdenBcm / $oreTon, 2) : 0.0;

        // Alokasi proporsional: 70% biaya ke OB removal, 30% ke Ore mining
        $obPortion = (int) round($totalCost * 0.70);
        $orePortion = $totalCost - $obPortion;

        $costPerBcm = $overburdenBcm > 0 ? (int) round($obPortion / $overburdenBcm) : 0;
        $costPerTonOre = $oreTon > 0 ? (int) round($orePortion / $oreTon) : 0;

        return [
            'stripping_ratio' => $sr,
            'cost_per_bcm_idr' => $costPerBcm,
            'cost_per_ton_ore_idr' => $costPerTonOre,
            'total_cost_idr' => $totalCost,
        ];
    }
}
