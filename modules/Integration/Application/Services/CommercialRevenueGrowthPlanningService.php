<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * CommercialRevenueGrowthPlanningService (Fase 305)
 *
 * Implements:
 *  - 305.1 Revenue growth management & margin guardrails
 *  - 305.2 Trade promo effectiveness & incremental lift calculation
 *  - 305.3 & 305.6 Value of Information (VOI) override policy based on Finance-provided cost
 *  - 305.4 Tests: Lift method documented, deterministic promo ROI, override policy enforced, pricing:audit clean
 *  - 305.5 Edge case: Promotions eroding net margins below minimum guardrail are immediately halted
 */
class CommercialRevenueGrowthPlanningService
{
    /**
     * Evaluate trade promotion performance with minimum margin guardrail (305.1, 305.2, 305.4, 305.5 Edge Case).
     */
    public function evaluatePromotion(
        string $promoCode,
        string $productLine,
        float $baselineSalesUsd,
        float $promoSalesUsd,
        float $netMarginPct,
        float $minMarginGuardrailPct = 15.00
    ): object {
        $pCode = strtoupper($promoCode);

        // Incremental lift 305.2
        $liftPct = $baselineSalesUsd > 0
            ? round((($promoSalesUsd - $baselineSalesUsd) / $baselineSalesUsd) * 100.0, 2)
            : 0.00;

        // ROI calculation 305.2
        $promoRoiPct = round($liftPct * 1.25, 2);

        // Edge case 305.5: Promo eroding net margin below threshold is immediately halted
        $isHalted = ($netMarginPct < $minMarginGuardrailPct);

        $id = DB::table('commercial_promotions')->insertGetId([
            'promo_code' => $pCode,
            'product_line' => strtoupper($productLine),
            'baseline_sales_usd' => $baselineSalesUsd,
            'promotional_sales_usd' => $promoSalesUsd,
            'incremental_lift_pct' => $liftPct,
            'min_margin_guardrail_pct' => $minMarginGuardrailPct,
            'net_realized_margin_pct' => $netMarginPct,
            'is_halted_by_margin_guard' => $isHalted,
            'promo_roi_pct' => $promoRoiPct,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($isHalted) {
            throw new InvalidArgumentException("Margin guardrail triggered: Promo '{$promoCode}' realized margin ({$netMarginPct}%) falls below minimum threshold ({$minMarginGuardrailPct}%); promo halted (305.5).");
        }

        return (object) DB::table('commercial_promotions')->find($id);
    }

    /**
     * Authorize human forecast override using Value of Information (VOI) (305.3, 305.4, 305.6).
     */
    public function evaluateForecastOverride(
        string $overrideCode,
        string $plannerId,
        float $projectedErrorSavingsUsd,
        float $overrideFinanceCostUsd
    ): object {
        $oCode = strtoupper($overrideCode);

        // Risk constraint 305.6: Finance cost must be realistic and strictly non-negative
        if ($overrideFinanceCostUsd < 0.0) {
            throw new InvalidArgumentException("Cost validation error: Override cost must be positive number verified from Finance (305.6).");
        }

        // Net VOI = projected savings - override cost (305.3)
        $netVoi = round($projectedErrorSavingsUsd - $overrideFinanceCostUsd, 2);
        $isAuthorized = ($netVoi > 0.00); // 305.3: Only authorized if net VOI is positive

        $id = DB::table('commercial_forecast_overrides')->insertGetId([
            'override_code' => $oCode,
            'planner_id' => strtoupper($plannerId),
            'projected_error_reduction_savings_usd' => $projectedErrorSavingsUsd,
            'override_cost_finance_usd' => $overrideFinanceCostUsd,
            'value_of_information_net_usd' => $netVoi,
            'is_override_authorized' => $isAuthorized,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if (! $isAuthorized) {
            throw new InvalidArgumentException("Forecast override rejected: Negative or zero Value of Information (\${$netVoi}) does not justify intervention cost (305.3).");
        }

        return (object) DB::table('commercial_forecast_overrides')->find($id);
    }

    /**
     * Commercial Pricing Platform Audit (`pricing:audit`) (305.4, 305.8).
     */
    public function audit(): array
    {
        // Discrepancy 1: Promos below margin guardrail not marked halted
        $unhaltedBreaches = DB::table('commercial_promotions')
            ->whereRaw('net_realized_margin_pct < min_margin_guardrail_pct')
            ->where('is_halted_by_margin_guard', false)
            ->count();

        // Discrepancy 2: Overrides authorized with negative VOI
        $improperOverrides = DB::table('commercial_forecast_overrides')
            ->where('is_override_authorized', true)
            ->where('value_of_information_net_usd', '<=', 0.0)
            ->count();

        $discrepancies = $unhaltedBreaches + $improperOverrides;

        return [
            'status' => $discrepancies === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_promotions' => DB::table('commercial_promotions')->count(),
            'total_forecast_overrides' => DB::table('commercial_forecast_overrides')->count(),
            'discrepancy_count' => $discrepancies,
        ];
    }
}
