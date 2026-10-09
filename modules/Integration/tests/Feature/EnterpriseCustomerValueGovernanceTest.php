<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\Integration\Application\Services\EnterpriseCustomerValueGovernanceService;
use Tests\TestCase;

class EnterpriseCustomerValueGovernanceTest extends TestCase
{
    use RefreshDatabase;

    protected EnterpriseCustomerValueGovernanceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(EnterpriseCustomerValueGovernanceService::class);
    }

    public function test_customer_value_proposition_and_sales_proof_flow(): void
    {
        // 463.1 Register customer value proposition for EV Charging line
        $prop = $this->service->registerProposition(
            lineCode: 'LINE-EV-CHARGING',
            title: '99.5% Charger Uptime Guarantee with Instant Automated Credit Refund',
            promisedSla: 99.50
        );

        $this->assertEquals('LINE-EV-CHARGING', $prop->line_code);
        $this->assertEquals(0.00, (float) $prop->value_gap_percentage);

        // 463.3 & 463.6 Register and approve verified proof point
        $pp = $this->service->registerProofPoint(
            proofCode: 'PRF-EV-UPTIME-2026',
            lineCode: 'LINE-EV-CHARGING',
            quantifiedClaim: 'Average session start success rate 99.7% across 12,000 charging sessions in Q2'
        );

        $this->assertEquals('draft', $pp->status);

        $approved = $this->service->approveProofPoint('PRF-EV-UPTIME-2026', true);
        $this->assertEquals('verified_approved', $approved->status);
        $this->assertTrue((bool) $approved->evidence_verified);

        // 463.4 Audit clean
        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);
    }

    public function test_unverified_claim_blocked_and_large_value_gap_edge_cases(): void
    {
        // 463.6 Risk: Unverified marketing value claim cannot be approved
        $this->service->registerProofPoint('PRF-BOGUS', 'LINE-TOWING', 'Fastest roadside response in universe');

        try {
            $this->service->approveProofPoint('PRF-BOGUS', false);
            $this->fail('Expected exception for unverified sales claim');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('requires empirical evidence verification before commercial release', $e->getMessage());
        }

        // 463.5 Edge case: Large value gap (> 10%) automatically mandates prioritized improvement backlog
        $this->service->registerProposition('LINE-DETAILING', 'Same-day 4-hour express auto detailing', 95.00);

        // Actual delivered only 80.00% (gap = 15.00% > 10%)
        $gapped = $this->service->recordDeliveredValue('LINE-DETAILING', 80.00);
        $this->assertEquals(15.00, (float) $gapped->value_gap_percentage);
        $this->assertTrue((bool) $gapped->has_prioritized_improvement_backlog);
    }
}
