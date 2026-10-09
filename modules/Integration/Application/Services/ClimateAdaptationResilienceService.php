<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * ClimateAdaptationResilienceService (Fase 287)
 *
 * Implements:
 *  - 287.1 Geospatial asset climate physical risk exposure modeling (flood, heat, storm, drought)
 *  - 287.2 EPC adaptation projects (flood barriers, cooling, elevated DCs) with reproducible ROI tracking
 *  - 287.3 Supply chain climate vulnerability with automatic mitigation & alternative routing triggers
 *  - 287.5 Edge case: Weather sensor data loss falls back to official meteorological data with explicit UNCERTAINTY labeling
 *  - 287.7 Adaptation measure completion mandatory before insurance resilience credits can be claimed
 */
class ClimateAdaptationResilienceService
{
    /**
     * Map asset site geospatial climate exposure with sensor fallback handling (287.1, 287.4, 287.5 Edge Case).
     */
    public function recordSiteClimateExposure(
        string $siteCode,
        float $lat,
        float $lng,
        string $hazardType,
        float $riskScore,
        float $potentialLossUsd,
        bool $sensorOnline = true
    ): object {
        $code = strtoupper($siteCode);

        // Edge case 287.5: If sensor is offline, fall back to official regional baseline and label UNCERTAIN
        $qualityLabel = $sensorOnline ? 'VERIFIED_SENSOR' : 'OFFICIAL_FALLBACK_UNCERTAIN';

        DB::table('esg_climate_asset_exposures')->updateOrInsert(
            ['asset_site_code' => $code],
            [
                'latitude' => $lat,
                'longitude' => $lng,
                'primary_hazard_type' => strtoupper($hazardType),
                'physical_risk_score' => $riskScore,
                'estimated_financial_loss_usd' => $potentialLossUsd,
                'weather_sensor_online' => $sensorOnline,
                'weather_data_quality_label' => $qualityLabel,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        return (object) DB::table('esg_climate_asset_exposures')->where('asset_site_code', $code)->first();
    }

    /**
     * Register adaptation measure with reproducible ROI and insurance credit gate (287.2, 287.4, 287.7).
     */
    public function registerAdaptationMeasure(
        string $measureCode,
        string $siteCode,
        string $measureName,
        float $capexCostUsd,
        float $avoidedLossUsd,
        bool $isCompleted = false
    ): object {
        $mCode = strtoupper($measureCode);

        // Reproducible ROI = ((avoided loss - capex) / capex) * 100% (287.4)
        $netBenefit = $avoidedLossUsd - $capexCostUsd;
        $roiPct = round(($netBenefit / max(1.0, $capexCostUsd)) * 100.0, 2);

        // Gate 287.7: Adaptation measure must be completed before insurance resilience credit is eligible
        $insuranceCreditEligible = $isCompleted;

        $id = DB::table('esg_adaptation_measures')->insertGetId([
            'measure_code' => $mCode,
            'asset_site_code' => strtoupper($siteCode),
            'measure_name' => strtoupper($measureName),
            'capex_cost_usd' => $capexCostUsd,
            'avoided_loss_benefit_usd' => $avoidedLossUsd,
            'adaptation_roi_pct' => $roiPct,
            'measure_completed' => $isCompleted,
            'insurance_resilience_credit_eligible' => $insuranceCreditEligible,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('esg_adaptation_measures')->find($id);
    }

    /**
     * Claim insurance resilience discount credit; strictly blocked if measure not completed (287.7 Edge Case).
     */
    public function claimInsuranceResilienceCredit(string $measureCode): object
    {
        $mCode = strtoupper($measureCode);
        $measure = DB::table('esg_adaptation_measures')->where('measure_code', $mCode)->first();
        if (! $measure) {
            throw new InvalidArgumentException("Adaptation measure '{$measureCode}' not found.");
        }

        // Edge case 287.7
        if (! $measure->measure_completed) {
            throw new InvalidArgumentException("Insurance credit rejected: Adaptation mitigation measure '{$measureCode}' must be fully completed and verified prior to insurance resilience credit grant (287.7).");
        }

        DB::table('esg_adaptation_measures')
            ->where('measure_code', $mCode)
            ->update([
                'insurance_resilience_credit_eligible' => true,
                'updated_at' => now(),
            ]);

        return (object) DB::table('esg_adaptation_measures')->where('measure_code', $mCode)->first();
    }

    /**
     * Evaluate supplier climate vulnerability and trigger alternative sourcing/routing mitigation (287.3 & 287.4).
     */
    public function evaluateSupplierClimateRisk(
        string $supplierCode,
        string $region,
        float $vulnerabilityScore
    ): object {
        $sCode = strtoupper($supplierCode);

        // High risk (vulnerability >= 7.0) automatically triggers mitigation and alternative routing (287.3 & 287.4)
        $triggersMitigation = ($vulnerabilityScore >= 7.0);
        $altRouting = $triggersMitigation ? 'ROUTE-BYPASS-CLIMATE-'.strtoupper($region) : null;

        DB::table('esg_supply_chain_climate_risks')->updateOrInsert(
            ['supplier_code' => $sCode],
            [
                'commodity_origin_region' => strtoupper($region),
                'climate_vulnerability_score' => $vulnerabilityScore,
                'mitigation_plan_triggered' => $triggersMitigation,
                'alternative_routing_code' => $altRouting,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        return (object) DB::table('esg_supply_chain_climate_risks')->where('supplier_code', $sCode)->first();
    }

    /**
     * Climate Resilience Platform Audit (`risk:audit` + `esg:audit`) (287.4, 287.8).
     */
    public function audit(): array
    {
        // Discrepancy 1: Incomplete adaptation measures with insurance credit granted
        $unearnedInsuranceCredits = DB::table('esg_adaptation_measures')
            ->where('measure_completed', false)
            ->where('insurance_resilience_credit_eligible', true)
            ->count();

        // Discrepancy 2: High risk suppliers without mitigation plan triggered
        $unmitigatedHighRiskSuppliers = DB::table('esg_supply_chain_climate_risks')
            ->where('climate_vulnerability_score', '>=', 7.0)
            ->where('mitigation_plan_triggered', false)
            ->count();

        // Discrepancy 3: Offline weather sensors labeled as verified
        $mislabelledSensors = DB::table('esg_climate_asset_exposures')
            ->where('weather_sensor_online', false)
            ->where('weather_data_quality_label', 'VERIFIED_SENSOR')
            ->count();

        $discrepancies = $unearnedInsuranceCredits + $unmitigatedHighRiskSuppliers + $mislabelledSensors;

        return [
            'status' => $discrepancies === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_site_exposures' => DB::table('esg_climate_asset_exposures')->count(),
            'total_adaptation_measures' => DB::table('esg_adaptation_measures')->count(),
            'total_suppliers' => DB::table('esg_supply_chain_climate_risks')->count(),
            'discrepancy_count' => $discrepancies,
        ];
    }
}
