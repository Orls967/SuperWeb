<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * CircularityWaterNatureService (Fase 230)
 *
 * Implements:
 *  - 230.1 Circularity mass balance accounting across 30 lines (recycled content & diversion)
 *  - 230.2 Water stewardship: site baselines, recycled/discharge tracking & compliance
 *  - 230.3 Nature-positive restoration portfolio linked to operational footprints
 *  - 230.4 Green procurement evaluation integrating sustainability criteria into tender awards
 *  - 230.6 Edge case: Water-stressed area sites prioritized with fast-tracked capex approval
 *  - 230.7 Anti-greenwashing: Green premium strictly restricted to third-party verified product lifecycle data
 */
class CircularityWaterNatureService
{
    /**
     * Record circularity mass balance enforcing physical mass conservation (230.1 & 230.5).
     */
    public function recordMassBalance(
        string $businessLine,
        float $inputMaterialTons,
        float $virginMaterialTons,
        float $recycledContentTons,
        float $wasteDivertedTons = 0,
        float $takebackTons = 0,
        bool $isVerified = false
    ): object {
        // Physical mass balance check: virgin + recycled must sum to input (within 0.05 tons tolerance)
        $componentSum = $virginMaterialTons + $recycledContentTons;
        if (abs($componentSum - $inputMaterialTons) > 0.05) {
            throw new \InvalidArgumentException(
                "Mass balance conservation failure: Virgin ({$virginMaterialTons}) + Recycled ({$recycledContentTons}) = {$componentSum} tons, which does not equal Total Input ({$inputMaterialTons}) tons."
            );
        }

        $recycledPct = ($inputMaterialTons > 0) ? round(($recycledContentTons / $inputMaterialTons) * 100, 2) : 0.0;
        $code = 'CMB-'.strtoupper(Str::random(8));

        // 230.7 Anti-greenwashing: Green premium eligible ONLY if verified
        $greenPremiumEligible = $isVerified;

        $id = DB::table('esg_circularity_mass_balances')->insertGetId([
            'balance_code' => $code,
            'business_line' => strtoupper($businessLine),
            'total_input_material_tons' => $inputMaterialTons,
            'virgin_material_tons' => $virginMaterialTons,
            'recycled_content_tons' => $recycledContentTons,
            'waste_diverted_tons' => $wasteDivertedTons,
            'product_takeback_tons' => $takebackTons,
            'recycled_content_pct' => $recycledPct,
            'is_verified' => $isVerified,
            'green_premium_eligible' => $greenPremiumEligible,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('esg_circularity_mass_balances')->find($id);
    }

    /**
     * Claim green premium with anti-greenwashing verification check (230.7).
     */
    public function claimGreenPremium(string $balanceCode): object
    {
        $balance = DB::table('esg_circularity_mass_balances')->where('balance_code', strtoupper($balanceCode))->first();
        if (! $balance) {
            throw new \InvalidArgumentException("Mass balance record {$balanceCode} not found.");
        }

        if (! $balance->is_verified) {
            throw new \InvalidArgumentException(
                "Anti-greenwashing violation: Cannot claim green premium on unverified product lifecycle data for {$balanceCode}."
            );
        }

        DB::table('esg_circularity_mass_balances')
            ->where('balance_code', strtoupper($balanceCode))
            ->update([
                'green_premium_eligible' => true,
                'updated_at' => now(),
            ]);

        return (object) DB::table('esg_circularity_mass_balances')->where('balance_code', strtoupper($balanceCode))->first();
    }

    /**
     * Register site water stewardship with water stress priority capex acceleration (230.2 & 230.6 Edge Case).
     */
    public function registerWaterSite(
        string $siteCode,
        string $businessLine,
        string $locationName,
        bool $isWaterStressedArea,
        float $baselineWithdrawalM3,
        float $currentWithdrawalM3,
        float $recycledWaterM3 = 0,
        float $dischargedWaterM3 = 0
    ): object {
        // Edge Case 230.6: Sites in water-stressed locations receive fast-track capex priority
        $capexFastTracked = $isWaterStressedArea;

        DB::table('esg_water_stewardship_sites')->updateOrInsert(
            ['site_code' => strtoupper($siteCode)],
            [
                'business_line' => strtoupper($businessLine),
                'location_name' => $locationName,
                'is_water_stressed_area' => $isWaterStressedArea,
                'baseline_withdrawal_m3' => $baselineWithdrawalM3,
                'current_withdrawal_m3' => $currentWithdrawalM3,
                'recycled_water_m3' => $recycledWaterM3,
                'discharged_water_m3' => $dischargedWaterM3,
                'capex_fast_tracked' => $capexFastTracked,
                'quality_compliant' => true,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        return (object) DB::table('esg_water_stewardship_sites')->where('site_code', strtoupper($siteCode))->first();
    }

    /**
     * Register nature-positive restoration project (230.3).
     */
    public function registerNaturePositiveProject(
        string $projectCode,
        string $entityCode,
        string $habitatType,
        float $restoredHectares,
        float $operationalFootprintHectares,
        bool $isVerified = false
    ): object {
        $netPositiveRatio = round($restoredHectares / max(0.1, $operationalFootprintHectares), 2);

        $id = DB::table('esg_nature_positive_projects')->insertGetId([
            'project_code' => strtoupper($projectCode),
            'entity_code' => strtoupper($entityCode),
            'habitat_type' => strtoupper($habitatType),
            'restored_hectares' => $restoredHectares,
            'operational_footprint_hectares' => $operationalFootprintHectares,
            'net_positive_ratio' => $netPositiveRatio,
            'verification_cycle_status' => $isVerified ? 'VERIFIED' : 'PENDING',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('esg_nature_positive_projects')->find($id);
    }

    /**
     * Evaluate green procurement tender (230.4 & 230.5).
     */
    public function evaluateGreenTender(
        string $tenderCode,
        string $vendorCode,
        float $commercialScore,
        float $greenCriteriaScore,
        bool $mandatoryPassed = true
    ): object {
        // Composite award score: 70% Commercial, 30% Green Sustainability criteria
        $compositeScore = round(($commercialScore * 0.70) + ($greenCriteriaScore * 0.30), 2);
        $awarded = $mandatoryPassed && ($compositeScore >= 75.0);

        $id = DB::table('esg_green_procurement_tenders')->insertGetId([
            'tender_code' => strtoupper($tenderCode),
            'vendor_code' => strtoupper($vendorCode),
            'commercial_score' => $commercialScore,
            'green_criteria_score' => $greenCriteriaScore,
            'mandatory_criteria_passed' => $mandatoryPassed,
            'composite_award_score' => $compositeScore,
            'awarded' => $awarded,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('esg_green_procurement_tenders')->find($id);
    }

    /**
     * Quality audit gate (`esg:audit`).
     */
    public function audit(): array
    {
        // Discrepancy 1: Unverified records claiming green premium
        $falseGreenPremiums = DB::table('esg_circularity_mass_balances')
            ->where('green_premium_eligible', true)
            ->where('is_verified', false)
            ->count();

        // Discrepancy 2: Mass balance failure where virgin + recycled != total
        $massBalanceFailures = DB::table('esg_circularity_mass_balances')
            ->whereRaw('ABS((virgin_material_tons + recycled_content_tons) - total_input_material_tons) > 0.05')
            ->count();

        // Discrepancy 3: Water-stressed sites without fast-tracked capex flag
        $unprioritizedWaterStressed = DB::table('esg_water_stewardship_sites')
            ->where('is_water_stressed_area', true)
            ->where('capex_fast_tracked', false)
            ->count();

        $discrepancies = $falseGreenPremiums + $massBalanceFailures + $unprioritizedWaterStressed;

        return [
            'status' => $discrepancies === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_mass_balances' => DB::table('esg_circularity_mass_balances')->count(),
            'total_water_sites' => DB::table('esg_water_stewardship_sites')->count(),
            'total_nature_projects' => DB::table('esg_nature_positive_projects')->count(),
            'total_green_tenders' => DB::table('esg_green_procurement_tenders')->count(),
            'discrepancy_count' => $discrepancies,
        ];
    }
}
