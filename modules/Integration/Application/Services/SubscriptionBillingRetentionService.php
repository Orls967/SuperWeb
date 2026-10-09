<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * SubscriptionBillingRetentionService (Fase 221)
 *
 * Implements:
 *  - 221.1 Cross-line subscription engine (membership, plans, pause, cancel, reactivate)
 *  - 221.2 Billing lifecycle & deterministic dunning escalation with automatic suspension
 *  - 221.3 Retention intelligence, churn risk score & margin guard enforcement
 *  - 221.4 Win-back campaign & cohort conversion tracking
 *  - 221.6 Edge case: Dunning failure escalation to collection (prevents infinite retries)
 *  - 221.7 Pause vs cancel rule enforcement
 */
class SubscriptionBillingRetentionService
{
    /**
     * Create cross-line subscription.
     */
    public function createSubscription(
        string $goldenId,
        string $businessLine,
        string $planCode,
        float $price,
        bool $isTrial = false
    ): object {
        $subCode = 'SUB-'.strtoupper(Str::random(8));

        $id = DB::table('crm_subscriptions')->insertGetId([
            'subscription_code' => $subCode,
            'customer_golden_id' => strtoupper($goldenId),
            'business_line' => strtoupper($businessLine),
            'plan_code' => strtoupper($planCode),
            'price' => $price,
            'status' => $isTrial ? 'TRIAL' : 'ACTIVE',
            'service_active' => true,
            'benefits_active' => true,
            'started_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('crm_subscriptions')->find($id);
    }

    /**
     * Pause subscription: suspends service consumption but preserves limited benefits/profile.
     */
    public function pauseSubscription(string $subCode, string $reason): object
    {
        DB::table('crm_subscriptions')
            ->where('subscription_code', strtoupper($subCode))
            ->update([
                'status' => 'PAUSED',
                'service_active' => false,
                'benefits_active' => true, // 221.7 pause mempertahankan benefit terbatas
                'pause_reason' => $reason,
                'updated_at' => now(),
            ]);

        return (object) DB::table('crm_subscriptions')->where('subscription_code', strtoupper($subCode))->first();
    }

    /**
     * Cancel subscription: terminates service and revokes all benefits.
     */
    public function cancelSubscription(string $subCode, string $reason): object
    {
        DB::table('crm_subscriptions')
            ->where('subscription_code', strtoupper($subCode))
            ->update([
                'status' => 'CANCELLED',
                'service_active' => false,
                'benefits_active' => false, // 221.7 cancel menghapus semua benefit
                'cancellation_reason' => $reason,
                'updated_at' => now(),
            ]);

        return (object) DB::table('crm_subscriptions')->where('subscription_code', strtoupper($subCode))->first();
    }

    /**
     * Reactivate subscription.
     */
    public function reactivateSubscription(string $subCode): object
    {
        DB::table('crm_subscriptions')
            ->where('subscription_code', strtoupper($subCode))
            ->update([
                'status' => 'REACTIVATED',
                'service_active' => true,
                'benefits_active' => true,
                'pause_reason' => null,
                'cancellation_reason' => null,
                'updated_at' => now(),
            ]);

        return (object) DB::table('crm_subscriptions')->where('subscription_code', strtoupper($subCode))->first();
    }

    /**
     * Generate invoice for subscription.
     */
    public function generateInvoice(string $subCode, float $amount, ?string $dueAt = null): object
    {
        $sub = DB::table('crm_subscriptions')->where('subscription_code', strtoupper($subCode))->first();
        if (! $sub) {
            throw new \InvalidArgumentException("Subscription {$subCode} not found.");
        }

        $invCode = 'INV-'.strtoupper(Str::random(8));

        $id = DB::table('crm_subscription_invoices')->insertGetId([
            'invoice_code' => $invCode,
            'subscription_code' => $sub->subscription_code,
            'customer_golden_id' => $sub->customer_golden_id,
            'amount' => $amount,
            'status' => 'ISSUED',
            'dunning_step' => 0,
            'due_at' => $dueAt ? date('Y-m-d H:i:s', strtotime($dueAt)) : now()->addDays(7),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('crm_subscription_invoices')->find($id);
    }

    /**
     * Process deterministic dunning step.
     */
    public function processDunningStep(string $invoiceCode): object
    {
        $invoice = DB::table('crm_subscription_invoices')->where('invoice_code', strtoupper($invoiceCode))->first();
        if (! $invoice) {
            throw new \InvalidArgumentException("Invoice {$invoiceCode} not found.");
        }

        $nextStep = $invoice->dunning_step + 1;

        if ($nextStep <= 2) {
            // Step 1 or 2: reminder notices
            DB::table('crm_subscription_invoices')
                ->where('invoice_code', strtoupper($invoiceCode))
                ->update([
                    'dunning_step' => $nextStep,
                    'status' => 'DUNNING',
                    'updated_at' => now(),
                ]);
        } elseif ($nextStep === 3) {
            // Step 3: grace expiry -> automatic suspension (service halted)
            DB::table('crm_subscription_invoices')
                ->where('invoice_code', strtoupper($invoiceCode))
                ->update([
                    'dunning_step' => $nextStep,
                    'status' => 'SUSPENDED',
                    'updated_at' => now(),
                ]);

            DB::table('crm_subscriptions')
                ->where('subscription_code', $invoice->subscription_code)
                ->update([
                    'status' => 'SUSPENDED',
                    'service_active' => false,
                    'benefits_active' => false,
                    'updated_at' => now(),
                ]);
        } else {
            // Step > 3: 221.6 Edge case: escalation ke collection, bukan infinite retry diam-diam
            DB::table('crm_subscription_invoices')
                ->where('invoice_code', strtoupper($invoiceCode))
                ->update([
                    'dunning_step' => $nextStep,
                    'status' => 'ESCALATED_COLLECTION',
                    'updated_at' => now(),
                ]);
        }

        return (object) DB::table('crm_subscription_invoices')->where('invoice_code', strtoupper($invoiceCode))->first();
    }

    /**
     * Write-off invoice with formal approval.
     */
    public function writeOffInvoice(string $invoiceCode, string $approvedBy): object
    {
        DB::table('crm_subscription_invoices')
            ->where('invoice_code', strtoupper($invoiceCode))
            ->update([
                'status' => 'WRITTEN_OFF',
                'write_off_approved_by' => $approvedBy,
                'updated_at' => now(),
            ]);

        return (object) DB::table('crm_subscription_invoices')->where('invoice_code', strtoupper($invoiceCode))->first();
    }

    /**
     * Evaluate retention and propose playbook with margin guard validation.
     */
    public function evaluateRetention(
        string $goldenId,
        float $churnScore,
        float $offeredDiscountPct,
        float $marginGuardMinPct = 10.00
    ): object {
        // 221.3 & 221.5 Margin Guard check: offered discount must not erode minimum profit margin
        $maxAllowableDiscount = 100.00 - $marginGuardMinPct;
        if ($offeredDiscountPct > $maxAllowableDiscount) {
            throw new \InvalidArgumentException(
                "Retention offer rejected: Discount {$offeredDiscountPct}% violates margin guard (maximum allowable discount is {$maxAllowableDiscount}%)."
            );
        }

        $status = 'OPEN';
        if ($churnScore >= 0.85) {
            $status = 'ESCALATED_HUMAN';
        } elseif ($offeredDiscountPct > 0) {
            $status = 'OFFERED';
        }

        $id = DB::table('crm_retention_playbooks')->insertGetId([
            'customer_golden_id' => strtoupper($goldenId),
            'churn_risk_score' => $churnScore,
            'offered_discount_pct' => $offeredDiscountPct,
            'margin_guard_min_pct' => $marginGuardMinPct,
            'status' => $status,
            'prevented_churn' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('crm_retention_playbooks')->find($id);
    }

    /**
     * Mark retention successfully prevented churn.
     */
    public function markRetentionPrevented(int $playbookId): void
    {
        DB::table('crm_retention_playbooks')->where('id', $playbookId)->update([
            'status' => 'PREVENTED',
            'prevented_churn' => true,
            'updated_at' => now(),
        ]);
    }

    /**
     * Win-back campaign orchestration.
     */
    public function enrollWinback(string $cohortName, string $goldenId, string $offerCode): object
    {
        $id = DB::table('crm_winback_campaigns')->insertGetId([
            'cohort_name' => strtoupper($cohortName),
            'customer_golden_id' => strtoupper($goldenId),
            'special_offer_code' => strtoupper($offerCode),
            'converted' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('crm_winback_campaigns')->find($id);
    }

    /**
     * Convert winback candidate.
     */
    public function convertWinback(string $cohortName, string $goldenId): void
    {
        DB::table('crm_winback_campaigns')
            ->where('cohort_name', strtoupper($cohortName))
            ->where('customer_golden_id', strtoupper($goldenId))
            ->update([
                'converted' => true,
                'updated_at' => now(),
            ]);
    }

    /**
     * Get winback cohort metrics.
     */
    public function getCohortStats(string $cohortName): array
    {
        $total = DB::table('crm_winback_campaigns')
            ->where('cohort_name', strtoupper($cohortName))
            ->count();

        $converted = DB::table('crm_winback_campaigns')
            ->where('cohort_name', strtoupper($cohortName))
            ->where('converted', true)
            ->count();

        $conversionRate = $total > 0 ? round(($converted / $total) * 100, 2) : 0.0;

        return [
            'cohort' => strtoupper($cohortName),
            'total' => $total,
            'converted' => $converted,
            'conversion_rate_pct' => $conversionRate,
        ];
    }

    /**
     * Quality audit gate (`billing:audit`).
     */
    public function audit(): array
    {
        // Discrepancy 1: Suspended subscription must NOT have active service
        $illegalActiveSuspended = DB::table('crm_subscriptions')
            ->where('status', 'SUSPENDED')
            ->where('service_active', true)
            ->count();

        // Discrepancy 2: Invoices stuck in infinite retry (dunning_step > 3 and still status DUNNING)
        $infiniteRetryInvoices = DB::table('crm_subscription_invoices')
            ->where('dunning_step', '>', 3)
            ->where('status', 'DUNNING')
            ->count();

        $discrepancies = $illegalActiveSuspended + $infiniteRetryInvoices;

        return [
            'status' => $discrepancies === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_subscriptions' => DB::table('crm_subscriptions')->count(),
            'total_invoices' => DB::table('crm_subscription_invoices')->count(),
            'total_retention_playbooks' => DB::table('crm_retention_playbooks')->count(),
            'total_winback_enrolled' => DB::table('crm_winback_campaigns')->count(),
            'discrepancy_count' => $discrepancies,
        ];
    }
}
