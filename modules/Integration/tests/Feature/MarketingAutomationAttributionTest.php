<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Integration\Application\Services\MarketingAutomationAttributionService;
use Tests\TestCase;

/**
 * Fase 222 — Pelanggan: Marketing Automation & Attribution Tests
 *
 * Covers:
 *  (a) Global frequency capping across channels (prevents spamming across Push/Email/In-App)
 *  (b) Opt-out and fatigue suppression linked to service desk
 *  (c) Campaign promotion budget enforcement and hard stop
 *  (d) Deterministic attribution models where sum of weights is strictly 100%
 *  (e) Quality audit marketing:audit clean with 0 discrepancies
 */
class MarketingAutomationAttributionTest extends TestCase
{
    use RefreshDatabase;

    protected MarketingAutomationAttributionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(MarketingAutomationAttributionService::class);
    }

    /**
     * (a) Edge Case 222.6: Global frequency cap across channels per subject.
     */
    public function test_global_cross_channel_frequency_cap(): void
    {
        $this->service->createSegment('SEG-VIP', 'High Spender', 'RFM', 80, 100);
        $this->service->createCampaign('CAMP-SUMMER', 'Summer Sale', 'SEG-VIP', 500000, 1000000);

        // Configure subject with global daily cap of 2 messages
        $this->service->configureCustomerProfile('CUST-MKT-01', 2, false);

        // 1st Dispatch via EMAIL -> SENT
        $d1 = $this->service->dispatchMessage('CAMP-SUMMER', 'CUST-MKT-01', 'EMAIL', 10.0);
        $this->assertSame('SENT', $d1->status);

        // 2nd Dispatch via PUSH -> SENT
        $d2 = $this->service->dispatchMessage('CAMP-SUMMER', 'CUST-MKT-01', 'PUSH', 10.0);
        $this->assertSame('SENT', $d2->status);

        // 3rd Dispatch via IN_APP -> Exceeds global cap of 2 -> SUPPRESSED_CAP
        $d3 = $this->service->dispatchMessage('CAMP-SUMMER', 'CUST-MKT-01', 'IN_APP', 10.0);
        $this->assertSame('SUPPRESSED_CAP', $d3->status);
    }

    /**
     * (b) Opt-out and fatigue suppression (222.2 & 222.7).
     */
    public function test_opt_out_and_fatigue_suppression(): void
    {
        $this->service->createCampaign('CAMP-AUTUMN', 'Autumn Blast', 'SEG-VIP', 200000, 500000);

        // 1. Opt-out customer
        $this->service->configureCustomerProfile('CUST-OPTOUT', 5, true);
        $dOpt = $this->service->dispatchMessage('CAMP-AUTUMN', 'CUST-OPTOUT', 'EMAIL');
        $this->assertSame('SUPPRESSED_OPT_OUT', $dOpt->status);

        // 2. Fatigue suppressed customer after complaint to service desk
        $this->service->configureCustomerProfile('CUST-COMPLAINT', 5, false);
        $this->service->suppressFatigue('CUST-COMPLAINT', 'CASE-99214');

        $dFatigue = $this->service->dispatchMessage('CAMP-AUTUMN', 'CUST-COMPLAINT', 'PUSH');
        $this->assertSame('SUPPRESSED_FATIGUE', $dFatigue->status);
    }

    /**
     * (c) Budget governance (222.3).
     */
    public function test_campaign_budget_governance(): void
    {
        // Max limit is 50.0
        $this->service->createCampaign('CAMP-TIGHT', 'Tight Budget Campaign', 'SEG-VIP', 50.0, 50.0);
        $this->service->configureCustomerProfile('CUST-BG-1', 10, false);

        // First dispatch spends 30.0 -> OK
        $this->service->dispatchMessage('CAMP-TIGHT', 'CUST-BG-1', 'EMAIL', 30.0);

        // Second dispatch tries to spend 30.0 (total 60.0 > 50.0 limit) -> Throws exception
        try {
            $this->service->dispatchMessage('CAMP-TIGHT', 'CUST-BG-1', 'PUSH', 30.0);
            $this->fail('Expected exception for campaign budget limit exceeded.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('exceeds maximum budget limit', $e->getMessage());
        }
    }

    /**
     * (d) Attribution models strictly summing to 100% (222.4 & 222.5).
     */
    public function test_attribution_models_invariant(): void
    {
        $touchpoints = ['SEARCH_AD', 'EMAIL_NEWSLETTER', 'SMS_PROMO'];

        // 1. First Touch
        $attr1 = $this->service->recordAttribution('CUST-ATTR-1', 500000, 'FIRST_TOUCH', $touchpoints);
        $weights1 = json_decode($attr1->attributed_weights, true);
        $this->assertSame(100.0, (float) $weights1['SEARCH_AD']);
        $this->assertSame(0.0, (float) $weights1['EMAIL_NEWSLETTER']);
        $this->assertEquals(100.0, array_sum($weights1));

        // 2. Last Touch
        $attr2 = $this->service->recordAttribution('CUST-ATTR-2', 500000, 'LAST_TOUCH', $touchpoints);
        $weights2 = json_decode($attr2->attributed_weights, true);
        $this->assertSame(100.0, (float) $weights2['SMS_PROMO']);
        $this->assertEquals(100.0, array_sum($weights2));

        // 3. Multi-Touch Linear
        $attr3 = $this->service->recordAttribution('CUST-ATTR-3', 500000, 'MULTI_TOUCH_LINEAR', $touchpoints);
        $weights3 = json_decode($attr3->attributed_weights, true);
        $this->assertEquals(100.0, array_sum($weights3));
    }

    /**
     * (e) Audit status healthy with 0 discrepancies.
     */
    public function test_marketing_audit(): void
    {
        $audit = $this->service->audit();
        $this->assertSame('HEALTHY', $audit['status']);
        $this->assertSame(0, $audit['discrepancy_count']);
    }
}
