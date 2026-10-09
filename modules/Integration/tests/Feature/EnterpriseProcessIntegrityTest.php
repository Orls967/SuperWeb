<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\Integration\Application\Services\EnterpriseProcessIntegrityService;
use Tests\TestCase;

class EnterpriseProcessIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected EnterpriseProcessIntegrityService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(EnterpriseProcessIntegrityService::class);
    }

    public function test_enterprise_process_mapping_and_execution_flow(): void
    {
        // 455.1 Map Procure-to-Pay process
        $proc = $this->service->mapProcess(
            processCode: 'P2P-GLOBAL',
            name: 'Cross-Border Procurement to Payment',
            systemOfRecord: 'SAP_S4HANA_LEDGER',
            owner: 'Head of Strategic Procurement',
            segregationOfDuties: true
        );

        $this->assertEquals('P2P-GLOBAL', $proc->process_code);

        // 455.2 Execute valid process step with distinct creator and approver
        $executed = $this->service->executeProcessStep('P2P-GLOBAL', 'BUYER-001', 'MANAGER-002');
        $this->assertTrue($executed);

        // 455.3 Flag deviation and resolve it
        $dev = $this->service->flagDeviation(
            deviationCode: 'DEV-PO-MATCH-01',
            processCode: 'P2P-GLOBAL',
            severity: 'medium',
            details: 'Line item unit price deviated by 0.5% from master catalog'
        );

        $this->assertEquals('flagged', $dev->status);
        $this->assertNotNull($dev->corrective_action_ticket);

        $resolved = $this->service->resolveDeviation('DEV-PO-MATCH-01');
        $this->assertEquals('resolved', $resolved->status);

        // 455.4 Audit clean
        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);
    }

    public function test_unmapped_process_and_segregation_of_duties_blocked_edge_cases(): void
    {
        // 455.5 Edge case: Unmapped process execution is blocked
        try {
            $this->service->executeProcessStep('SHADOW-PROCESS-01', 'USER-A', 'USER-B');
            $this->fail('Expected exception for unmapped process');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Unmapped business process \'SHADOW-PROCESS-01\' must be formally registered', $e->getMessage());
        }

        // 455.2 Segregation of duties: Creator approving own transaction is blocked
        $this->service->mapProcess('OTC-SALES', 'Order to Cash', 'CRM_CORE', 'Sales VP', true);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Segregation of duties breached! Creator \'USER-SELF\' cannot approve own');

        $this->service->executeProcessStep('OTC-SALES', 'USER-SELF', 'USER-SELF');
    }
}
