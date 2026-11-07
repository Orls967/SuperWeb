<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Smelter;

final class SmelterPyrometallurgicalEnergyBalanceResult
{
    /**
     * @param  array<string, float>  $heatBalanceBreakdown
     * @param  array<string, float>  $massBalanceSummary
     */
    public function __construct(
        public readonly float $dryFeedTons,
        public readonly float $metalTappedTons,
        public readonly float $slagTappedTons,
        public readonly float $offGasDustTons,
        public readonly float $nickelInMetalTons,
        public readonly float $nickelRecoveryRatePct,
        public readonly float $ironInMetalTons,
        public readonly float $metalNiGradePct,
        public readonly float $slagBasicityRatio,
        public readonly float $theoreticalEnergyMwh,
        public readonly float $actualPowerConsumedMwh,
        public readonly float $specificEnergyConsumptionMwhPerTonMetal,
        public readonly float $thermalEfficiencyPct,
        public readonly float $scope1EmissionsTco2e,
        public readonly float $scope2EmissionsTco2e,
        public readonly float $grossEmissionsTco2e,
        public readonly float $emissionIntensityTco2ePerTonMetal,
        public readonly bool $carbonTaxThresholdExceeded,
        public readonly bool $isThermallyFeasible,
        public readonly array $heatBalanceBreakdown,
        public readonly array $massBalanceSummary,
        public readonly string $verificationHash,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'dry_feed_tons' => $this->dryFeedTons,
            'metal_tapped_tons' => $this->metalTappedTons,
            'slag_tapped_tons' => $this->slagTappedTons,
            'off_gas_dust_tons' => $this->offGasDustTons,
            'nickel_in_metal_tons' => $this->nickelInMetalTons,
            'nickel_recovery_rate_pct' => $this->nickelRecoveryRatePct,
            'iron_in_metal_tons' => $this->ironInMetalTons,
            'metal_ni_grade_pct' => $this->metalNiGradePct,
            'slag_basicity_ratio' => $this->slagBasicityRatio,
            'theoretical_energy_mwh' => $this->theoreticalEnergyMwh,
            'actual_power_consumed_mwh' => $this->actualPowerConsumedMwh,
            'specific_energy_consumption_mwh_ton' => $this->specificEnergyConsumptionMwhPerTonMetal,
            'thermal_efficiency_pct' => $this->thermalEfficiencyPct,
            'scope1_emissions_tco2e' => $this->scope1EmissionsTco2e,
            'scope2_emissions_tco2e' => $this->scope2EmissionsTco2e,
            'gross_emissions_tco2e' => $this->grossEmissionsTco2e,
            'total_emissions_tco2e' => $this->grossEmissionsTco2e,
            'emission_intensity_tco2e_per_ton_metal' => $this->emissionIntensityTco2ePerTonMetal,
            'carbon_tax_threshold_exceeded' => $this->carbonTaxThresholdExceeded,
            'is_thermally_feasible' => $this->isThermallyFeasible,
            'heat_balance_breakdown' => $this->heatBalanceBreakdown,
            'mass_balance_summary' => $this->massBalanceSummary,
            'verification_hash' => $this->verificationHash,
        ];
    }
}
