<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Integration\Application\Services\OrgDesignWorkforcePlanningService;
use Tests\TestCase;

/**
 * Fase 223 — SDM: Organization Design & Workforce Planning Tests
 *
 * Covers:
 *  (a) Org hierarchy and reporting line integrity
 *  (b) Headcount budget check rejecting over-budget requests
 *  (c) Edge Case 223.6: Sudden hiring freeze holds requisition safely with reason
 *  (d) Critical position succession coverage & single-point-of-failure detection
 *  (e) Quality audit hcm:audit clean with 0 discrepancies
 */
class OrgDesignWorkforcePlanningTest extends TestCase
{
    use RefreshDatabase;

    protected OrgDesignWorkforcePlanningService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(OrgDesignWorkforcePlanningService::class);
    }

    /**
     * (a) Position creation and reporting line hierarchy.
     */
    public function test_position_hierarchy_and_reporting(): void
    {
        $vp = $this->service->createPosition(
            'POS-VP-ENG',
            'TELCO',
            'ID',
            'VP of Engineering',
            'EXEC',
            null,
            1,
            true
        );
        $this->assertSame('POS-VP-ENG', $vp->position_code);

        $mgr = $this->service->createPosition(
            'POS-MGR-NET',
            'TELCO',
            'ID',
            'Network Engineering Manager',
            'L4',
            'POS-VP-ENG',
            2,
            false
        );
        $this->assertSame('POS-VP-ENG', $mgr->parent_position_code);

        // Attempting to set self as parent -> Throws exception
        try {
            $this->service->updateReportingLine('POS-MGR-NET', 'POS-MGR-NET');
            $this->fail('Expected exception for self-reporting loop.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('cannot report to itself', $e->getMessage());
        }
    }

    /**
     * (b) Headcount governance rejects over-budget requisitions (223.4 & 223.5).
     */
    public function test_headcount_budget_enforcement(): void
    {
        $pos = $this->service->createPosition(
            'POS-DOC-ICU',
            'HEALTHCARE',
            'ID',
            'ICU Specialist Physician',
            'L4',
            null,
            2, // Budgeted 2
            true
        );

        // Requisition for 2 -> OK
        $req1 = $this->service->createRequisition('POS-DOC-ICU', 2, 80000000);
        $this->assertSame('PENDING', $req1->status);

        // Fulfill req1
        $this->service->fulfillRequisition($req1->requisition_code);

        // Requisition for 1 more (would make total 3 > 2) -> Exception
        try {
            $this->service->createRequisition('POS-DOC-ICU', 1, 40000000);
            $this->fail('Expected exception for over-budget headcount requisition.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('exceeds budgeted limit', $e->getMessage());
        }
    }

    /**
     * (c) Edge Case 223.6: Sudden hiring freeze holds requisition with reason, keeping pipeline.
     */
    public function test_sudden_hiring_freeze(): void
    {
        $pos = $this->service->createPosition(
            'POS-MIN-ENG',
            'MINING',
            'ID',
            'Mining Site Geologist',
            'L3',
            null,
            5,
            false
        );

        $req = $this->service->createRequisition('POS-MIN-ENG', 1, 35000000);
        $this->assertSame('PENDING', $req->status);

        // Apply sudden freeze to MINING
        $this->service->applyHiringFreeze('MINING', 'Q4 Capital Reprioritization');

        // New requisition on frozen position directly enters FROZEN_HELD
        $req2 = $this->service->createRequisition('POS-MIN-ENG', 1, 35000000);
        $this->assertSame('FROZEN_HELD', $req2->status);
        $this->assertStringContainsString('hiring freeze', $req2->frozen_reason);

        // Fulfilling frozen requisition is prevented
        try {
            $this->service->fulfillRequisition($req2->requisition_code);
            $this->fail('Expected exception for fulfilling frozen requisition.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('under hiring freeze', $e->getMessage());
        }
    }

    /**
     * (d) Succession coverage and single-point-of-failure detection (223.3 & 223.5).
     */
    public function test_succession_coverage_and_spof_detection(): void
    {
        $pos1 = $this->service->createPosition('POS-CEO', 'HOLDING', 'ID', 'Chief Executive', 'EXEC', null, 1, true);
        $pos2 = $this->service->createPosition('POS-CTO', 'HOLDING', 'ID', 'Chief Technology Officer', 'EXEC', null, 1, true);

        // Assign successor to CEO only
        $this->service->addSuccessor('POS-CEO', 'EMP-EXEC-99', 'READY_NOW');

        $metrics = $this->service->getSuccessionMetrics();
        $this->assertSame(2, $metrics['total_critical_positions']);
        $this->assertSame(1, $metrics['covered_positions']);
        $this->assertEquals(50.0, $metrics['coverage_pct']);
        $this->assertContains('POS-CTO', $metrics['single_point_of_failures']);
    }

    /**
     * (e) Audit status healthy with 0 discrepancies.
     */
    public function test_hcm_audit(): void
    {
        $audit = $this->service->audit();
        $this->assertSame('HEALTHY', $audit['status']);
        $this->assertSame(0, $audit['discrepancy_count']);
    }
}
