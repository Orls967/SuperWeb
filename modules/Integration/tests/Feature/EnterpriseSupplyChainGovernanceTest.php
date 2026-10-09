<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Integration\Application\Services\EnterpriseSupplyChainGovernanceService;
use Tests\TestCase;

class EnterpriseSupplyChainGovernanceTest extends TestCase
{
    use RefreshDatabase;

    protected EnterpriseSupplyChainGovernanceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(EnterpriseSupplyChainGovernanceService::class);
    }

    public function test_supply_chain_strategy_and_sourcing_flow(): void
    {
        // 461.1 & 461.2 Register critical category with dual sourcing requirement
        $cat = $this->service->registerCategory(
            code: 'CAT-EV-BATTERY-PACK',
            tier: 'strategic',
            dualSourcingRequired: true,
            reviewedAt: now()->toDateString()
        );

        $this->assertEquals('CAT-EV-BATTERY-PACK', $cat->category_code);
        $this->assertTrue((bool) $cat->dual_sourcing_required);

        // 461.1 Conduct sourcing with 2 qualified vendors (satisfies dual sourcing)
        $event = $this->service->conductSourcingEvent('EVT-BATTERY-2026', 'CAT-EV-BATTERY-PACK', 2);
        $this->assertEquals('compliant', $event->status);
        $this->assertFalse((bool) $event->policy_deviation_flagged);

        // 461.4 Audit clean
        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);
    }

    public function test_dual_sourcing_violation_and_stale_review_edge_cases(): void
    {
        // 461.5 Edge case: Dual sourcing required but only 1 supplier available flags policy deviation
        $this->service->registerCategory('CAT-SINGLE-DEPENDENCY', 'strategic', true);

        $event = $this->service->conductSourcingEvent('EVT-SINGLE-SOLO', 'CAT-SINGLE-DEPENDENCY', 1); // Only 1 supplier
        $this->assertEquals('deviation_flagged', $event->status);
        $this->assertTrue((bool) $event->policy_deviation_flagged);

        // 461.4 Stale annual review detection in audit
        $this->service->registerCategory('CAT-STALE', 'tactical', false, now()->subMonths(14)->toDateString());

        $audit = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $audit['status']);
        $this->assertGreaterThan(0, $audit['stale_strategies']);

        // Refresh review resolves discrepancy
        $this->service->refreshAnnualReview('CAT-STALE');
        $this->assertFalse((bool) $this->service->conductSourcingEvent('EVT-OK', 'CAT-STALE', 1)->policy_deviation_flagged);
    }
}
