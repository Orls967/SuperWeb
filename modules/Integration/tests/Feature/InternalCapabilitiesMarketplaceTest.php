<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Integration\Application\Services\InternalCapabilitiesMarketplaceService;
use Tests\TestCase;

/**
 * Fase 235 — Inovasi: Marketplace of Capabilities & Internal API Products Tests
 *
 * Covers:
 *  (a) Capability catalog registration and consumer subscription
 *  (b) Unit-cost chargeback ledger balancing and SLA breach penalty crediting
 *  (c) Edge Case 235.6: Shadow duplication bypass detected & prefer-platform policy enforced
 *  (d) Capability deprecation policy with defined grace period
 *  (e) Quality audit api:audit clean with 0 discrepancies
 */
class InternalCapabilitiesMarketplaceTest extends TestCase
{
    use RefreshDatabase;

    protected InternalCapabilitiesMarketplaceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(InternalCapabilitiesMarketplaceService::class);
    }

    /**
     * (a) Capability catalog registration & consumer subscription (235.1 & 235.2).
     */
    public function test_capability_catalog_and_subscription(): void
    {
        $cap = $this->service->registerCapability(
            'CAP-PAY',
            'Unified Payment Engine',
            'PAYMENT',
            'v1',
            150.0, // 150 IDR per transaction
            99.95,
            80
        );

        $this->assertSame('CAP-PAY', $cap->capability_code);
        $this->assertEquals(99.95, (float) $cap->sla_availability_pct);

        $sub = $this->service->subscribeConsumer('CAP-PAY', 'HOTEL', 5000);
        $this->assertSame('HOTEL', $sub->consumer_business_line);
        $this->assertSame('ACTIVE', $sub->status);
    }

    /**
     * (b) Chargeback ledger balancing and SLA penalty crediting (235.1, 235.3 & 235.5).
     */
    public function test_chargeback_and_sla_breach_crediting(): void
    {
        $this->service->registerCapability('CAP-ID', 'Global KYC & Identity Vault', 'IDENTITY', 'v1', 500.0, 99.90);

        // 1. Record an SLA breach: Actual 98.5% < 99.9% target -> 50,000 IDR credit penalty
        $breach = $this->service->recordSlaBreach('CAP-ID', 'RETAIL', 98.50, 50000.0);
        $this->assertEquals(50000.0, (float) $breach->credit_penalty_amount);

        // 2. Process usage: 1,000 calls * 500 IDR = 500,000 IDR gross charge minus 50,000 SLA credit = 450,000 net
        $charge = $this->service->recordUsageAndChargeback('CAP-ID', 'RETAIL', 1000, 50000.0);
        $this->assertEquals(500000.0, (float) $charge->charge_amount);
        $this->assertEquals(50000.0, (float) $charge->credit_penalty_amount);
        $this->assertEquals(450000.0, (float) $charge->net_transferred_amount);
    }

    /**
     * (c) Edge Case 235.6: Shadow duplication bypass detection.
     */
    public function test_shadow_duplication_bypass_detection(): void
    {
        // Business unit attempting to build bespoke logistics tracker instead of using CAP-LOG
        $detection = $this->service->detectShadowDuplication(
            'FOOD',
            'CAP-LOG',
            'Custom In-house Cold Chain Fleet Tracker'
        );

        $this->assertSame('FOOD', $detection->business_line);
        $this->assertSame('CAP-LOG', $detection->bypassed_capability_code);
        $this->assertSame('PREFER_PLATFORM_POLICY_ENFORCED', $detection->enforcement_action);
    }

    /**
     * (d) Deprecation policy with grace period (235.7).
     */
    public function test_capability_deprecation_policy(): void
    {
        $this->service->registerCapability('CAP-OLD-AUTH', 'Legacy OAuth 1.0 Provider', 'IDENTITY', 'v0', 50.0);
        $deprecated = $this->service->deprecateCapability('CAP-OLD-AUTH', '2027-03-31');

        $this->assertSame('DEPRECATED', $deprecated->status);
        $this->assertSame('2027-03-31', $deprecated->deprecation_grace_period_end);
    }

    /**
     * (e) Audit status healthy with 0 discrepancies.
     */
    public function test_api_marketplace_audit(): void
    {
        $audit = $this->service->audit();
        $this->assertSame('HEALTHY', $audit['status']);
        $this->assertSame(0, $audit['discrepancy_count']);
    }
}
