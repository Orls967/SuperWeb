<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * ClimateDecarbonizationRoadmapService (Fase 229)
 *
 * Implements:
 *  - 229.1 Net-zero roadmap per business line & interim target tracking
 *  - 229.2 Energy transition portfolio (solar/wind/biomass/storage) with IRR & carbon abatement prioritization
 *  - 229.3 Internal carbon pricing (shadow pricing) incorporated in economic appraisals
 *  - 229.4 Physical & transition climate risk assessment linked to adaptation plans & insurance policies
 *  - 229.6 Edge case: Offset hierarchy policy forbidding carbon offsets from substituting mandatory direct abatement
 *  - 229.7 Scope 3 emission estimations with versioned emission factors explicitly labeled as estimates
 */
class ClimateDecarbonizationRoadmapService
{
    /**
     * Initialize decarbonization net-zero roadmap for a business line (229.1).
     */
    public function initiateRoadmap(
        string $businessLine,
        float $baselineEmissions,
        float $targetInterimEmissions,
        float $currentActualEmissions,
        float $minAbatementRatioPct = 80.00
    ): object {
        $code = 'RDM-'.strtoupper(Str::random(8));

        $id = DB::table('esg_decarbonization_roadmaps')->insertGetId([
            'roadmap_code' => $code,
            'business_line' => strtoupper($businessLine),
            'baseline_emissions_tco2e' => $baselineEmissions,
            'target_interim_emissions_tco2e' => $targetInterimEmissions,
            'current_actual_emissions_tco2e' => $currentActualEmissions,
            'direct_abatement_achieved_tco2e' => 0,
            'offset_applied_tco2e' => 0,
            'min_abatement_ratio_pct' => $minAbatementRatioPct,
            'status' => 'ON_TRACK',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('esg_decarbonization_roadmaps')->find($id);
    }

    /**
     * Record abatement and offset enforcing direct reduction priority (229.6 Edge Case).
     */
    public function recordAbatementAndOffset(
        string $roadmapCode,
        float $directAbatementTco2e,
        float $offsetAppliedTco2e
    ): object {
        $roadmap = DB::table('esg_decarbonization_roadmaps')->where('roadmap_code', strtoupper($roadmapCode))->first();
        if (! $roadmap) {
            throw new \InvalidArgumentException("Roadmap {$roadmapCode} not found.");
        }

        $totalReductions = $directAbatementTco2e + $offsetAppliedTco2e;

        // Edge Case 229.6: Offset cannot substitute direct abatement
        if ($totalReductions > 0 && $offsetAppliedTco2e > 0) {
            $directRatio = round(($directAbatementTco2e / $totalReductions) * 100, 2);
            if ($directRatio < (float) $roadmap->min_abatement_ratio_pct) {
                throw new \InvalidArgumentException(
                    "Carbon offset policy violation: Direct abatement ratio is {$directRatio}%, which violates the mandatory minimum threshold of {$roadmap->min_abatement_ratio_pct}%. Offsets cannot substitute priority direct reduction."
                );
            }
        }

        DB::table('esg_decarbonization_roadmaps')
            ->where('roadmap_code', strtoupper($roadmapCode))
            ->update([
                'direct_abatement_achieved_tco2e' => $directAbatementTco2e,
                'offset_applied_tco2e' => $offsetAppliedTco2e,
                'updated_at' => now(),
            ]);

        return (object) DB::table('esg_decarbonization_roadmaps')->where('roadmap_code', strtoupper($roadmapCode))->first();
    }

    /**
     * Evaluate energy transition project applying internal shadow carbon price (229.2 & 229.3).
     */
    public function evaluateEnergyProject(
        string $projectCode,
        string $businessLine,
        string $techType,
        float $capexRequired,
        float $annualSavings,
        float $expectedIrrPct,
        float $annualAbatementTco2e,
        float $shadowPriceUsd = 50.00
    ): object {
        // 229.3 Shadow carbon value calculation
        $shadowCarbonValue = $annualAbatementTco2e * $shadowPriceUsd;
        $adjustedEconomicValue = $annualSavings + $shadowCarbonValue;

        // Prioritization score combines financial IRR with shadow carbon benefit
        $prioritizationScore = round($expectedIrrPct + ($shadowCarbonValue / max(1, $capexRequired) * 100), 2);

        $id = DB::table('esg_energy_transition_projects')->insertGetId([
            'project_code' => strtoupper($projectCode),
            'business_line' => strtoupper($businessLine),
            'tech_type' => strtoupper($techType),
            'capex_required' => $capexRequired,
            'annual_savings' => $annualSavings,
            'expected_irr_pct' => $expectedIrrPct,
            'annual_carbon_abatement_tco2e' => $annualAbatementTco2e,
            'internal_shadow_carbon_price_usd' => $shadowPriceUsd,
            'shadow_carbon_value' => $shadowCarbonValue,
            'adjusted_economic_value' => $adjustedEconomicValue,
            'prioritization_score' => $prioritizationScore,
            'funding_status' => 'APPROVED',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('esg_energy_transition_projects')->find($id);
    }

    /**
     * Register physical & transition climate asset risk with insurance linkage (229.4).
     */
    public function registerClimateAssetRisk(
        string $assetCode,
        string $businessLine,
        string $locationName,
        string $physicalRisk,
        string $transitionRisk,
        string $severity,
        string $adaptationPlan,
        ?string $linkedInsurancePolicyId = null
    ): object {
        // High or critical severity assets must maintain linked insurance alignment (229.4 & 229.5)
        if (in_array(strtoupper($severity), ['HIGH', 'CRITICAL']) && empty($linkedInsurancePolicyId)) {
            throw new \InvalidArgumentException(
                "Climate risk governance violation: High or critical severity climate asset {$assetCode} requires linked insurance policy alignment."
            );
        }

        $id = DB::table('esg_climate_asset_risks')->insertGetId([
            'asset_code' => strtoupper($assetCode),
            'business_line' => strtoupper($businessLine),
            'location_name' => $locationName,
            'physical_risk_type' => strtoupper($physicalRisk),
            'transition_risk_type' => strtoupper($transitionRisk),
            'risk_severity' => strtoupper($severity),
            'adaptation_plan' => $adaptationPlan,
            'linked_insurance_policy_id' => $linkedInsurancePolicyId ? strtoupper($linkedInsurancePolicyId) : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('esg_climate_asset_risks')->find($id);
    }

    /**
     * Record Scope 3 emission estimate enforcing estimation labeling (229.7).
     */
    public function recordScope3Estimate(
        string $businessLine,
        string $categoryName,
        float $estimatedTco2e,
        string $emissionFactorVersion,
        bool $claimAsMeasured = false
    ): object {
        // 229.7 Scope 3 estimasi: angka estimasi dilabeli, bukan diklaim terukur
        if ($claimAsMeasured) {
            throw new \InvalidArgumentException(
                'Scope 3 disclosure violation: Modelled Scope 3 emission estimates cannot be claimed as measured without empirical primary metering.'
            );
        }

        $code = 'SC3-'.strtoupper(Str::random(8));

        $id = DB::table('esg_scope3_emission_estimates')->insertGetId([
            'estimate_code' => $code,
            'business_line' => strtoupper($businessLine),
            'category_name' => $categoryName,
            'estimated_tco2e' => $estimatedTco2e,
            'emission_factor_version' => $emissionFactorVersion,
            'is_labeled_as_estimate' => true,
            'claimed_as_measured' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('esg_scope3_emission_estimates')->find($id);
    }

    /**
     * Quality audit gate (`esg:audit`).
     */
    public function audit(): array
    {
        // Discrepancy 1: Scope 3 estimates falsely claimed as measured
        $falseScope3Claims = DB::table('esg_scope3_emission_estimates')
            ->where('claimed_as_measured', true)
            ->count();

        // Discrepancy 2: High or critical severity risks lacking insurance policy
        $uninsuredHighRisks = DB::table('esg_climate_asset_risks')
            ->whereIn('risk_severity', ['HIGH', 'CRITICAL'])
            ->whereNull('linked_insurance_policy_id')
            ->count();

        // Discrepancy 3: Roadmaps with offsets violating direct abatement minimum ratio
        $roadmaps = DB::table('esg_decarbonization_roadmaps')
            ->where('offset_applied_tco2e', '>', 0)
            ->get();

        $offsetViolations = 0;
        foreach ($roadmaps as $r) {
            $total = (float) $r->direct_abatement_achieved_tco2e + (float) $r->offset_applied_tco2e;
            if ($total > 0) {
                $ratio = ((float) $r->direct_abatement_achieved_tco2e / $total) * 100.0;
                if ($ratio < (float) $r->min_abatement_ratio_pct) {
                    $offsetViolations++;
                }
            }
        }

        $discrepancies = $falseScope3Claims + $uninsuredHighRisks + $offsetViolations;

        return [
            'status' => $discrepancies === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_roadmaps' => DB::table('esg_decarbonization_roadmaps')->count(),
            'total_transition_projects' => DB::table('esg_energy_transition_projects')->count(),
            'total_climate_asset_risks' => DB::table('esg_climate_asset_risks')->count(),
            'discrepancy_count' => $discrepancies,
        ];
    }
}
