<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Actions;

use Modules\Mining\Domain\Models\MiningSmelterEnergyEmission;
use Modules\Mining\Domain\Models\MiningSmelterFurnaceRun;

final class EvaluateSmelterEnergyEmissionIntensityAction
{
    /**
     * @return array{
     *     emission_code: string,
     *     scope1_tco2e: float,
     *     scope2_tco2e: float,
     *     total_tco2e: float,
     *     intensity_tco2e_per_ton: float,
     *     carbon_tax_threshold_exceeded: bool,
     *     estimated_carbon_tax_idr: int
     * }
     */
    public function execute(MiningSmelterFurnaceRun $run): array
    {
        $emission = MiningSmelterEnergyEmission::where('furnace_run_id', $run->id)->first();

        $metalTons = max(0.1, (float) $run->metal_output_tons);
        $totalEmissions = $emission ? (float) $emission->total_emissions_tco2e : 125.0;
        $intensity = round($totalEmissions / $metalTons, 4);

        $thresholdExceeded = $intensity > 2.50; // Standar emisi sektor industri mineral ESDM / KLHK
        $taxableExcessTons = max(0.0, $totalEmissions - ($metalTons * 2.50));
        // Tarif pajak karbon UU HPP: Rp 30.000 / ton CO2e
        $carbonTaxIdr = (int) round($taxableExcessTons * 30000);

        if ($emission) {
            $emission->intensity_tco2e_per_ton_metal = $intensity;
            $emission->carbon_tax_threshold_exceeded = $thresholdExceeded;
            $emission->save();
        }

        return [
            'emission_code' => $emission ? $emission->emission_code : ('EMS-'.$run->run_code),
            'scope1_tco2e' => $emission ? (float) $emission->scope1_emissions_tco2e : 65.0,
            'scope2_tco2e' => $emission ? (float) $emission->scope2_emissions_tco2e : 60.0,
            'total_tco2e' => $totalEmissions,
            'intensity_tco2e_per_ton' => $intensity,
            'carbon_tax_threshold_exceeded' => $thresholdExceeded,
            'estimated_carbon_tax_idr' => $carbonTaxIdr,
        ];
    }
}
