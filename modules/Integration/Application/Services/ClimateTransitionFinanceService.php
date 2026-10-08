<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * ClimateTransitionFinanceService (Fase 326)
 *
 * Implements:
 *  - 326.1 Internal carbon price (ICP) shadow cost modeling for CAPEX appraisal
 *  - 326.2 Sustainability-Linked Sukuk / Green Loans with covenant pricing adjustments
 *  - 326.4 Tests: Shadow carbon cost never posts to cash ledger without actual transaction; reproducible formula; esg:audit clean
 *  - 326.5 Edge case: Missed sustainability KPI strictly triggers contractual pricing step-up (+0.50%) without quiet renegotiation
 *  - 326.6 Risk: Greenwashing risk prevented by mandating verified evidence of direct abatement plan before issuance
 */
class ClimateTransitionFinanceService
{
    /**
     * Conduct CAPEX appraisal adjusted by internal carbon shadow price (326.1 & 326.4).
     */
    public function conductShadowCapexAppraisal(
        string $appraisalCode,
        string $siteCode,
        float $nominalCapexUsd,
        float $annualCarbonTco2e,
        float $shadowPriceUsdPerTon = 75.00
    ): object {
        $code = strtoupper($appraisalCode);

        // Shadow adjustment 326.1: Shadow carbon impact over 5-year appraisal window
        $totalShadowCarbonCost = round($annualCarbonTco2e * $shadowPriceUsdPerTon * 5.0, 2);
        $adjustedNpv = round($nominalCapexUsd + $totalShadowCarbonCost, 2);

        $id = DB::table('internal_carbon_shadow_capex_appraisals')->insertGetId([
            'appraisal_code' => $code,
            'site_code' => strtoupper($siteCode),
            'nominal_capex_usd' => $nominalCapexUsd,
            'annual_carbon_intensity_tco2e' => $annualCarbonTco2e,
            'internal_carbon_shadow_price_usd_per_ton' => $shadowPriceUsdPerTon,
            'shadow_adjusted_npv_usd' => $adjustedNpv,
            'posted_to_actual_cash_ledger' => false, // strictly false (326.4)
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('internal_carbon_shadow_capex_appraisals')->find($id);
    }

    /**
     * Issue sustainability-linked financing with anti-greenwashing evidence guard (326.2, 326.5 Edge Case, 326.6 Risk).
     */
    public function issueTransitionInstrument(
        string $instrumentCode,
        string $instrumentType,
        float $baseCouponRatePct,
        float $targetReductionPct,
        float $realizedReductionPct,
        bool $hasEvidencedAbatementPlan = true,
        float $stepUpPct = 0.50
    ): object {
        $code = strtoupper($instrumentCode);

        // Anti-greenwashing check 326.6: Must have verified abatement plan evidence
        if (! $hasEvidencedAbatementPlan) {
            throw new InvalidArgumentException("Greenwashing violation: Transition instrument issuance strictly requires verified evidence of direct carbon abatement plan (326.6).");
        }

        // Target evaluation & step-up logic 326.5 Edge Case
        $targetMet = ($realizedReductionPct >= $targetReductionPct);
        $effectiveCoupon = $targetMet
            ? $baseCouponRatePct
            : round($baseCouponRatePct + $stepUpPct, 2); // Automatic contractual step-up

        $id = DB::table('sustainability_linked_instruments')->insertGetId([
            'instrument_code' => $code,
            'instrument_type' => strtoupper($instrumentType),
            'base_coupon_rate_pct' => $baseCouponRatePct,
            'target_emissions_reduction_pct' => $targetReductionPct,
            'realized_emissions_reduction_pct' => $realizedReductionPct,
            'pricing_step_up_pct' => $stepUpPct,
            'effective_coupon_rate_pct' => $effectiveCoupon,
            'has_evidenced_abatement_plan' => true,
            'kpi_target_achieved' => $targetMet,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('sustainability_linked_instruments')->find($id);
    }

    /**
     * ESG Transition Finance Audit (`esg:audit`) (326.4, 326.8).
     */
    public function audit(): array
    {
        // Discrepancy 1: Shadow appraisal posted to cash ledger
        $improperCashPosts = DB::table('internal_carbon_shadow_capex_appraisals')
            ->where('posted_to_actual_cash_ledger', true)
            ->count();

        // Discrepancy 2: Instruments issued without verified abatement plan
        $greenwashedInstruments = DB::table('sustainability_linked_instruments')
            ->where('has_evidenced_abatement_plan', false)
            ->count();

        // Discrepancy 3: KPI missed but step-up penalty not applied
        $unpenalizedMisses = DB::table('sustainability_linked_instruments')
            ->where('kpi_target_achieved', false)
            ->whereRaw('effective_coupon_rate_pct <= base_coupon_rate_pct')
            ->count();

        $discrepancies = $improperCashPosts + $greenwashedInstruments + $unpenalizedMisses;

        return [
            'status' => $discrepancies === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_appraisals' => DB::table('internal_carbon_shadow_capex_appraisals')->count(),
            'total_instruments' => DB::table('sustainability_linked_instruments')->count(),
            'discrepancy_count' => $discrepancies,
        ];
    }
}
