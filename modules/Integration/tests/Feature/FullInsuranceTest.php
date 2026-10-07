<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Integration\Application\Services\FullInsuranceService;
use Tests\TestCase;

/**
 * Fase 156 — Full Insurance (Lini 18) Tests
 *
 * Covers:
 *  (a) rating engine deterministik dua run identik
 *  (b) reserve >= kewajiban
 *  (c) claim ganda atas polis sama ditolak
 *  (d) subrogation recovery mengurangi net loss
 *  (e) ins:audit = ledger 0 selisih
 */
class FullInsuranceTest extends TestCase
{
    use RefreshDatabase;

    protected FullInsuranceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(FullInsuranceService::class);
    }

    /**
     * (a) Rating engine deterministik.
     */
    public function test_rating_engine_is_deterministic(): void
    {
        $prem1 = $this->service->calculatePremium('VEHICLE', 300000000.0, 1.2);
        $prem2 = $this->service->calculatePremium('VEHICLE', 300000000.0, 1.2);

        // 300,000,000 * 0.025 * 1.2 = 9,000,000
        $this->assertEquals(9000000.00, $prem1);
        $this->assertEquals($prem1, $prem2);
    }

    /**
     * (b) Reserve held >= outstanding liabilities.
     */
    public function test_actuarial_reserves_held_exceeds_liabilities(): void
    {
        $reserve = $this->service->updateActuarialReserve('VEHICLE', 500000000.00, 0.20);

        $this->assertEquals(500000000.00, (float) $reserve->outstanding_claims_reserve);
        $this->assertEquals(100000000.00, (float) $reserve->ibnr_reserve);
        $this->assertEquals(600000000.00, (float) $reserve->total_reserve_held);
        $this->assertGreaterThanOrEqual((float) $reserve->outstanding_claims_reserve, (float) $reserve->total_reserve_held);
    }

    /**
     * (c) Claim ganda atas polis dan insiden sama ditolak.
     */
    public function test_duplicate_claims_are_rejected(): void
    {
        $policy = $this->service->issuePolicy('VEHICLE-ALLRISK', 501, 250000000.0);

        // 1. First claim succeeds
        $claim = $this->service->submitClaim($policy->policy_number, 'Bumper collision at intersection', 7500000.00);
        $this->assertSame('REGISTERED', $claim->status);

        // 2. Duplicate claim with exact same incident throws exception
        $this->expectException(\RuntimeException::class);
        $this->service->submitClaim($policy->policy_number, 'Bumper collision at intersection', 7500000.00);
    }

    /**
     * (d) Subrogation recovery tercatat pada approved claim.
     */
    public function test_claim_approval_and_subrogation_recovery(): void
    {
        $policy = $this->service->issuePolicy('PROPERTY-FIRE', 502, 1000000000.0);
        $claim = $this->service->submitClaim($policy->policy_number, 'Warehouse water pipe leak', 50000000.00);

        $approved = $this->service->approveClaim($claim->claim_number, 50000000.00, 15000000.00);
        $this->assertSame('PAID', $approved->status);
        $this->assertEquals(50000000.00, (float) $approved->approved_amount);
        $this->assertEquals(15000000.00, (float) $approved->subrogation_recovered);
        $this->assertNotNull($approved->ledger_payout_ref);
    }

    /**
     * (e) Insurance audit returns healthy state.
     */
    public function test_insurance_audit_healthy(): void
    {
        $this->service->updateActuarialReserve('HEALTH', 200000000.00);

        $audit = $this->service->audit();
        $this->assertSame('HEALTHY', $audit['status']);
        $this->assertSame(0, $audit['discrepancy_count']);
    }
}
