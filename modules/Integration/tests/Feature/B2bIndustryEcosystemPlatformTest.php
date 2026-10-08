<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\B2bIndustryEcosystemPlatformService;
use Tests\TestCase;

class B2bIndustryEcosystemPlatformTest extends TestCase
{
    use RefreshDatabase;

    protected B2bIndustryEcosystemPlatformService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(B2bIndustryEcosystemPlatformService::class);
    }

    public function test_industry_participant_onboarding_and_fee_capping(): void
    {
        // 1. Tier 3 SME with capped fee (2.0% <= 2.5% cap) succeeds (316.1 & 316.6 Risk)
        $sme = $this->service->onboardParticipant(
            partnerCode: 'PARTNER-LOCAL-WORKSHOP-01',
            vertical: 'MINING_HEAVY_EQUIPMENT',
            kybTier: 'TIER_3_SME',
            proposedFeePct: 2.0
        );
        $this->assertEquals(2.0, (float) $sme->platform_fee_rate_pct);

        // 2. Tier 3 SME fee exceeding 2.5% cap is rejected (316.6)
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Fee cap breach: Proposed fee (4%) exceeds maximum limit (2.5%)');
        $this->service->onboardParticipant('PARTNER-EXPENSIVE', 'MINING', 'TIER_3_SME', 4.0);
    }

    public function test_trust_index_drop_mandates_remediation_plan(): void
    {
        $this->service->onboardParticipant('PARTNER-SMELTER-02', 'SMELTER_REFINERY', 'TIER_1_ENTERPRISE', 4.5);

        // 1. Normal trust score (8.5) requires no remediation
        $normal = $this->service->updateTrustIndex('PARTNER-SMELTER-02', 8.5);
        $this->assertFalse((bool) $normal->remediation_plan_active);

        // 2. Low trust score (< 6.0) automatically triggers active remediation plan (316.3 & 316.5 Edge Case)
        $lowTrust = $this->service->updateTrustIndex('PARTNER-SMELTER-02', 5.2);
        $this->assertTrue((bool) $lowTrust->remediation_plan_active);
    }

    public function test_liquidity_subsidy_budget_bounding(): void
    {
        // 1. Disbursed subsidy within allocated budget succeeds (316.2 & 316.4)
        $subsidy = $this->service->allocateLiquiditySubsidy(
            campaignCode: 'CAMP-NICKEL-LIQUIDITY-01',
            vertical: 'SMELTER_REFINERY',
            allocatedBudgetUsd: 500000.0,
            disbursedAmountUsd: 150000.0
        );
        $this->assertEquals(150000.0, (float) $subsidy->utilized_subsidy_usd);

        // 2. Disbursed subsidy exceeding campaign budget is rejected (316.4)
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Budget violation: Disbursed subsidy ($600000) exceeds allocated campaign cap ($500000)');
        $this->service->allocateLiquiditySubsidy('CAMP-OVERSPENT', 'SMELTER_REFINERY', 500000.0, 600000.0);
    }

    public function test_b2b_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->onboardParticipant('P-AUD', 'VERT', 'TIER_1_ENTERPRISE', 3.0);
        $this->service->allocateLiquiditySubsidy('CAMP-AUD', 'VERT', 1000.0, 500.0);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: low trust without remediation
        DB::table('b2b_industry_vertical_participants')->insert([
            'partner_code' => 'P-UNREMEDIATED',
            'industry_vertical' => 'LOGISTICS',
            'kyb_tier' => 'TIER_2_MID',
            'platform_fee_rate_pct' => 3.0,
            'reputation_trust_index' => 4.0, // < 6.0
            'remediation_plan_active' => false, // Discrepancy!
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
