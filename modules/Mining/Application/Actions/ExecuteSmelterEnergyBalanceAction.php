<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Actions;

use Modules\Mining\Domain\Models\MiningSmelterEnergyEmission;
use Modules\Mining\Domain\Models\MiningSmelterFurnaceRun;
use Modules\Shared\Domain\Algorithms\Smelter\SmelterEnergyReading;
use Modules\Shared\Domain\Algorithms\Smelter\SmelterFeedParameters;
use Modules\Shared\Domain\Algorithms\Smelter\SmelterPyrometallurgicalEnergyBalanceResult;
use Modules\Shared\Domain\Algorithms\Smelter\SmelterPyrometallurgicalEnergyBalanceSolver;
use Modules\Shared\Domain\Algorithms\Smelter\SmelterPyrometallurgicalEnergyBalanceVerifier;

final class ExecuteSmelterEnergyBalanceAction
{
    public function __construct(
        private readonly SmelterPyrometallurgicalEnergyBalanceSolver $solver,
        private readonly SmelterPyrometallurgicalEnergyBalanceVerifier $verifier
    ) {}

    public function execute(
        MiningSmelterFurnaceRun $run,
        ?float $overridePowerMwh = null
    ): SmelterPyrometallurgicalEnergyBalanceResult {
        $feed = new SmelterFeedParameters(
            oreFeedWmt: (float) $run->ore_feed_wmt,
            oreMoisturePct: (float) $run->ore_moisture_pct,
            niGradePct: (float) $run->ore_ni_grade_pct,
            feGradePct: (float) $run->ore_fe_grade_pct,
            sio2GradePct: 43.0,
            mgoGradePct: 22.0,
            al2o3GradePct: 2.5,
            coalReductantTons: (float) $run->coal_reductant_tons,
            coalFixedCarbonPct: 82.0,
            silicaFluxTons: (float) $run->silica_flux_tons,
            limestoneFluxTons: 4.5,
            targetTappingTemperatureCelsius: (float) $run->tapping_temperature_c
        );

        $energyReading = new SmelterEnergyReading(
            meteredElectricalPowerMwh: $overridePowerMwh ?? (float) ($run->power_consumption_mwh ?: 48.5),
            gridEmissionFactorTco2PerMwh: 0.7900,
            coalEmissionFactorTco2PerTon: 2.4200,
            dieselFuelLiters: 150.0
        );

        $result = $this->solver->solve($feed, $energyReading);

        // Verifikasi hasil algoritma
        $isValid = $this->verifier->verify($result, $feed);

        // Update model run
        $run->metal_output_tons = $result->metalTappedTons;
        $run->slag_output_tons = $result->slagTappedTons;
        $run->metal_recovery_rate_pct = $result->nickelRecoveryRatePct;
        $run->power_consumption_mwh = $result->actualPowerConsumedMwh;
        $run->save();

        // Update/create emisi
        MiningSmelterEnergyEmission::updateOrCreate(
            ['furnace_run_id' => $run->id],
            [
                'emission_code' => 'EMS-'.$run->run_code,
                'electricity_mwh' => $result->actualPowerConsumedMwh,
                'electricity_emission_factor_tco2_mwh' => 0.7900,
                'coal_burned_tons' => (float) $run->coal_reductant_tons,
                'coal_emission_factor_tco2_ton' => 2.4200,
                'diesel_fuel_liters' => 150.0,
                'scope1_emissions_tco2e' => $result->scope1EmissionsTco2e,
                'scope2_emissions_tco2e' => $result->scope2EmissionsTco2e,
                'total_emissions_tco2e' => $result->grossEmissionsTco2e,
                'intensity_tco2e_per_ton_metal' => $result->emissionIntensityTco2ePerTonMetal,
                'carbon_tax_threshold_exceeded' => $result->carbonTaxThresholdExceeded,
                'status' => $isValid ? 'VERIFIED' : 'FAILED_VALIDATION',
                'metadata' => [
                    'heat_balance' => $result->heatBalanceBreakdown,
                    'mass_balance' => $result->massBalanceSummary,
                    'verification_hash' => $result->verificationHash,
                ],
            ]
        );

        return $result;
    }
}
