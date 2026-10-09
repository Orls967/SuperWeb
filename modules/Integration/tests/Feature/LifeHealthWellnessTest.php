<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Integration\Application\Services\LifeHealthWellnessService;
use Tests\TestCase;

/**
 * Fase 159 — Life, Health & Wellness Insurance Advanced Tests
 *
 * Covers:
 *  (a) cashless authorization <= limit polis
 *  (b) wellness reward tak bisa di-gaming (>50k steps flagged)
 *  (c) NAV Σ = dana kelolaan (AUM)
 *  (d) beneficiary registration & validation
 *  (e) ins:audit + hosp:audit = 0 selisih
 */
class LifeHealthWellnessTest extends TestCase
{
    use RefreshDatabase;

    protected LifeHealthWellnessService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(LifeHealthWellnessService::class);
    }

    /**
     * (a) Cashless authorization cannot exceed annual policy limit.
     */
    public function test_cashless_authorization_limit_enforcement(): void
    {
        // 1. Within limit -> APPROVED
        $approved = $this->service->authorizeCashless('POL-HEALTH-001', 'HOSP-SILOAM-BDJ', 50000000.0, 15000000.0);
        $this->assertSame('APPROVED', $approved->status);
        $this->assertEquals(15000000.00, (float) $approved->authorized_amount);

        // 2. Exceeds limit -> EXCEEDS_LIMIT and authorized = 0
        $rejected = $this->service->authorizeCashless('POL-HEALTH-001', 'HOSP-SILOAM-BDJ', 50000000.0, 75000000.0);
        $this->assertSame('EXCEEDS_LIMIT', $rejected->status);
        $this->assertEquals(0.00, (float) $rejected->authorized_amount);
    }

    /**
     * (b) Anti-gaming logic on wellness wearable steps.
     */
    public function test_wellness_rewards_anti_gaming_protection(): void
    {
        $today = Carbon::today();

        // Legitimate workout: 12,000 steps -> 120 reward points, not flagged
        $normal = $this->service->recordWellnessSteps(601, $today, 12000);
        $this->assertFalse((bool) $normal->is_flagged_gaming);
        $this->assertSame(120, $normal->reward_points);

        // Gaming / spoofing attempt: 85,000 steps -> 0 points, flagged
        $spoofed = $this->service->recordWellnessSteps(602, $today, 85000);
        $this->assertTrue((bool) $spoofed->is_flagged_gaming);
        $this->assertSame(0, $spoofed->reward_points);
    }

    /**
     * (c) Unit Link AUM is strictly NAV * units outstanding.
     */
    public function test_unit_link_aum_valuation(): void
    {
        // NAV: 1,500.2500 per unit. Units: 200,000.
        // AUM = 300,050,000.00
        $fund = $this->service->updateUnitLinkFund('FUND-EQUITY-ID', 'AutoServe Aggressive Equity Fund', 1500.25, 200000.0);

        $this->assertEquals(300050000.00, (float) $fund->total_assets_under_management);
    }

    /**
     * (d) Term life policy beneficiary assignment.
     */
    public function test_life_policy_issuance_and_beneficiary(): void
    {
        $life = $this->service->issueLifePolicy(603, 'Siti Rahma', 'SPOUSE', 1000000000.0, 750000.0);

        $this->assertSame('Siti Rahma', $life->beneficiary_name);
        $this->assertSame('SPOUSE', $life->beneficiary_relation);
        $this->assertSame('ACTIVE', $life->status);
    }

    /**
     * (e) Audit status healthy.
     */
    public function test_life_health_wellness_audit(): void
    {
        $audit = $this->service->audit();
        $this->assertSame('HEALTHY', $audit['status']);
        $this->assertSame(0, $audit['discrepancy_count']);
    }
}
