<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Integration\Application\Services\SubscriptionBillingRetentionService;
use Tests\TestCase;

/**
 * Fase 221 — Pelanggan: Subscription, Billing Lifecycle & Retention Tests
 *
 * Covers:
 *  (a) Subscription lifecycle (pause maintains limited benefit; cancel revokes all; reactivation restores)
 *  (b) Deterministic dunning: step 3 suspends service automatically; step > 3 escalates to collection
 *  (c) Retention evaluation: margin guard protection against over-discounting
 *  (d) Win-back cohort analysis and conversion tracking
 *  (e) Quality audit gate billing:audit clean
 */
class SubscriptionBillingRetentionTest extends TestCase
{
    use RefreshDatabase;

    protected SubscriptionBillingRetentionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(SubscriptionBillingRetentionService::class);
    }

    /**
     * (a) Subscription creation, pause vs cancel rule.
     */
    public function test_subscription_lifecycle_pause_vs_cancel(): void
    {
        $sub = $this->service->createSubscription('CUST-SUB-01', 'ISP', 'FIBER_100M', 450000);
        $this->assertSame('ACTIVE', $sub->status);
        $this->assertTrue((bool) $sub->service_active);
        $this->assertTrue((bool) $sub->benefits_active);

        // Pause: service stopped, but benefits preserved (221.7)
        $paused = $this->service->pauseSubscription($sub->subscription_code, 'Travelling abroad');
        $this->assertSame('PAUSED', $paused->status);
        $this->assertFalse((bool) $paused->service_active);
        $this->assertTrue((bool) $paused->benefits_active);

        // Reactivate: both restored
        $reactivated = $this->service->reactivateSubscription($sub->subscription_code);
        $this->assertSame('REACTIVATED', $reactivated->status);
        $this->assertTrue((bool) $reactivated->service_active);
        $this->assertTrue((bool) $reactivated->benefits_active);

        // Cancel: service stopped, benefits revoked (221.7)
        $cancelled = $this->service->cancelSubscription($sub->subscription_code, 'Competitor offer');
        $this->assertSame('CANCELLED', $cancelled->status);
        $this->assertFalse((bool) $cancelled->service_active);
        $this->assertFalse((bool) $cancelled->benefits_active);
    }

    /**
     * (b) Dunning lifecycle, automatic suspension and escalation to collection (edge case 221.6).
     */
    public function test_billing_dunning_and_automatic_suspension(): void
    {
        $sub = $this->service->createSubscription('CUST-SUB-02', 'CLOUD', 'PRO_VM', 1200000);
        $invoice = $this->service->generateInvoice($sub->subscription_code, 1200000);
        $this->assertSame('ISSUED', $invoice->status);
        $this->assertSame(0, $invoice->dunning_step);

        // Step 1: Reminder
        $d1 = $this->service->processDunningStep($invoice->invoice_code);
        $this->assertSame('DUNNING', $d1->status);
        $this->assertSame(1, $d1->dunning_step);

        // Step 2: Second Reminder
        $d2 = $this->service->processDunningStep($invoice->invoice_code);
        $this->assertSame('DUNNING', $d2->status);
        $this->assertSame(2, $d2->dunning_step);

        // Step 3: Grace period expired -> Automatic service suspension
        $d3 = $this->service->processDunningStep($invoice->invoice_code);
        $this->assertSame('SUSPENDED', $d3->status);
        $this->assertSame(3, $d3->dunning_step);

        $freshSub = DB::table('crm_subscriptions')->where('subscription_code', $sub->subscription_code)->first();
        $this->assertSame('SUSPENDED', $freshSub->status);
        $this->assertFalse((bool) $freshSub->service_active); // Automatic stop, no free billing

        // Step 4: Edge Case 221.6 - escalation to collection, not infinite retry
        $d4 = $this->service->processDunningStep($invoice->invoice_code);
        $this->assertSame('ESCALATED_COLLECTION', $d4->status);
        $this->assertSame(4, $d4->dunning_step);

        // Write-off invoice with approval
        $writtenOff = $this->service->writeOffInvoice($invoice->invoice_code, 'CFO_OFFICER');
        $this->assertSame('WRITTEN_OFF', $writtenOff->status);
        $this->assertSame('CFO_OFFICER', $writtenOff->write_off_approved_by);
    }

    /**
     * (c) Retention margin guard and human escalation.
     */
    public function test_retention_intelligence_and_margin_guard(): void
    {
        // Margin guard test: 95% discount with 15% min margin -> exceeds allowed (max 85%) -> Throws exception
        try {
            $this->service->evaluateRetention('CUST-SUB-03', 0.60, 95.0, 15.0);
            $this->fail('Expected exception for discount exceeding margin guard.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('violates margin guard', $e->getMessage());
        }

        // High churn risk score -> Escalate to human
        $playbook = $this->service->evaluateRetention('CUST-SUB-03', 0.90, 20.0, 15.0);
        $this->assertSame('ESCALATED_HUMAN', $playbook->status);

        // Prevented churn
        $this->service->markRetentionPrevented($playbook->id);
        $updated = DB::table('crm_retention_playbooks')->find($playbook->id);
        $this->assertSame('PREVENTED', $updated->status);
        $this->assertTrue((bool) $updated->prevented_churn);
    }

    /**
     * (d) Win-back cohort and conversion tracking.
     */
    public function test_winback_cohort_and_conversion(): void
    {
        $cohort = 'Q4_CHURN_RECOVERY';
        $this->service->enrollWinback($cohort, 'CUST-CHURN-1', 'WINBACK_25');
        $this->service->enrollWinback($cohort, 'CUST-CHURN-2', 'WINBACK_25');

        $this->service->convertWinback($cohort, 'CUST-CHURN-1');

        $stats = $this->service->getCohortStats($cohort);
        $this->assertSame(2, $stats['total']);
        $this->assertSame(1, $stats['converted']);
        $this->assertEquals(50.0, $stats['conversion_rate_pct']);
    }

    /**
     * (e) Audit status healthy with 0 discrepancies.
     */
    public function test_subscription_billing_audit(): void
    {
        $audit = $this->service->audit();
        $this->assertSame('HEALTHY', $audit['status']);
        $this->assertSame(0, $audit['discrepancy_count']);
    }
}
