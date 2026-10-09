<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\AgentMarketplaceGovernedToolsService;
use Tests\TestCase;

class AgentMarketplaceGovernedToolsTest extends TestCase
{
    use RefreshDatabase;

    protected AgentMarketplaceGovernedToolsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(AgentMarketplaceGovernedToolsService::class);
    }

    public function test_unregistered_agent_run_prohibition_and_secret_masking(): void
    {
        // 1. Unregistered agent cannot run (358.4)
        try {
            $this->service->executeGovernedTool(
                executionCode: 'EXEC-UNREG-01',
                agentCode: 'AGENT-SHADOW-BOT',
                toolName: 'INTERNAL_QUOTE_TOOL',
                toolPermissionGranted: true
            );
            $this->fail('Expected exception for unregistered agent execution');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Unregistered agent \'AGENT-SHADOW-BOT\' cannot execute tools', $e->getMessage());
        }

        // 2. Secret leakage detection triggers block (358.3 & 358.6 Risk)
        $this->service->registerAgent('AGENT-BILLING-AUDITOR', 'Billing Bot', 'LOW');

        try {
            $this->service->executeGovernedTool(
                executionCode: 'EXEC-SECRET-LEAK-02',
                agentCode: 'AGENT-BILLING-AUDITOR',
                toolName: 'INTERNAL_QUOTE_TOOL',
                toolPermissionGranted: true,
                secretExposed: true // Secret exposed!
            );
            $this->fail('Expected exception for secret exposure');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Secrets leaked to agent context detected and blocked', $e->getMessage());
        }

        // 3. Compliant execution passes (358.4)
        $exec = $this->service->executeGovernedTool(
            executionCode: 'EXEC-CLEAN-03',
            agentCode: 'AGENT-BILLING-AUDITOR',
            toolName: 'INTERNAL_QUOTE_TOOL',
            toolPermissionGranted: true,
            secretExposed: false
        );
        $this->assertTrue((bool) $exec->execution_permitted);
    }

    public function test_quarantined_agent_behavioral_regression_edge_case(): void
    {
        $this->service->registerAgent('AGENT-HAUL-NAV', 'Autonomous Haul Bot', 'CRITICAL');

        // Quarantine agent due to unexpected behavior (358.5 Edge Case)
        $quarantined = $this->service->quarantineAgent('AGENT-HAUL-NAV', 'Anomalous deviation in waypoint calculation');
        $this->assertTrue((bool) $quarantined->is_quarantined);

        // Attempting to execute tool while quarantined fails (358.5)
        try {
            $this->service->executeGovernedTool(
                executionCode: 'EXEC-QUARANTINED-04',
                agentCode: 'AGENT-HAUL-NAV',
                toolName: 'HAUL_DISPATCH_TOOL',
                toolPermissionGranted: true
            );
            $this->fail('Expected exception for quarantined agent execution');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Quarantined agent \'AGENT-HAUL-NAV\' cannot execute tools', $e->getMessage());
        }
    }

    public function test_ai_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->registerAgent('A-AUD', 'Name', 'LOW');
        $this->service->executeGovernedTool('E-AUD', 'A-AUD', 'TOOL1', true, false);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: execution with secret exposed
        DB::table('agent_marketplace_tool_executions')->insert([
            'execution_code' => 'E-DEFECT-SECRET',
            'agent_code' => 'A-AUD',
            'tool_name' => 'TOOL_DEFECT',
            'tool_permission_granted' => true,
            'secrets_exposed_to_context' => true, // Discrepancy!
            'execution_permitted' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
