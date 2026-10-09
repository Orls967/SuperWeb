<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * CoalitionLoyaltyBreakageService (Fase 283)
 *
 * Implements:
 *  - 283.1 Cross-industry coalition loyalty (airlines, hotels, retail, telcos) with interchange fees & liability governance
 *  - 283.2 Conservative breakage revenue recognition with deviation reversals
 *  - 283.4 Sum of ledger liability reconciliation (points * valuation = ledger liability)
 *  - 283.5 Edge case: Point devaluation requires notice period & grandfathering existing balances (no retroactive loss)
 *  - 283.6 Coalition partner settlement defaults trigger reserve utilization & formal escalation
 *  - 283.7 Breakage forecasts updated periodically with actual vs forecast deviation adjustments
 */
class CoalitionLoyaltyBreakageService
{
    /**
     * Register coalition partner with interchange fee model (283.1).
     */
    public function registerPartner(
        string $partnerCode,
        string $sector,
        float $interchangeFeePct,
        float $defaultReserveUsd = 50000.0
    ): object {
        $code = strtoupper($partnerCode);

        $id = DB::table('coalition_loyalty_partners')->insertGetId([
            'partner_code' => $code,
            'industry_sector' => strtoupper($sector),
            'interchange_fee_rate_pct' => $interchangeFeePct,
            'outstanding_settlement_usd' => 0.0,
            'default_reserve_usd' => $defaultReserveUsd,
            'is_in_default_escalation' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('coalition_loyalty_partners')->find($id);
    }

    /**
     * Credit loyalty points and reconcile balance liability (283.1 & 283.4).
     */
    public function creditLoyaltyPoints(
        string $accountId,
        float $pointsToAdd,
        float $pointValuationUsd = 0.0100
    ): object {
        $acc = strtoupper($accountId);
        $record = DB::table('loyalty_liability_ledgers')->where('account_id', $acc)->first();

        $currentBal = $record ? (float) $record->points_balance : 0.0;
        $valuation = $record ? (float) $record->point_valuation_usd : $pointValuationUsd;
        $newBal = $currentBal + $pointsToAdd;
        $totalLiability = round($newBal * $valuation, 2);

        DB::table('loyalty_liability_ledgers')->updateOrInsert(
            ['account_id' => $acc],
            [
                'points_balance' => $newBal,
                'point_valuation_usd' => $valuation,
                'total_liability_usd' => $totalLiability,
                'is_grandfathered_rate' => $record ? (bool) $record->is_grandfathered_rate : false,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        return (object) DB::table('loyalty_liability_ledgers')->where('account_id', $acc)->first();
    }

    /**
     * Devalue points with grandfathering existing balances (283.5 Edge Case).
     */
    public function applyPointDevaluation(
        string $accountId,
        float $newDevaluedRateUsd,
        bool $grandfatherExistingBalance = true
    ): object {
        $acc = strtoupper($accountId);
        $record = DB::table('loyalty_liability_ledgers')->where('account_id', $acc)->first();
        if (! $record) {
            throw new InvalidArgumentException("Loyalty account '{$accountId}' not found.");
        }

        // Edge case 283.5: Grandfathering protects existing balances from retroactive devaluation
        $valuationToApply = $grandfatherExistingBalance ? (float) $record->point_valuation_usd : $newDevaluedRateUsd;
        $totalLiability = round((float) $record->points_balance * $valuationToApply, 2);

        DB::table('loyalty_liability_ledgers')
            ->where('account_id', $acc)
            ->update([
                'point_valuation_usd' => $valuationToApply,
                'total_liability_usd' => $totalLiability,
                'is_grandfathered_rate' => $grandfatherExistingBalance,
                'updated_at' => now(),
            ]);

        return (object) DB::table('loyalty_liability_ledgers')->where('account_id', $acc)->first();
    }

    /**
     * Handle coalition partner settlement default utilizing reserve (283.6 Edge Case).
     */
    public function handlePartnerSettlementDefault(string $partnerCode, float $unpaidAmountUsd): object
    {
        $code = strtoupper($partnerCode);
        $partner = DB::table('coalition_loyalty_partners')->where('partner_code', $code)->first();
        if (! $partner) {
            throw new InvalidArgumentException("Partner '{$partnerCode}' not found.");
        }

        $reserve = (float) $partner->default_reserve_usd;
        $remainingReserve = max(0.0, $reserve - $unpaidAmountUsd);

        DB::table('coalition_loyalty_partners')
            ->where('partner_code', $code)
            ->update([
                'outstanding_settlement_usd' => $unpaidAmountUsd,
                'default_reserve_usd' => $remainingReserve,
                'is_in_default_escalation' => true,
                'updated_at' => now(),
            ]);

        return (object) DB::table('coalition_loyalty_partners')->where('partner_code', $code)->first();
    }

    /**
     * Recognize conservative breakage revenue and adjust for deviations (283.2 & 283.7).
     */
    public function recognizeBreakage(
        string $recognitionCode,
        string $periodName,
        float $forecastRedemptionPct,
        float $breakageRevenueUsd,
        ?float $actualRedemptionPct = null
    ): object {
        $code = strtoupper($recognitionCode);

        // Deviation adjustment: if actual redemption exceeds forecast, reverse portion of breakage revenue (283.2)
        $reversal = 0.0;
        if ($actualRedemptionPct !== null && $actualRedemptionPct > $forecastRedemptionPct) {
            $diffPct = $actualRedemptionPct - $forecastRedemptionPct;
            $reversal = round($breakageRevenueUsd * ($diffPct / 100.0), 2);
        }

        $id = DB::table('loyalty_breakage_recognitions')->insertGetId([
            'recognition_code' => $code,
            'period_name' => $periodName,
            'forecast_redemption_rate_pct' => $forecastRedemptionPct,
            'actual_redemption_rate_pct' => $actualRedemptionPct,
            'breakage_revenue_recognized_usd' => $breakageRevenueUsd,
            'reversal_adjustment_usd' => $reversal,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('loyalty_breakage_recognitions')->find($id);
    }

    /**
     * Coalition Loyalty Platform Audit (`loyalty:audit`) (283.4, 283.8).
     */
    public function audit(): array
    {
        // Discrepancy 1: Ledger total liability not matching points * valuation
        $inconsistentLiabilities = DB::table('loyalty_liability_ledgers')
            ->whereRaw('abs(total_liability_usd - round(points_balance * point_valuation_usd, 2)) > 0.05')
            ->count();

        // Discrepancy 2: Partner defaults with 0 reserve and no escalation
        $unaddressedDefaults = DB::table('coalition_loyalty_partners')
            ->where('outstanding_settlement_usd', '>', 0)
            ->where('is_in_default_escalation', false)
            ->count();

        // Discrepancy 3: Breakage recognitions with higher actual redemptions without reversal
        $unreversedBreakages = DB::table('loyalty_breakage_recognitions')
            ->whereRaw('actual_redemption_rate_pct > forecast_redemption_rate_pct')
            ->where('reversal_adjustment_usd', '<=', 0)
            ->count();

        $discrepancies = $inconsistentLiabilities + $unaddressedDefaults + $unreversedBreakages;

        return [
            'status' => $discrepancies === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_partners' => DB::table('coalition_loyalty_partners')->count(),
            'total_accounts' => DB::table('loyalty_liability_ledgers')->count(),
            'total_breakage_records' => DB::table('loyalty_breakage_recognitions')->count(),
            'discrepancy_count' => $discrepancies,
        ];
    }
}
