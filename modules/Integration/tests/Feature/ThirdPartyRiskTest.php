<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Integration\Application\Services\ThirdPartyRiskService;
use Tests\TestCase;

/**
 * Fase 205 — Risiko: Third-Party & Supply Chain Risk Tests
 *
 * Covers:
 *  (a) vendor criticality tiering
 *  (b) concentration exposure limit monitoring and breach detection
 *  (c) exit and continuity playbook enforcement for Tier 1 vendors
 *  (d) vendor:audit = 0 discrepancy
 */
class ThirdPartyRiskTest extends TestCase
{
    use RefreshDatabase;

    protected ThirdPartyRiskService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ThirdPartyRiskService::class);
    }

    /**
     * (a) & (b) Tiering and concentration exposure monitoring.
     */
    public function test_vendor_concentration_exposure_monitoring(): void
    {
        // Limit 5 Billion IDR
        $v = $this->service->registerVendor('VND-CLOUD-01', 'Primary Cloud Provider', 'TIER_1_CRITICAL', 5000000000.0, true);
        $this->assertSame('TIER_1_CRITICAL', $v->criticality_tier);
        $this->assertFalse((bool) $v->concentration_breached);

        // 1. Exposure within limit: 3 Billion -> Clean
        $v1 = $this->service->updateExposure('VND-CLOUD-01', 3000000000.0);
        $this->assertFalse((bool) $v1->concentration_breached);

        // 2. Exposure exceeds limit: 6.5 Billion -> Breached
        $v2 = $this->service->updateExposure('VND-CLOUD-01', 6500000000.0);
        $this->assertTrue((bool) $v2->concentration_breached);
    }

    /**
     * (c) Exit & continuity playbook requirement.
     */
    public function test_vendor_exit_continuity_playbook(): void
    {
        $v = $this->service->registerVendor('VND-LOGISTIK-02', 'Strategic Ocean Carrier', 'TIER_1_CRITICAL', 10000000000.0, true);
        $this->assertTrue((bool) $v->has_exit_continuity_playbook);
    }

    /**
     * (d) Audit status healthy with 0 discrepancies.
     */
    public function test_vendor_risk_audit(): void
    {
        $audit = $this->service->audit();
        $this->assertSame('HEALTHY', $audit['status']);
        $this->assertSame(0, $audit['discrepancy_count']);
    }
}
