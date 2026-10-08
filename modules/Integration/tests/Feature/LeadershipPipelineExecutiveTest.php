<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\LeadershipPipelineExecutiveService;
use Tests\TestCase;

class LeadershipPipelineExecutiveTest extends TestCase
{
    use RefreshDatabase;

    protected LeadershipPipelineExecutiveService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(LeadershipPipelineExecutiveService::class);
    }

    public function test_leadership_role_creation_and_board_bench_alert(): void
    {
        // 1. Role registered with 0 successors immediately triggers board bench alert (318.3 & 318.6 Risk)
        $role = $this->service->registerLeadershipRole(
            roleCode: 'ROLE-CHIEF-OPERATIONS-OFFICER',
            title: 'Chief Operations Officer',
            tierLevel: 'C_SUITE',
            isCriticalRole: true
        );
        $this->assertEquals(0, $role->ready_successor_count);
        $this->assertTrue((bool) $role->board_bench_alert_triggered);

        // 2. Assess ready candidate (score 92.0 >= 85.0) which lifts bench alert (318.1 & 318.3)
        $readyCandidate = $this->service->assessCandidate(
            candidateCode: 'CAND-VP-OPS-BUDI',
            roleCode: 'ROLE-CHIEF-OPERATIONS-OFFICER',
            employeeId: 'EMP_EXEC_BUDI',
            readinessScore: 92.0
        );
        $this->assertTrue((bool) $readyCandidate->is_ready_now);
        $this->assertFalse((bool) $readyCandidate->requires_readiness_plan);

        $roleAfter = DB::table('leadership_pipeline_roles')->where('role_code', 'ROLE-CHIEF-OPERATIONS-OFFICER')->first();
        $this->assertEquals(1, $roleAfter->ready_successor_count);
        $this->assertFalse((bool) $roleAfter->board_bench_alert_triggered);
    }

    public function test_unready_candidate_mandates_readiness_plan_with_deadline(): void
    {
        $this->service->registerLeadershipRole('ROLE-DIR-SMELTER', 'Director of Smelter', 'DIRECTOR', true);

        // 1. Candidate score < 85% without plan deadline is rejected (318.5 Edge Case)
        try {
            $this->service->assessCandidate('CAND-UNREADY-01', 'ROLE-DIR-SMELTER', 'EMP_ENG_RATNA', 72.0, null);
            $this->fail('Expected exception for unready candidate without deadline');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('strictly requires a development plan with an explicit deadline', $e->getMessage());
        }

        // 2. Candidate score < 85% with deadline succeeds (318.5)
        $plannedCandidate = $this->service->assessCandidate('CAND-PLANNED-02', 'ROLE-DIR-SMELTER', 'EMP_ENG_RATNA', 72.0, '2027-06-30');
        $this->assertFalse((bool) $plannedCandidate->is_ready_now);
        $this->assertTrue((bool) $plannedCandidate->requires_readiness_plan);
        $this->assertEquals('2027-06-30', $plannedCandidate->readiness_plan_deadline);
    }

    public function test_hcm_leadership_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->registerLeadershipRole('ROLE-AUD', 'Lead', 'FIRST_LINE', true);
        $this->service->assessCandidate('CAND-AUD', 'ROLE-AUD', 'E1', 90.0);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: critical role with 0 successors and unalerted board
        DB::table('leadership_pipeline_roles')->insert([
            'role_code' => 'ROLE-DEFECT-SILENT',
            'title' => 'Critical VP',
            'tier_level' => 'DIRECTOR',
            'is_critical_role' => true,
            'ready_successor_count' => 0,
            'board_bench_alert_triggered' => false, // Discrepancy!
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
