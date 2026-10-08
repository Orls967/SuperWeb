<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\AiCostSustainabilityService;
use Tests\TestCase;

class AiCostSustainabilityTest extends TestCase
{
    use RefreshDatabase;

    protected AiCostSustainabilityService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(AiCostSustainabilityService::class);
    }

    public function test_loop_runaway_circuit_breaker_edge_case(): void
    {
        // 1. Normal invocation count (5 <= 20) succeeds (349.1 & 349.4)
        $normal = $this->service->trackInvocationCost(
            invocationCode: 'INV-NORMAL-001',
            domainName: 'MINING',
            agentId: 'AGENT-ORE-GRADE-CLASSIFIER',
            costUsd: 0.0450,
            consecutiveCount: 5
        );
        $this->assertFalse((bool) $normal->circuit_breaker_tripped);

        // 2. Runaway loop (> 20 calls) trips circuit breaker immediately (349.5 Edge Case)
        try {
            $this->service->trackInvocationCost(
                invocationCode: 'INV-RUNAWAY-LOOP-002',
                domainName: 'MINING',
                agentId: 'AGENT-ORE-GRADE-CLASSIFIER',
                costUsd: 0.2500,
                consecutiveCount: 25 // 25 > 20 calls!
            );
            $this->fail('Expected exception for tripped circuit breaker');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('AI runtime circuit breaker tripped: Agent loop detected', $e->getMessage());
        }

        // Verify tripped record exists
        $tripped = DB::table('ai_cost_sustainability_trackers')->where('invocation_code', 'INV-RUNAWAY-LOOP-002')->first();
        $this->assertNotNull($tripped);
        $this->assertTrue((bool) $tripped->circuit_breaker_tripped);
    }

    public function test_model_tiering_runtime_policy_enforcement(): void
    {
        // 1. Low risk task using efficient tier passes (349.3)
        $lowRisk = $this->service->enforceModelTierPolicy(
            requestCode: 'REQ-CHAT-SUMMARY-01',
            stakes: 'LOW_RISK',
            modelTier: 'EFFICIENT_STANDARD'
        );
        $this->assertTrue((bool) $lowRisk->tier_policy_compliant);

        // 2. High stakes task using efficient tier throws exception (349.3 & 349.4)
        try {
            $this->service->enforceModelTierPolicy(
                requestCode: 'REQ-SMELTER-SHUTDOWN-DISPATCH',
                stakes: 'HIGH_STAKES',
                modelTier: 'EFFICIENT_STANDARD' // Disallowed!
            );
            $this->fail('Expected exception for high stakes task using unreviewed model tier');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('High-stakes decision requires REVIEWED_PREMIUM model tier', $e->getMessage());
        }

        // 3. High stakes task using reviewed premium tier succeeds (349.3)
        $highStakes = $this->service->enforceModelTierPolicy(
            requestCode: 'REQ-SMELTER-SHUTDOWN-APPROVED',
            stakes: 'HIGH_STAKES',
            modelTier: 'REVIEWED_PREMIUM'
        );
        $this->assertEquals('REVIEWED_PREMIUM', $highStakes->assigned_model_tier);
    }

    public function test_ai_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->trackInvocationCost('INV-AUD', 'FINANCE', 'AGENT-1', 0.01, 1);
        $this->service->enforceModelTierPolicy('REQ-AUD', 'LOW_RISK', 'EFFICIENT_STANDARD');

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: runaway invocation not marked tripped
        DB::table('ai_cost_sustainability_trackers')->insert([
            'invocation_code' => 'INV-DEFECT-UNBROKEN',
            'domain_name' => 'FINANCE',
            'agent_identifier' => 'AGENT-2',
            'invocation_cost_usd' => 5.0,
            'consecutive_invocation_count' => 50,
            'circuit_breaker_tripped' => false, // Discrepancy!
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
