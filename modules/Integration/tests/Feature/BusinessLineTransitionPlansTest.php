<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\BusinessLineTransitionPlansService;
use Tests\TestCase;

class BusinessLineTransitionPlansTest extends TestCase
{
    use RefreshDatabase;

    protected BusinessLineTransitionPlansService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(BusinessLineTransitionPlansService::class);
    }

    public function test_line_transition_plans_and_consolidated_trajectory_math(): void
    {
        // 1. Plan for Line Mining: 100,000 tCO2e abatement (333.1 & 333.4)
        $this->service->registerTransitionPlan(
            planCode: 'PLAN-MINE-DECARB-2030',
            lineCode: 'LINE_MINING',
            baselineEmissionsTco2e: 500000.0,
            targetAbatementTco2e: 100000.0,
            allocatedCapexUsd: 25000000.0,
            hasFeasiblePathway: true
        );

        // 2. Plan for Line Smelter: 150,000 tCO2e abatement (333.1 & 333.4)
        $this->service->registerTransitionPlan(
            planCode: 'PLAN-SMELTER-RENEWABLE-2030',
            lineCode: 'LINE_SMELTER',
            baselineEmissionsTco2e: 800000.0,
            targetAbatementTco2e: 150000.0,
            allocatedCapexUsd: 40000000.0,
            hasFeasiblePathway: true
        );

        // Consolidated abatement target must equal sum: 100,000 + 150,000 = 250,000 tCO2e (333.4)
        $this->assertEquals(250000.0, $this->service->getConsolidatedAbatementTarget());
    }

    public function test_infeasible_pathway_edge_case_and_milestone_escalation(): void
    {
        // 1. Decarbonization claim without feasible pathway throws exception (333.5 Edge Case)
        try {
            $this->service->registerTransitionPlan(
                planCode: 'PLAN-UNFEASIBLE-COAL',
                lineCode: 'LINE_COAL_FIRED_BOILER',
                baselineEmissionsTco2e: 300000.0,
                targetAbatementTco2e: 150000.0,
                allocatedCapexUsd: 1000000.0,
                hasFeasiblePathway: false // Infeasible!
            );
            $this->fail('Expected exception for unfeasible decarbonization claim');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('cannot claim carbon transition targets without a feasible engineering decarbonization roadmap', $e->getMessage());
        }

        // 2. Missed milestone triggers automatic board escalation (333.3 & 333.4)
        $this->service->registerTransitionPlan('PLAN-LOGISTICS', 'LINE_LOGISTICS', 100000.0, 20000.0, 5000000.0, true);
        $milestone = $this->service->recordTransitionMilestone(
            milestoneCode: 'MS-EV-TRUCK-PILOT-01',
            planCode: 'PLAN-LOGISTICS',
            title: 'Deploy first 20 heavy EV haulage trucks',
            dueDate: '2026-12-31',
            status: 'DELAYED_MISSED'
        );
        $this->assertTrue((bool) $milestone->board_escalation_triggered);
    }

    public function test_esg_transition_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->registerTransitionPlan('PLAN-AUD', 'LINE-AUD', 1000.0, 200.0, 5000.0, true);
        $this->service->recordTransitionMilestone('MS-AUD', 'PLAN-AUD', 'Solar install', '2026-11-01', 'COMPLETED');

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: delayed milestone without board escalation
        DB::table('transition_milestone_progress_trackers')->insert([
            'milestone_code' => 'MS-DEFECT-UNESCALATED',
            'plan_code' => 'PLAN-AUD',
            'milestone_title' => 'Critical grid tie-in',
            'due_date' => '2026-10-01',
            'status' => 'DELAYED_MISSED',
            'board_escalation_triggered' => false, // Discrepancy!
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
