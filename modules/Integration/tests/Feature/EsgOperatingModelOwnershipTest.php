<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\Integration\Application\Services\EsgOperatingModelOwnershipService;
use Tests\TestCase;

class EsgOperatingModelOwnershipTest extends TestCase
{
    use RefreshDatabase;

    protected EsgOperatingModelOwnershipService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(EsgOperatingModelOwnershipService::class);
    }

    public function test_metric_ownership_and_verified_incentive_payout_flow(): void
    {
        // 441.1 Formalize ESG metric ownership
        $metric = $this->service->registerMetricOwnership(
            metricCode: 'ESG-GHG-INTENSITY',
            topic: 'climate',
            dataOwner: 'VP Fleet Operations',
            steward: 'Head of Sustainability',
            assuranceProvider: 'PwC Sustainability Assurance'
        );

        $this->assertEquals('ESG-GHG-INTENSITY', $metric->metric_code);
        $this->assertFalse((bool) $metric->is_orphaned);

        // 441.3 Record executive scorecard with verified assurance
        $card = $this->service->recordScorecard(
            scorecardCode: 'SC-CEO-2026',
            executiveId: 'EXEC-CEO',
            metricCode: 'ESG-GHG-INTENSITY',
            target: 15.00,
            actual: 18.50,
            verifiedByAssurance: true
        );

        $this->assertTrue((bool) $card->metric_verified_by_assurance);

        // 441.4 Payout incentive
        $payout = $this->service->payoutEsgIncentive('SC-CEO-2026', 150000000.00, true);
        $this->assertEquals(150000000.00, (float) $payout->incentive_bonus_payout);

        // 441.4 Audit clean
        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);
    }

    public function test_unverified_incentive_and_anti_gaming_blocked_edge_cases(): void
    {
        // 441.4 Unverified metric outcome blocks payout
        $this->service->recordScorecard('SC-COO-UNVERIFIED', 'EXEC-COO', 'METRIC-X', 10, 12, false);

        try {
            $this->service->payoutEsgIncentive('SC-COO-UNVERIFIED', 50000000.00);
            $this->fail('Expected exception for unverified ESG outcome');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('independently verified by external assurance', $e->getMessage());
        }

        // 441.5 Edge case: Anti-gaming guardrail violation blocks payout
        $this->service->recordScorecard('SC-CFO-GAMED', 'EXEC-CFO', 'METRIC-Y', 20, 25, true);

        try {
            $this->service->payoutEsgIncentive('SC-CFO-GAMED', 50000000.00, false); // Gaming flagged!
            $this->fail('Expected exception for gaming violation');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Metric gaming / manipulation detected', $e->getMessage());
        }
    }
}
