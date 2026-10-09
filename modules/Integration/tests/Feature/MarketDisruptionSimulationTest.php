<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\Integration\Application\Services\MarketDisruptionSimulationService;
use Tests\TestCase;

class MarketDisruptionSimulationTest extends TestCase
{
    use RefreshDatabase;

    protected MarketDisruptionSimulationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(MarketDisruptionSimulationService::class);
    }

    public function test_market_disruption_simulation_and_response_flow(): void
    {
        // 469.1 & 469.2 Simulate technology shift (competitor introduces robo-taxis) with moderate -12% EBITDA impact
        $sim = $this->service->simulateDisruption(
            code: 'DISRUPT-ROBOTAXI-2026',
            type: 'technology_shift',
            ebitdaImpactPercent: -12.50,
            lever: 'partnership',
            confidence: 'high',
            sandboxOnly: true
        );

        $this->assertEquals('DISRUPT-ROBOTAXI-2026', $sim->scenario_code);
        $this->assertTrue((bool) $sim->sandbox_only);
        $this->assertFalse((bool) $sim->severe_loss_mitigation_plan_logged);

        // 469.4 Audit clean
        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);
    }

    public function test_severe_loss_mitigation_plan_and_unsandboxed_blocked_edge_cases(): void
    {
        // 469.3 & 469.4 Unsandboxed disruption simulation is blocked
        try {
            $this->service->simulateDisruption('DISRUPT-LIVE', 'demand_collapse', -15.00, 'cost', 'medium', false);
            $this->fail('Expected exception for unsandboxed simulation');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('must be restricted to isolated sandbox', $e->getMessage());
        }

        // 469.5 Edge case: Severe loss (> 20% drop) automatically mandates logged mitigation plan
        $severe = $this->service->simulateDisruption(
            code: 'DISRUPT-COMMODITY-COLLAPSE',
            type: 'demand_collapse',
            ebitdaImpactPercent: -35.00, // Severe!
            lever: 'cost',
            confidence: 'high'
        );

        $this->assertTrue((bool) $severe->severe_loss_mitigation_plan_logged);

        // Audit clean
        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);
    }
}
