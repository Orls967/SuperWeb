<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * SupplierFinanceCollaborativePlanningService (Fase 261)
 *
 * Implements:
 *  - 261.1 Supplier portal: rolling 12-month forecast sharing, capacity confirmation & scorecards
 *  - 261.2 Supply Chain Finance (SCF) scale: dynamic discount early payment from investor pool (payout <= invoice)
 *  - 261.3 Collaborative quality: Statistical Process Control (SPC) data sharing
 *  - 261.4 Access gating: portal access automatically expires when supplier contract ends
 *  - 261.5 Edge case: Supplier forecast sharing refusal triggers tier downgrade rather than forced lockout
 *  - 261.6 Edge case: Insufficient investor liquidity pool executes pro-rata / queued policy (no silent rejection)
 *  - 261.7 Poor supplier feed data quality triggers structured improvement plan
 */
class SupplierFinanceCollaborativePlanningService
{
    /**
     * Register collaborative supplier profile (261.1 & 261.4).
     */
    public function registerSupplier(
        string $supplierCode,
        string $supplierName,
        string $partnershipTier = 'PREFERRED',
        string $contractExpiryDate = '2027-12-31',
        bool $isForecastSharingConsented = true
    ): object {
        $code = strtoupper($supplierCode);

        $id = DB::table('supplier_collaborative_profiles')->insertGetId([
            'supplier_code' => $code,
            'supplier_name' => $supplierName,
            'partnership_tier' => strtoupper($partnershipTier),
            'is_forecast_sharing_consented' => $isForecastSharingConsented,
            'contract_expiry_date' => $contractExpiryDate,
            'portal_access_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('supplier_collaborative_profiles')->find($id);
    }

    /**
     * Handle forecast sharing refusal: gracefully downgrades tier rather than forced coercion (261.5 Edge Case).
     */
    public function handleForecastSharingRefusal(string $supplierCode): object
    {
        $code = strtoupper($supplierCode);
        $sup = DB::table('supplier_collaborative_profiles')->where('supplier_code', $code)->first();
        if (! $sup) {
            throw new InvalidArgumentException("Supplier '{$supplierCode}' not found.");
        }

        // Downgrade to STANDARD tier gracefully
        DB::table('supplier_collaborative_profiles')
            ->where('supplier_code', $code)
            ->update([
                'is_forecast_sharing_consented' => false,
                'partnership_tier' => 'STANDARD',
                'updated_at' => now(),
            ]);

        return (object) DB::table('supplier_collaborative_profiles')->where('supplier_code', $code)->first();
    }

    /**
     * Verify contract expiry and revoke portal access if expired (261.4).
     */
    public function verifyContractExpiryAndGateAccess(string $supplierCode): object
    {
        $code = strtoupper($supplierCode);
        $sup = DB::table('supplier_collaborative_profiles')->where('supplier_code', $code)->first();
        if (! $sup) {
            throw new InvalidArgumentException("Supplier '{$supplierCode}' not found.");
        }

        $isExpired = Carbon::parse($sup->contract_expiry_date)->isPast();

        if ($isExpired) {
            DB::table('supplier_collaborative_profiles')
                ->where('supplier_code', $code)
                ->update([
                    'portal_access_active' => false,
                    'updated_at' => now(),
                ]);
        }

        return (object) DB::table('supplier_collaborative_profiles')->where('supplier_code', $code)->first();
    }

    /**
     * Share rolling forecast & confirm capacity (261.1 & 261.4).
     */
    public function shareRollingForecast(
        string $supplierCode,
        int $projectedDemandUnits,
        int $confirmedCapacityUnits,
        int $rollingMonths = 12
    ): object {
        $code = strtoupper($supplierCode);
        $sup = DB::table('supplier_collaborative_profiles')->where('supplier_code', $code)->first();

        if (! $sup || ! $sup->portal_access_active) {
            throw new InvalidArgumentException("Cannot share forecast: Supplier '{$supplierCode}' portal access is inactive or expired (261.4).");
        }

        $id = DB::table('supplier_forecast_shares')->insertGetId([
            'supplier_code' => $code,
            'rolling_months' => $rollingMonths,
            'projected_demand_units' => $projectedDemandUnits,
            'confirmed_capacity_units' => $confirmedCapacityUnits,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('supplier_forecast_shares')->find($id);
    }

    /**
     * Request Supply Chain Finance early payment with pro-rata pool allocation (261.2, 261.4, 261.6 Edge Case).
     */
    public function requestScfEarlyPayment(
        string $supplierCode,
        string $invoiceCode,
        float $invoiceFaceValueUsd,
        float $discountRatePct,
        float $investorPoolCapacityUsd
    ): object {
        $code = strtoupper($supplierCode);
        $invCode = strtoupper($invoiceCode);

        // Calculate early payment: net = face_value * (1 - (discount / 100))
        $requestedEarlyAmount = round($invoiceFaceValueUsd * (1.0 - ($discountRatePct / 100.0)), 2);

        // Strict validation: early payment <= invoice face value (261.4)
        if ($requestedEarlyAmount > $invoiceFaceValueUsd) {
            throw new InvalidArgumentException('Invalid SCF payout: Approved early payment cannot exceed invoice face value.');
        }

        $isProRata = false;
        $approvedAmount = $requestedEarlyAmount;
        $status = 'APPROVED';

        // Edge case 261.6: Insufficient investor pool -> pro-rata queued allocation (no silent rejection)
        if ($requestedEarlyAmount > $investorPoolCapacityUsd) {
            $isProRata = true;
            $approvedAmount = $investorPoolCapacityUsd;
            $status = 'QUEUED_PRO_RATA';
        }

        $ref = 'SCF-'.strtoupper(Str::random(8));

        $id = DB::table('supplier_scf_early_payments')->insertGetId([
            'payment_ref' => $ref,
            'supplier_code' => $code,
            'invoice_code' => $invCode,
            'invoice_face_value_usd' => $invoiceFaceValueUsd,
            'discount_rate_pct' => $discountRatePct,
            'requested_early_amount_usd' => $requestedEarlyAmount,
            'approved_payout_usd' => $approvedAmount,
            'investor_pool_capacity_usd' => $investorPoolCapacityUsd,
            'is_pro_rata_allocated' => $isProRata,
            'payout_status' => $status,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('supplier_scf_early_payments')->find($id);
    }

    /**
     * Record collaborative Statistical Process Control (SPC) quality feed (261.3 & 261.7).
     */
    public function recordSpcQualityFeed(
        string $supplierCode,
        string $batchCode,
        float $spcQualityScore,
        float $dataQualityScore
    ): object {
        $code = strtoupper($supplierCode);

        // Edge case 261.7: Poor feed data quality (< 70.0) triggers improvement plan
        $improvementRequired = ($dataQualityScore < 70.0);
        $feedCode = 'SPC-'.strtoupper(Str::random(8));

        $id = DB::table('supplier_collaborative_spc_feeds')->insertGetId([
            'feed_code' => $feedCode,
            'supplier_code' => $code,
            'sample_batch_code' => strtoupper($batchCode),
            'spc_quality_score' => $spcQualityScore,
            'data_quality_score' => $dataQualityScore,
            'improvement_plan_required' => $improvementRequired,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('supplier_collaborative_spc_feeds')->find($id);
    }

    /**
     * Supplier Ecosystem Platform Audit (`proc:audit`) (261.4, 261.8).
     */
    public function audit(): array
    {
        // Discrepancy 1: SCF early payment exceeding invoice face value
        $excessiveScfPayments = DB::table('supplier_scf_early_payments')
            ->whereRaw('approved_payout_usd > invoice_face_value_usd')
            ->count();

        // Discrepancy 2: Expired contracts where portal access is still active
        $nowDate = now()->toDateString();
        $unrevokedExpiredAccess = DB::table('supplier_collaborative_profiles')
            ->where('contract_expiry_date', '<', $nowDate)
            ->where('portal_access_active', true)
            ->count();

        // Discrepancy 3: Poor data feeds (< 70) lacking improvement plan flag
        $unaddressedBadFeeds = DB::table('supplier_collaborative_spc_feeds')
            ->where('data_quality_score', '<', 70.0)
            ->where('improvement_plan_required', false)
            ->count();

        $discrepancies = $excessiveScfPayments + $unrevokedExpiredAccess + $unaddressedBadFeeds;

        return [
            'status' => $discrepancies === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_suppliers' => DB::table('supplier_collaborative_profiles')->count(),
            'total_forecasts' => DB::table('supplier_forecast_shares')->count(),
            'total_scf_payments' => DB::table('supplier_scf_early_payments')->count(),
            'total_spc_feeds' => DB::table('supplier_collaborative_spc_feeds')->count(),
            'discrepancy_count' => $discrepancies,
        ];
    }
}
