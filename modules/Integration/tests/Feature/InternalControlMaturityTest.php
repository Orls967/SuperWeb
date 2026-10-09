<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\InternalControlMaturityService;
use Tests\TestCase;

class InternalControlMaturityTest extends TestCase
{
    use RefreshDatabase;

    protected InternalControlMaturityService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(InternalControlMaturityService::class);
    }

    public function test_control_registration_and_evaluation_flow(): void
    {
        $control = $this->service->registerControl(
            controlCode: 'CTRL-FIN-001',
            name: 'Dual Authorization Journal',
            frequency: 'continuous',
            owner: 'Head of Accounting',
            evidenceSource: 'Ledger Audit Log',
            designDoc: 'Requires 2 approvals for journals > 100M',
            testPlan: 'Sample 50 transactions and verify approvals',
            dependencies: ['LEDGER_CORE', 'AUTH_SERVICE']
        );

        $this->assertEquals('CTRL-FIN-001', $control->control_code);
        $this->assertEquals('none', $control->deficiency_rating);

        // Evaluation passes
        $eval = $this->service->evaluateControl(
            controlCode: 'CTRL-FIN-001',
            period: '2026-Q3',
            testPassed: true,
            isAutomated: true
        );

        $this->assertTrue((bool) $eval->passed);
        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);
    }

    public function test_manual_control_requires_dual_review_risk(): void
    {
        $this->service->registerControl(
            controlCode: 'CTRL-MANUAL-002',
            name: 'Physical Warehouse Stock Opname',
            frequency: 'monthly',
            owner: 'Warehouse Lead',
            evidenceSource: 'Physical Count Sheet',
            designDoc: 'Floor count matching system stock',
            testPlan: 'Random check 10 SKUs'
        );

        // 401.6 Risk: Manual control missing reviewer should throw exception
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Manual control requires dual review');

        $this->service->evaluateControl(
            controlCode: 'CTRL-MANUAL-002',
            period: '2026-10',
            testPassed: true,
            isAutomated: false,
            attestationBy: 'Staff A',
            reviewedBy: null // missing reviewer!
        );
    }

    public function test_continuous_failure_triggers_redesign_edge_case(): void
    {
        $this->service->registerControl(
            controlCode: 'CTRL-FAIL-003',
            name: 'API Key Rotation',
            frequency: 'monthly',
            owner: 'Security Lead',
            evidenceSource: 'KMS Log',
            designDoc: 'Rotate every 30 days',
            testPlan: 'Verify KMS event'
        );

        // Fail 1st time
        $this->service->evaluateControl('CTRL-FAIL-003', '2026-07', false, true);
        // Fail 2nd time
        $this->service->evaluateControl('CTRL-FAIL-003', '2026-08', false, true);
        
        $ctrl = DB::table('gov_internal_controls')->where('control_code', 'CTRL-FAIL-003')->first();
        $this->assertFalse((bool) $ctrl->requires_redesign);

        // Fail 3rd time -> 401.5 Edge case: triggers redesign requirement
        $this->service->evaluateControl('CTRL-FAIL-003', '2026-09', false, true);
        $ctrlAfter3 = DB::table('gov_internal_controls')->where('control_code', 'CTRL-FAIL-003')->first();
        $this->assertTrue((bool) $ctrlAfter3->requires_redesign);
        $this->assertEquals('material_weakness', $ctrlAfter3->deficiency_rating);

        // Audit shows discrepancy
        $audit = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $audit['status']);
        $this->assertEquals(1, $audit['discrepancy_count']);

        // Execute redesign
        $redesigned = $this->service->redesignControl(
            controlCode: 'CTRL-FAIL-003',
            newDesignDoc: 'Automated rotation daemon with AWS KMS hook',
            newTestPlan: 'Synthetic test daemon every 7 days'
        );

        $this->assertFalse((bool) $redesigned->requires_redesign);
        $this->assertEquals('none', $redesigned->deficiency_rating);
        $this->assertEquals('HEALTHY', $this->service->audit()['status']);
    }
}
