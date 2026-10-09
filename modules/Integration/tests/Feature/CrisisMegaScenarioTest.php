<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\Integration\Application\Services\CrisisMegaScenarioService;
use Tests\TestCase;

class CrisisMegaScenarioTest extends TestCase
{
    use RefreshDatabase;

    protected CrisisMegaScenarioService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(CrisisMegaScenarioService::class);
    }

    public function test_layered_crisis_simulation_and_recovery_flow(): void
    {
        // 466.1 Trigger 5 concurrent shocks across lines in isolated sandbox
        $crisis = $this->service->triggerCrisis(
            code: 'CRISIS-MEGA-2026-01',
            concurrentShocks: ['flood', 'blackout', 'health_event', 'commodity_shock', 'cyber_incident'],
            sandboxIsolated: true
        );

        $this->assertEquals('CRISIS-MEGA-2026-01', $crisis->crisis_code);
        $this->assertEquals('active', $crisis->status);

        // 466.2 & 466.4 Recover crisis with zero invariant leakage and measured RTO (42 mins)
        $recovered = $this->service->recoverCrisis(
            code: 'CRISIS-MEGA-2026-01',
            rtoMinutes: 42.00,
            moneyVariance: 0.00,
            stockVariance: 0.00
        );

        $this->assertEquals('recovered', $recovered->status);
        $this->assertEquals(0.00, (float) $recovered->money_invariant_variance);
        $this->assertEquals(0.00, (float) $recovered->stock_invariant_variance);

        // 466.4 Audit clean
        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);
    }

    public function test_unsandboxed_simulation_and_war_room_escalation_edge_cases(): void
    {
        // 466.6 Risk: Unsandboxed simulation is blocked
        try {
            $this->service->triggerCrisis('CRISIS-UNSAFE', ['blackout'], false);
            $this->fail('Expected exception for unsandboxed crisis simulation');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Must execute within an isolated sandbox environment', $e->getMessage());
        }

        // 466.5 Edge case: Stalled recovery escalates to War Room + Plan B
        $this->service->triggerCrisis('CRISIS-STALLED', ['cyber_incident'], true);

        $escalated = $this->service->recoverCrisis(
            code: 'CRISIS-STALLED',
            rtoMinutes: 0.00,
            moneyVariance: 0.00,
            stockVariance: 0.00,
            recoveryStalled: true
        );

        $this->assertEquals('war_room_escalated', $escalated->status);
        $this->assertTrue((bool) $escalated->escalated_to_war_room);
    }
}
