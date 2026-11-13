<?php

declare(strict_types=1);

namespace Modules\Wms\Domain\Services;

use InvalidArgumentException;

/**
 * Perencana susunan palet (Ti x Hi) berbasis berat maksimum & tinggi lapisan (integer murni).
 */
final class PalletizationPlanner
{
    /**
     * @return array{
     *     cases_per_layer: int,
     *     layers_per_pallet: int,
     *     cases_per_pallet: int,
     *     pallets_required: int,
     *     remainder_cases: int,
     *     total_gross_weight_g: int
     * }
     */
    public function plan(
        int $totalCases,
        int $caseWeightG,
        int $caseHeightMm,
        int $casesPerLayerTi = 12,
        int $maxPalletHeightMm = 1500,
        int $maxPalletWeightG = 1000000,
        int $palletTareWeightG = 25000,
    ): array {
        if ($totalCases < 0 || $caseWeightG <= 0 || $caseHeightMm <= 0 || $casesPerLayerTi <= 0) {
            throw new InvalidArgumentException('Parameter paletisasi tidak valid.');
        }

        $maxLayersByHeight = intdiv($maxPalletHeightMm, $caseHeightMm);
        $layerWeightG = $casesPerLayerTi * $caseWeightG;
        $maxLayersByWeight = intdiv(max(0, $maxPalletWeightG - $palletTareWeightG), $layerWeightG);

        $layersPerPallet = max(1, min($maxLayersByHeight, $maxLayersByWeight));
        $casesPerPallet = $casesPerLayerTi * $layersPerPallet;

        $fullPallets = intdiv($totalCases, $casesPerPallet);
        $remainder = $totalCases % $casesPerPallet;
        $palletsRequired = $fullPallets + ($remainder > 0 ? 1 : 0);

        $totalWeightG = ($totalCases * $caseWeightG) + ($palletsRequired * $palletTareWeightG);

        return [
            'cases_per_layer' => $casesPerLayerTi,
            'layers_per_pallet' => $layersPerPallet,
            'cases_per_pallet' => $casesPerPallet,
            'pallets_required' => $palletsRequired,
            'remainder_cases' => $remainder,
            'total_gross_weight_g' => $totalWeightG,
        ];
    }
}
