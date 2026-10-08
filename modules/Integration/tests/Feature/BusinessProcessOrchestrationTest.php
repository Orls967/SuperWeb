<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\BusinessProcessOrchestrationService;
use Tests\TestCase;

class BusinessProcessOrchestrationTest extends TestCase
{
    use RefreshDatabase;

    protected BusinessProcessOrchestrationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(BusinessProcessOrchestrationService::class);
    }

    public function test_state_machine_invalid_transition_rejection(): void
    {
        // Start process in DRAFT state (367.1)
        $this->service->startProcess(
            processCode: 'PROC-CLAIM-ACCIDENT-01',
            workflowType: 'CLAIM',
            initialState: 'DRAFT'
        );

        // 1. Direct transition from DRAFT to APPROVED is invalid and rejected (367.4)
        try {
            $this->service->transitionState('PROC-CLAIM-ACCIDENT-01', 'APPROVED');
            $this->fail('Expected exception for invalid state transition DRAFT -> APPROVED');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Cannot transition from \'DRAFT\' to \'APPROVED\'', $e->getMessage());
        }

        // 2. Transition from DRAFT to REVIEW succeeds (367.1 & 367.4)
        $reviewed = $this->service->transitionState('PROC-CLAIM-ACCIDENT-01', 'REVIEW');
        $this->assertEquals('REVIEW', $reviewed->current_state);

        // 3. Transition from REVIEW to APPROVED succeeds (367.1 & 367.4)
        $approved = $this->service->transitionState('PROC-CLAIM-ACCIDENT-01', 'APPROVED');
        $this->assertEquals('APPROVED', $approved->current_state);
    }

    public function test_cross_domain_compensation_contract_bridge_edge_case(): void
    {
        // 1. Direct write without contract bridge/saga fails (367.4 & 367.5 Edge Case)
        try {
            $this->service->executeCrossDomainCompensation(
                sagaCode: 'SAGA-REFUND-PAYMENT-01',
                processCode: 'PROC-CLAIM-ACCIDENT-01',
                targetDomain: 'FINANCE',
                usesContractBridge: false // Direct db write attempt!
            );
            $this->fail('Expected exception for cross-domain compensation without contract bridge');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Cross-domain compensation must use contract bridge or saga', $e->getMessage());
        }

        // 2. Cross-domain compensation using contract bridge succeeds (367.5)
        $saga = $this->service->executeCrossDomainCompensation(
            sagaCode: 'SAGA-REFUND-PAYMENT-02',
            processCode: 'PROC-CLAIM-ACCIDENT-01',
            targetDomain: 'FINANCE',
            usesContractBridge: true
        );
        $this->assertTrue((bool) $saga->uses_contract_bridge);
        $this->assertTrue((bool) $saga->compensation_executed);
    }

    public function test_workflow_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->startProcess('P-AUD', 'PO', 'DRAFT');
        $this->service->executeCrossDomainCompensation('S-AUD', 'P-AUD', 'FINANCE', true);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: unbridged compensation executed
        DB::table('platform_process_cross_domain_sagas')->insert([
            'saga_code' => 'S-DEFECT-UNBRIDGED',
            'process_code' => 'P-AUD',
            'target_domain' => 'INVENTORY',
            'uses_contract_bridge' => false, // Discrepancy!
            'compensation_executed' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
