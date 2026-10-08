<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * KamPartnershipRevenueService (Fase 247)
 *
 * Implements:
 *  - 247.1 Key Account Management (KAM) workspace for strategic cross-line enterprises
 *  - 247.2 Solution bundling engine across lines with internal margin settlement
 *  - 247.4 Partnership deal referral & revenue share payout calculations
 *  - 247.6 Edge case: Single-line cancellation in a multi-line bundle triggers recalculation rather than full contract void
 *  - 247.7 Renewal gap alert tracking (90, 60, 30 days prior to contract expiration)
 */
class KamPartnershipRevenueService
{
    /**
     * Create strategic key account profile (247.1).
     */
    public function createKeyAccount(
        string $accountCode,
        string $clientName,
        string $accountTier,
        string $leadAccountDirector
    ): object {
        $code = strtoupper($accountCode);

        $id = DB::table('kam_key_accounts')->insertGetId([
            'account_code' => $code,
            'client_name' => $clientName,
            'account_tier' => strtoupper($accountTier),
            'lead_account_director' => strtoupper($leadAccountDirector),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('kam_key_accounts')->find($id);
    }

    /**
     * Create multi-line solution bundle with internal margin settlement (247.2 & 247.5).
     */
    public function createMultiLineBundle(
        int $accountId,
        string $bundleName,
        array $lineComponents,
        string $contractEndDate
    ): object {
        $totalPrice = 0.0;
        $totalMargin = 0.0;

        foreach ($lineComponents as $comp) {
            $price = (float) $comp['price'];
            $cogs = (float) $comp['cogs'];
            $margin = round($price - $cogs, 2);

            $totalPrice += $price;
            $totalMargin += $margin;
        }

        $code = 'BNDL-'.strtoupper(Str::random(8));

        $bundleId = DB::table('kam_multi_line_bundles')->insertGetId([
            'bundle_code' => $code,
            'account_id' => $accountId,
            'bundle_name' => $bundleName,
            'total_contract_price_usd' => $totalPrice,
            'internal_margin_usd' => $totalMargin,
            'status' => 'ACTIVE',
            'contract_end_date' => $contractEndDate,
            'renewal_alert_days' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ($lineComponents as $comp) {
            $price = (float) $comp['price'];
            $cogs = (float) $comp['cogs'];
            $margin = round($price - $cogs, 2);

            DB::table('kam_bundle_line_components')->insert([
                'bundle_id' => $bundleId,
                'business_line' => strtoupper($comp['business_line']),
                'allocated_price_usd' => $price,
                'allocated_cogs_usd' => $cogs,
                'allocated_margin_usd' => $margin,
                'is_cancelled' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return (object) DB::table('kam_multi_line_bundles')->find($bundleId);
    }

    /**
     * Handle single line cancellation: recalculates remaining bundle rather than cancelling all (247.6 Edge Case).
     */
    public function handlePartialBundleCancellation(int $bundleId, string $cancelledLine): object
    {
        $bundle = DB::table('kam_multi_line_bundles')->find($bundleId);
        if (! $bundle) {
            throw new InvalidArgumentException("Bundle #{$bundleId} not found.");
        }

        $lineUpper = strtoupper($cancelledLine);

        // Mark the specific component as cancelled
        DB::table('kam_bundle_line_components')
            ->where('bundle_id', $bundleId)
            ->where('business_line', $lineUpper)
            ->update([
                'is_cancelled' => true,
                'updated_at' => now(),
            ]);

        // Recalculate remaining active components
        $activeComponents = DB::table('kam_bundle_line_components')
            ->where('bundle_id', $bundleId)
            ->where('is_cancelled', false)
            ->get();

        $newTotalPrice = (float) $activeComponents->sum('allocated_price_usd');
        $newTotalMargin = (float) $activeComponents->sum('allocated_margin_usd');

        DB::table('kam_multi_line_bundles')
            ->where('id', $bundleId)
            ->update([
                'total_contract_price_usd' => $newTotalPrice,
                'internal_margin_usd' => $newTotalMargin,
                'status' => 'RECALCULATED',
                'updated_at' => now(),
            ]);

        return (object) DB::table('kam_multi_line_bundles')->find($bundleId);
    }

    /**
     * Check renewal gap alerts (90, 60, 30 days) (247.7).
     */
    public function checkRenewalGapAlerts(int $bundleId): array
    {
        $bundle = DB::table('kam_multi_line_bundles')->find($bundleId);
        if (! $bundle) {
            throw new InvalidArgumentException("Bundle #{$bundleId} not found.");
        }

        $endDate = Carbon::parse($bundle->contract_end_date);
        $daysRemaining = (int) now()->diffInDays($endDate, false);

        $alertLevel = 0;
        if ($daysRemaining <= 30) {
            $alertLevel = 30;
        } elseif ($daysRemaining <= 60) {
            $alertLevel = 60;
        } elseif ($daysRemaining <= 90) {
            $alertLevel = 90;
        }

        if ($alertLevel > 0) {
            DB::table('kam_multi_line_bundles')
                ->where('id', $bundleId)
                ->update([
                    'renewal_alert_days' => $alertLevel,
                    'updated_at' => now(),
                ]);
        }

        return [
            'bundle_id' => $bundleId,
            'days_remaining' => $daysRemaining,
            'renewal_alert_level' => $alertLevel,
            'requires_follow_up' => $alertLevel > 0,
            'action_owner' => 'ACCOUNT_DIRECTOR_FOLLOW_UP',
        ];
    }

    /**
     * Calculate partnership referral revenue share (247.4 & 247.5).
     */
    public function calculatePartnerRevenueShare(
        string $partnerId,
        float $dealValueUsd,
        float $sharePct
    ): object {
        $payout = round($dealValueUsd * ($sharePct / 100.0), 2);
        $code = 'REF-'.strtoupper(Str::random(8));

        $id = DB::table('kam_partnership_referral_shares')->insertGetId([
            'referral_code' => $code,
            'partner_id' => strtoupper($partnerId),
            'deal_value_usd' => $dealValueUsd,
            'share_pct' => $sharePct,
            'calculated_payout_usd' => $payout,
            'payout_status' => 'PENDING',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('kam_partnership_referral_shares')->find($id);
    }

    /**
     * KAM & Partnership Platform Audit (`ptn:audit`) (247.5, 247.8).
     */
    public function audit(): array
    {
        // Discrepancy 1: Internal margin discrepancy between bundle and its active components
        $marginDiscrepancies = 0;
        $bundles = DB::table('kam_multi_line_bundles')->get();
        foreach ($bundles as $b) {
            $sumComponentMargins = (float) DB::table('kam_bundle_line_components')
                ->where('bundle_id', $b->id)
                ->where('is_cancelled', false)
                ->sum('allocated_margin_usd');

            if (abs($sumComponentMargins - (float) $b->internal_margin_usd) > 0.01) {
                $marginDiscrepancies++;
            }
        }

        // Discrepancy 2: Referral payout calculation formula breach
        $badPayouts = DB::table('kam_partnership_referral_shares')
            ->whereRaw('calculated_payout_usd != ROUND(deal_value_usd * (share_pct / 100.0), 2)')
            ->count();

        $discrepancies = $marginDiscrepancies + $badPayouts;

        return [
            'status' => $discrepancies === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_accounts' => DB::table('kam_key_accounts')->count(),
            'total_bundles' => DB::table('kam_multi_line_bundles')->count(),
            'total_referrals' => DB::table('kam_partnership_referral_shares')->count(),
            'discrepancy_count' => $discrepancies,
        ];
    }
}
