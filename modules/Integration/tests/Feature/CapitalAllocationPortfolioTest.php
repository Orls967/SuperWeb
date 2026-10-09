<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\Integration\Application\Services\CapitalAllocationPortfolioService;
use Tests\TestCase;

class CapitalAllocationPortfolioTest extends TestCase
{
    use RefreshDatabase;

    protected CapitalAllocationPortfolioService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(CapitalAllocationPortfolioService::class);
    }

    public function test_capital_investment_scoring_and_tranche_release_flow(): void
    {
        // 437.1 Investment scoring: IRR 24%, Strategic Fit 90 -> Weighted = 24*0.6 + 90*0.4 = 14.4 + 36 = 50.40
        $prop = $this->service->createProposal(
            proposalCode: 'CAPEX-LOGISTICS-HUB',
            projectName: 'Automated High-Throughput Fulfillment Center Cikarang',
            npv: 85000000000.00,
            irrPercent: 24.00,
            strategicFitScore: 90.00,
            requestedCapex: 100000000000.00
        );

        $this->assertEquals('CAPEX-LOGISTICS-HUB', $prop->proposal_code);
        $this->assertEquals(50.40, (float) $prop->weighted_score);

        // 437.6 Independent review approval
        $this->service->approveIndependentReview('CAPEX-LOGISTICS-HUB');

        // 437.2 & 437.4 Release tranche 1 (40B)
        $t1 = $this->service->releaseFundingTranche('CAPEX-LOGISTICS-HUB', 40000000000.00, 200000000000.00);
        $this->assertEquals(40000000000.00, (float) $t1->approved_funding_release);

        // 437.3 Complete post-investment review before tranche 2
        $this->service->completePostInvestmentReview('CAPEX-LOGISTICS-HUB');

        // Release tranche 2 (60B) -> Total 100B (reaches requested capex)
        $t2 = $this->service->releaseFundingTranche('CAPEX-LOGISTICS-HUB', 60000000000.00, 200000000000.00);
        $this->assertEquals(100000000000.00, (float) $t2->approved_funding_release);

        // 437.4 Audit clean
        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);
    }

    public function test_budget_breach_and_missing_review_blocked_edge_cases(): void
    {
        $this->service->createProposal('CAPEX-HOTEL-SPA', 'Luxury Resort Spa Expansion', 10000000000, 18, 75, 20000000000);

        // 437.6 Unapproved independent review blocks funding
        try {
            $this->service->releaseFundingTranche('CAPEX-HOTEL-SPA', 5000000000, 50000000000);
            $this->fail('Expected exception for unapproved independent review');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Independent reviewer approval is required', $e->getMessage());
        }

        // Approve review
        $this->service->approveIndependentReview('CAPEX-HOTEL-SPA');

        // 437.5 Edge case: Funding release exceeding requested capex is blocked
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('exceeds approved capex limit');

        $this->service->releaseFundingTranche('CAPEX-HOTEL-SPA', 25000000000, 50000000000); // 25B > 20B capex!
    }
}
