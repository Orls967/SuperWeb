<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * BusinessEthicsAntiCorruptionService (Fase 340)
 *
 * Implements:
 *  - 340.2 Gifts & hospitality registry with threshold enforcement and pre-approval
 *  - 340.3 Intermediary agent payment due diligence & economic rationale verification
 *  - 340.4 Tests: Threshold enforcement; unapproved gifts rejected; intermediary payment needs rationale; ethics:audit clean
 *  - 340.5 Edge case: Detected bribery/collusion immediately flags independent investigation & freezes payment clearance
 *  - 340.6 Risk: Unreasonable intermediary fees prevented by requiring documented economic rationale
 */
class BusinessEthicsAntiCorruptionService
{
    /**
     * Register gift or hospitality with threshold pre-approval guard (340.2 & 340.4).
     */
    public function registerGiftHospitality(
        string $giftCode,
        string $employeeId,
        string $counterparty,
        float $valueUsd,
        bool $preApproved = false,
        float $thresholdUsd = 100.00
    ): object {
        $gCode = strtoupper($giftCode);

        // Core gate 340.4: If value > threshold ($100), pre-approval is strictly required
        if ($valueUsd > $thresholdUsd && ! $preApproved) {
            throw new InvalidArgumentException("Anti-corruption violation: Gift exceeding policy threshold (\${$thresholdUsd}) requires compliance pre-approval (340.4).");
        }

        $id = DB::table('corporate_gifts_hospitality_registries')->insertGetId([
            'gift_code' => $gCode,
            'employee_id' => strtoupper($employeeId),
            'counterparty_name' => $counterparty,
            'value_usd' => $valueUsd,
            'policy_threshold_usd' => $thresholdUsd,
            'pre_approved_by_compliance' => $preApproved,
            'gift_accepted_or_given' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('corporate_gifts_hospitality_registries')->find($id);
    }

    /**
     * Process intermediary agent fee payment with economic rationale and bribery investigation triggers (340.3, 340.4, 340.5 Edge Case).
     */
    public function processIntermediaryPayment(
        string $paymentCode,
        string $agentId,
        float $feeAmountUsd,
        bool $hasEconomicRationale,
        bool $briberySuspected = false
    ): object {
        $pCode = strtoupper($paymentCode);
        $aId = strtoupper($agentId);

        // Economic rationale gate 340.4 & 340.6
        if (! $hasEconomicRationale) {
            throw new InvalidArgumentException("Intermediary due diligence breach: Payment requires documented economic rationale and performance proof (340.4).");
        }

        // Edge case 340.5: Bribery/collusion suspicion flags independent investigation & blocks clearance
        $investigationOrdered = $briberySuspected;
        $paymentCleared = ! $briberySuspected;

        $id = DB::table('commercial_intermediary_payments')->insertGetId([
            'payment_code' => $pCode,
            'agent_id' => $aId,
            'fee_amount_usd' => $feeAmountUsd,
            'has_documented_economic_rationale' => $hasEconomicRationale,
            'bribery_collusion_flagged' => $briberySuspected,
            'independent_investigation_ordered' => $investigationOrdered,
            'payment_cleared' => $paymentCleared,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('commercial_intermediary_payments')->find($id);
    }

    /**
     * Business Ethics & Anti-Corruption Audit (`ethics:audit`) (340.4, 340.8).
     */
    public function audit(): array
    {
        // Discrepancy 1: Gifts over threshold without pre-approval
        $unapprovedGifts = DB::table('corporate_gifts_hospitality_registries')
            ->where('value_usd', '>', DB::raw('policy_threshold_usd'))
            ->where('pre_approved_by_compliance', false)
            ->count();

        // Discrepancy 2: Intermediary payments cleared despite bribery collusion flag
        $improperClearedPayments = DB::table('commercial_intermediary_payments')
            ->where('bribery_collusion_flagged', true)
            ->where('payment_cleared', true)
            ->count();

        // Discrepancy 3: Intermediary payments without economic rationale
        $irrationalPayments = DB::table('commercial_intermediary_payments')
            ->where('has_documented_economic_rationale', false)
            ->count();

        $discrepancies = $unapprovedGifts + $improperClearedPayments + $irrationalPayments;

        return [
            'status' => $discrepancies === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_gifts' => DB::table('corporate_gifts_hospitality_registries')->count(),
            'total_intermediary_payments' => DB::table('commercial_intermediary_payments')->count(),
            'discrepancy_count' => $discrepancies,
        ];
    }
}
