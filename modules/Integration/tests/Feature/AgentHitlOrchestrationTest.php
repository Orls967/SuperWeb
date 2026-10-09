<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Integration\Application\Services\AgentHitlOrchestrationService;
use Tests\TestCase;

/**
 * Fase 196 — AI: Agent Orchestration & Human-in-the-Loop Tests
 *
 * Covers:
 *  (a) tool action outside whitelist is blocked with exception
 *  (b) step budget is enforced strictly
 *  (c) instant kill-switch stops agent execution immediately
 *  (d) HITL review requires mandatory reviewer reason
 *  (e) agent:audit = 0 discrepancy
 */
class AgentHitlOrchestrationTest extends TestCase
{
    use RefreshDatabase;

    protected AgentHitlOrchestrationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(AgentHitlOrchestrationService::class);
    }

    /**
     * (a) Tool whitelisting enforcement.
     */
    public function test_agent_tool_whitelisting(): void
    {
        // Register procurement agent with whitelist: ['read_catalog', 'request_quote']
        $this->service->registerAgent('AGT-PROCURE-01', 'PROCUREMENT', ['read_catalog', 'request_quote'], 5);

        // 1. Whitelisted tool -> SUCCESS
        $this->assertTrue($this->service->executeAgentStep('AGT-PROCURE-01', 'read_catalog', 1));

        // 2. Non-whitelisted tool (e.g. 'execute_wire_transfer') -> BLOCKED
        try {
            $this->service->executeAgentStep('AGT-PROCURE-01', 'execute_wire_transfer', 2);
            $this->fail('Expected exception for non-whitelisted tool execution.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Tool \'execute_wire_transfer\' is not in the whitelist', $e->getMessage());
        }
    }

    /**
     * (b) Step budget enforcement.
     */
    public function test_agent_step_budget_limit(): void
    {
        $this->service->registerAgent('AGT-OPS-01', 'OPS', ['query_telematics'], 3);

        // Step 3 within budget
        $this->assertTrue($this->service->executeAgentStep('AGT-OPS-01', 'query_telematics', 3));

        // Step 4 exceeds budget of 3 -> Exception
        try {
            $this->service->executeAgentStep('AGT-OPS-01', 'query_telematics', 4);
            $this->fail('Expected exception for step budget exceeded.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Step budget exceeded', $e->getMessage());
        }
    }

    /**
     * (c) Instant kill-switch stops agent.
     */
    public function test_agent_kill_switch(): void
    {
        $this->service->registerAgent('AGT-CLAIMS-01', 'CLAIMS', ['verify_claim'], 10);

        // Active agent runs step
        $this->assertTrue($this->service->executeAgentStep('AGT-CLAIMS-01', 'verify_claim', 1));

        // Trigger kill-switch
        $this->service->triggerKillSwitch('AGT-CLAIMS-01');

        // Subsequent call is blocked
        try {
            $this->service->executeAgentStep('AGT-CLAIMS-01', 'verify_claim', 2);
            $this->fail('Expected exception for killed agent.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Kill-switch is active', $e->getMessage());
        }
    }

    /**
     * (d) Human-in-the-loop review requires reason.
     */
    public function test_hitl_review_audit_reason(): void
    {
        $review = $this->service->enqueueHitlReview('AGT-FIN-01', 'DISBURSE_MONEY', 500000000.0, 'CFO');
        $this->assertSame('PENDING', $review->status);

        // Attempt resolving without reason -> Fails
        try {
            $this->service->resolveHitlReview($review->review_code, true, '   ');
            $this->fail('Expected exception for missing review reason.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('Reviewer reason cannot be empty', $e->getMessage());
        }

        // Resolving with valid reason -> APPROVED
        $resolved = $this->service->resolveHitlReview($review->review_code, true, 'Disbursement verified against physical PO and goods receipt.');
        $this->assertSame('APPROVED', $resolved->status);
        $this->assertNotNull($resolved->reviewer_reason);
    }

    /**
     * (e) Audit status healthy with 0 discrepancies.
     */
    public function test_agent_hitl_audit(): void
    {
        $audit = $this->service->audit();
        $this->assertSame('HEALTHY', $audit['status']);
        $this->assertSame(0, $audit['discrepancy_count']);
    }
}
