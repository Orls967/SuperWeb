<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Actions;

use Modules\Mining\Domain\Models\MiningSmelterEnergyEmission;
use Modules\Mining\Domain\Models\MiningSmelterFurnaceRun;

final class CalculateSmelterCarbonTaxLiabilityAction
{
    private const INDONESIA_CARBON_TAX_RATE_IDR_PER_TON = 30000; // Rp 30.000 / ton CO2e

    private const EMISSION_CAP_TCO2_PER_TON_METAL = 2.50; // Cap batas atas intensitas emisi

    /**
     * @return array{
     *     run_code: string,
     *     metal_output_tons: float,
     *     actual_emissions_tco2e: float,
     *     allowed_cap_tco2e: float,
     *     taxable_excess_tco2e: float,
     *     carbon_tax_liability_idr: int,
     *     tax_status: string
     * }
     */
    public function execute(MiningSmelterFurnaceRun $run): array
    {
        $emission = MiningSmelterEnergyEmission::where('furnace_run_id', $run->id)->first();

        $metalTons = (float) $run->metal_output_tons;
        $actualEmissions = $emission ? (float) $emission->total_emissions_tco2e : 0.0;
        $allowedCap = round($metalTons * self::EMISSION_CAP_TCO2_PER_TON_METAL, 3);

        $excess = max(0.0, round($actualEmissions - $allowedCap, 3));
        $taxLiabilityIdr = (int) round($excess * self::INDONESIA_CARBON_TAX_RATE_IDR_PER_TON);

        return [
            'run_code' => $run->run_code,
            'metal_output_tons' => $metalTons,
            'actual_emissions_tco2e' => $actualEmissions,
            'allowed_cap_tco2e' => $allowedCap,
            'taxable_excess_tco2e' => $excess,
            'carbon_tax_liability_idr' => $taxLiabilityIdr,
            'tax_status' => $excess > 0.0 ? 'PAYABLE_EXCESS' : 'COMPLIANT_ZERO_TAX',
        ];
    }
}
