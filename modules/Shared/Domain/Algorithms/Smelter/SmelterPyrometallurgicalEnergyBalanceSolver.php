<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Smelter;

final class SmelterPyrometallurgicalEnergyBalanceSolver
{
    private const INDONESIA_CARBON_TAX_THRESHOLD_TON = 2.50; // Ton CO2e per ton NPI

    public function solve(
        SmelterFeedParameters $feed,
        SmelterEnergyReading $energy,
        float $targetRecoveryPct = 92.5
    ): SmelterPyrometallurgicalEnergyBalanceResult {
        $dryFeed = $feed->dryOreFeedTons();
        $containedNi = $feed->containedNickelTons();
        $containedFe = $feed->containedIronTons();

        // Nickel and iron recovery to crude metal (NPI)
        $actualRecovery = min(max($targetRecoveryPct, 80.0), 98.0);
        $nickelInMetal = $containedNi * ($actualRecovery / 100.0);

        // In pyrometallurgical reduction of saprolite, partial iron reduction occurs
        // Typically Fe reduction ratio is 0.55 to 0.70 of total Fe
        $feReductionRatio = 0.62;
        $ironInMetal = $containedFe * $feReductionRatio;

        // Minor elements in metal: C, Si, P, S (~2.5% of total metal weight)
        $minorElementsPct = 2.5;
        $metalBaseTons = $nickelInMetal + $ironInMetal;
        $metalTappedTons = $metalBaseTons / (1.0 - ($minorElementsPct / 100.0));
        $metalNiGradePct = $metalTappedTons > 0.0 ? ($nickelInMetal / $metalTappedTons) * 100.0 : 0.0;

        // Slag generation from unreduced Fe, SiO2, MgO, Al2O3, plus flux
        $unreducedFeTons = $containedFe - $ironInMetal;
        $feOInSlag = $unreducedFeTons * (71.85 / 55.85); // Fe to FeO conversion
        $sio2InSlag = ($dryFeed * ($feed->sio2GradePct / 100.0)) + $feed->silicaFluxTons;
        $mgoInSlag = $dryFeed * ($feed->mgoGradePct / 100.0);
        $al2o3InSlag = $dryFeed * ($feed->al2o3GradePct / 100.0);
        $caoInSlag = $feed->limestoneFluxTons * 0.56; // CaCO3 calcination to CaO

        $slagTappedTons = $feOInSlag + $sio2InSlag + $mgoInSlag + $al2o3InSlag + $caoInSlag;
        $slagBasicity = $sio2InSlag > 0.0 ? ($mgoInSlag + $caoInSlag) / $sio2InSlag : 1.0;

        // Mass balance conservation check: Off-gas and dust
        $totalInputMass = $dryFeed + $feed->coalReductantTons + $feed->silicaFluxTons + $feed->limestoneFluxTons;
        $solidLiquidProducts = $metalTappedTons + $slagTappedTons;
        $offGasDustTons = max(0.0, $totalInputMass - $solidLiquidProducts);

        // Theoretical Enthalpy Requirements (MWh)
        // 1. Reduction of NiO and FeO: ~1.85 GJ/t feed -> 0.514 MWh/t feed
        $reductionEnthalpyMwh = $dryFeed * 0.485;
        // 2. Sensible heat of liquid metal at 1520°C: ~1.25 GJ/t metal -> 0.347 MWh/t metal
        $metalSensibleHeatMwh = $metalTappedTons * 0.360;
        // 3. Sensible heat of liquid slag at 1550°C: ~1.80 GJ/t slag -> 0.500 MWh/t slag
        $slagSensibleHeatMwh = $slagTappedTons * 0.510;
        // 4. Furnace wall and roof thermal radiation losses
        $radiationLossesMwh = $energy->furnaceSurfaceHeatLossMwh;

        $theoreticalEnergyMwh = $reductionEnthalpyMwh + $metalSensibleHeatMwh + $slagSensibleHeatMwh + $radiationLossesMwh;

        // Actual electrical power consumed
        $actualPowerConsumedMwh = max($energy->meteredElectricalPowerMwh, $theoreticalEnergyMwh * 1.08);
        $specificEnergyMwhPerTonMetal = $metalTappedTons > 0.0 ? $actualPowerConsumedMwh / $metalTappedTons : 0.0;
        $thermalEfficiencyPct = $actualPowerConsumedMwh > 0.0 ? ($theoreticalEnergyMwh / $actualPowerConsumedMwh) * 100.0 : 0.0;

        // Emissions Calculation:
        // Scope 1: Direct emissions from coal reductant combustion C + O2 -> CO2 + diesel
        $coalEmissionsTco2e = $feed->coalReductantTons * $energy->coalEmissionFactorTco2PerTon;
        $dieselEmissionsTco2e = ($energy->dieselFuelLiters / 1000.0) * $energy->dieselEmissionFactorTco2PerKl;
        $scope1Emissions = round($coalEmissionsTco2e + $dieselEmissionsTco2e, 3);

        // Scope 2: Indirect emissions from grid electricity
        $scope2Emissions = round($actualPowerConsumedMwh * $energy->gridEmissionFactorTco2PerMwh, 3);
        $totalEmissions = round($scope1Emissions + $scope2Emissions, 3);
        $intensityPerTonMetal = $metalTappedTons > 0.0 ? round($totalEmissions / $metalTappedTons, 4) : 0.0;

        $carbonTaxExceeded = $intensityPerTonMetal > self::INDONESIA_CARBON_TAX_THRESHOLD_TON;
        $isThermallyFeasible = $thermalEfficiencyPct >= 40.0 && $thermalEfficiencyPct <= 96.0;

        $heatBalanceBreakdown = [
            'reduction_enthalpy_mwh' => round($reductionEnthalpyMwh, 3),
            'metal_sensible_heat_mwh' => round($metalSensibleHeatMwh, 3),
            'slag_sensible_heat_mwh' => round($slagSensibleHeatMwh, 3),
            'radiation_losses_mwh' => round($radiationLossesMwh, 3),
            'electrical_losses_mwh' => round($actualPowerConsumedMwh - $theoreticalEnergyMwh, 3),
        ];

        $massBalanceSummary = [
            'ore_dry_input_tons' => round($dryFeed, 2),
            'coal_reductant_input_tons' => round($feed->coalReductantTons, 2),
            'flux_input_tons' => round($feed->silicaFluxTons + $feed->limestoneFluxTons, 2),
            'metal_tapped_output_tons' => round($metalTappedTons, 2),
            'slag_tapped_output_tons' => round($slagTappedTons, 2),
            'off_gas_dust_output_tons' => round($offGasDustTons, 2),
        ];

        $verificationHash = hash('sha256', sprintf(
            '%.3f:%.3f:%.3f:%.3f:%.2f',
            round($metalTappedTons, 3),
            round($slagTappedTons, 3),
            round($actualPowerConsumedMwh, 3),
            $totalEmissions,
            round($thermalEfficiencyPct, 2)
        ));

        return new SmelterPyrometallurgicalEnergyBalanceResult(
            dryFeedTons: round($dryFeed, 3),
            metalTappedTons: round($metalTappedTons, 3),
            slagTappedTons: round($slagTappedTons, 3),
            offGasDustTons: round($offGasDustTons, 3),
            nickelInMetalTons: round($nickelInMetal, 3),
            nickelRecoveryRatePct: round($actualRecovery, 2),
            ironInMetalTons: round($ironInMetal, 3),
            metalNiGradePct: round($metalNiGradePct, 3),
            slagBasicityRatio: round($slagBasicity, 3),
            theoreticalEnergyMwh: round($theoreticalEnergyMwh, 3),
            actualPowerConsumedMwh: round($actualPowerConsumedMwh, 3),
            specificEnergyConsumptionMwhPerTonMetal: round($specificEnergyMwhPerTonMetal, 3),
            thermalEfficiencyPct: round($thermalEfficiencyPct, 2),
            scope1EmissionsTco2e: $scope1Emissions,
            scope2EmissionsTco2e: $scope2Emissions,
            grossEmissionsTco2e: $totalEmissions,
            emissionIntensityTco2ePerTonMetal: $intensityPerTonMetal,
            carbonTaxThresholdExceeded: $carbonTaxExceeded,
            isThermallyFeasible: $isThermallyFeasible,
            heatBalanceBreakdown: $heatBalanceBreakdown,
            massBalanceSummary: $massBalanceSummary,
            verificationHash: $verificationHash
        );
    }
}
