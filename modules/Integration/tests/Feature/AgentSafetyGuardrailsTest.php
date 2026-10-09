<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\AgentSafetyGuardrailsService;
use Tests\TestCase;

class AgentSafetyGuardrailsTest extends TestCase
{
    use RefreshDatabase;

    protected AgentSafetyGuardrailsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(AgentSafetyGuardrailsService::class);
    }

    public function test_safety_eval_suite_and_fallback_hold_edge_case(): void
    {
        // 1. Safety eval passing all injection & jailbreak suites (346.1 & 346.4)
        $evalPass = $this->service->runSafetyEvaluation(
            evalCode: 'EVAL-FINANCE-ASSISTANT-V2',
            agentId: 'AGENT-FIN-COPILOT',
            version: '2.0.0',
            injectionBlocked: true,
            jailbreakBlocked: true
        );
        $this->assertTrue((bool) $evalPass->eval_suite_passed);
        $this->assertFalse((bool) $evalPass->release_held_fallback_prior);

        // 2. Safety eval failing jailbreak holds release and keeps prior agent active (346.5 Edge Case)
        $evalFail = $this->service->runSafetyEvaluation(
            evalCode: 'EVAL-HR-COPILOT-V3',
            agentId: 'AGENT-HR-BOT',
            version: '3.0.0',
            injectionBlocked: true,
            jailbreakBlocked: false // Jailbreak leaked!
        );
        $this->assertFalse((bool) $evalFail->eval_suite_passed);
        $this->assertTrue((bool) $evalFail->release_held_fallback_prior);
    }

    public function test_tool_permission_expansion_ci_approval_guard(): void
    {
        // 1. Tool permission expansion without CI approval throws exception (346.2, 346.4, 346.6 Risk)
        try {
            $this->service->provisionToolPermission(
                permCode: 'PERM-DELETE-DATABASE-TOOL',
                agentId: 'AGENT-OPS-AUTOMATION',
                toolName: 'EXECUTE_DATABASE_DROP_PARTITION',
                isExpansion: true, // Permission expansion!
                ciApprovalGranted: false // No CI approval!
            );
            $this->fail('Expected exception for unapproved tool expansion');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Agent tool permission expansion requires mandatory CI change approval', $e->getMessage());
        }

        // 2. Tool permission expansion with CI approval succeeds (346.4)
        $perm = $this->service->provisionToolPermission(
            permCode: 'PERM-READ-TELEMETRY-TOOL',
            agentId: 'AGENT-OPS-AUTOMATION',
            toolName: 'READ_MINE_HAUL_TELEMETRY',
            isExpansion: true,
            ciApprovalGranted: true
        );
        $this->assertTrue((bool) $perm->is_permission_expansion);
        $this->assertTrue((bool) $perm->ci_change_approval_granted);
        $this->assertTrue((bool) $perm->permission_active);
    }

    public function test_ai_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->runSafetyEvaluation('E-AUD', 'A1', '1.0', true, true);
        $this->service->provisionToolPermission('P-AUD', 'A1', 'TOOL1', false, true);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: active unapproved expansion
        DB::table('ai_agent_tool_permissions')->insert([
            'permission_code' => 'P-DEFECT-UNAPPROVED',
            'agent_identifier' => 'A2',
            'tool_name' => 'DANGEROUS_TOOL',
            'is_permission_expansion' => true,
            'ci_change_approval_granted' => false, // Discrepancy!
            'permission_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
