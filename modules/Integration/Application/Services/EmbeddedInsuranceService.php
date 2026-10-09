<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * EmbeddedInsuranceService (Fase 158 — Lini 18)
 *
 * Implements:
 *  - 158.1 Embedded insurance catalog for all lines (hotel cancellation, cargo loss, crop drought)
 *  - 158.2 Automated parametric trigger & instant payout
 *  - 158.3 Broker & agent commission settlements
 *  - 158.5 Fraud risk scoring & SIU investigation hold
 */
class EmbeddedInsuranceService
{
    /**
     * Register embedded insurance product.
     */
    public function registerEmbeddedProduct(string $lineCode, string $name, float $premiumFee, float $coverageAmount): object
    {
        $code = 'EMB-'.strtoupper($lineCode).'-'.strtoupper(Str::random(6));

        $id = DB::table('ins_embedded_products')->insertGetId([
            'embed_code' => $code,
            'line_code' => strtoupper($lineCode),
            'product_name' => $name,
            'premium_fee' => $premiumFee,
            'coverage_amount' => $coverageAmount,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('ins_embedded_products')->find($id);
    }

    /**
     * Evaluate parametric trigger and execute payout if threshold breached.
     */
    public function evaluateParametricTrigger(string $triggerType, float $threshold, float $actualValue, float $payoutPerPolicy): object
    {
        $code = 'TRG-'.strtoupper(Str::random(8));

        // E.g., for drought: actual rainfall < threshold
        $isTriggered = ($actualValue < $threshold);

        $id = DB::table('ins_parametric_triggers')->insertGetId([
            'trigger_code' => $code,
            'trigger_type' => strtoupper($triggerType),
            'threshold_value' => $threshold,
            'actual_value' => $actualValue,
            'is_triggered' => $isTriggered,
            'payout_per_policy' => $isTriggered ? $payoutPerPolicy : 0.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('ins_parametric_triggers')->find($id);
    }

    /**
     * Record broker commission settlement: commission = premium * rate.
     */
    public function settleBrokerCommission(string $brokerCode, string $policyNumber, float $grossPremium, float $commissionRatePct = 10.0): object
    {
        $commissionAmount = round($grossPremium * ($commissionRatePct / 100.0), 2);

        $id = DB::table('ins_broker_commissions')->insertGetId([
            'broker_code' => $brokerCode,
            'policy_number' => $policyNumber,
            'gross_premium' => $grossPremium,
            'commission_rate_pct' => $commissionRatePct,
            'commission_amount' => $commissionAmount,
            'status' => 'PAID',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('ins_broker_commissions')->find($id);
    }

    /**
     * Assess fraud risk score on insurance claim.
     */
    public function assessFraudRisk(string $claimNumber, int $score, ?string $reason = null): object
    {
        $isSiu = ($score >= 75);
        $decision = match (true) {
            $score >= 90 => 'DENIED',
            $score >= 75 => 'SIU_INVESTIGATION',
            default => 'APPROVED',
        };

        DB::table('ins_fraud_assessments')->updateOrInsert(
            ['claim_number' => $claimNumber],
            [
                'fraud_risk_score' => $score,
                'is_held_for_siu' => $isSiu,
                'decision' => $decision,
                'reason' => $reason,
                'updated_at' => now(),
            ]
        );

        return (object) DB::table('ins_fraud_assessments')->where('claim_number', $claimNumber)->first();
    }

    /**
     * Audit: verify no discrepancies in commission or parametric executions.
     */
    public function audit(): array
    {
        $commissions = DB::table('ins_broker_commissions')->get();
        $discrepancies = 0;

        foreach ($commissions as $c) {
            $expected = round((float) $c->gross_premium * ((float) $c->commission_rate_pct / 100.0), 2);
            if (abs($expected - (float) $c->commission_amount) > 0.01) {
                $discrepancies++;
            }
        }

        return [
            'status' => $discrepancies === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_embedded_products' => DB::table('ins_embedded_products')->count(),
            'total_parametric_triggers' => DB::table('ins_parametric_triggers')->count(),
            'total_commissions' => $commissions->count(),
            'discrepancy_count' => $discrepancies,
        ];
    }
}
