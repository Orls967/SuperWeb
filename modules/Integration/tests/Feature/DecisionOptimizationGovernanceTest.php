<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\DecisionOptimizationGovernanceService;
use Tests\TestCase;

class DecisionOptimizationGovernanceTest extends TestCase
{
    use RefreshDatabase;

    protected DecisionOptimizationGovernanceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(DecisionOptimizationGovernanceService::class);
    }

    public function test_problem_registration_and_solver_deployment_permission(): void
    {
        // 1. Unapproved problem cannot deploy solver (348.1 & 348.4)
        $unapproved = $this->service->registerOptimizationProblem(
            problemCode: 'OPT-HAUL-DISPATCH-V1',
            domainName: 'MINING',
            solverVersion: 'v1.4-simplex',
            owner: 'Head of Mine Planning',
            governanceApproved: false // Not approved!
        );
        $this->assertFalse((bool) $unapproved->solver_deployment_permitted);

        // Cannot audit recommendations for unpermitted problem (348.4)
        try {
            $this->service->auditRecommendation(
                recCode: 'REC-DISPATCH-001',
                problemCode: 'OPT-HAUL-DISPATCH-V1',
                recommendedValue: 50.0,
                minBound: 10.0,
                maxBound: 100.0
            );
            $this->fail('Expected exception for unpermitted problem solver execution');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Cannot audit recommendations for unregistered or unapproved problem', $e->getMessage());
        }

        // 2. Approved problem enables solver deployment (348.1 & 348.4)
        $approved = $this->service->registerOptimizationProblem(
            problemCode: 'OPT-HAUL-DISPATCH-V2',
            domainName: 'MINING',
            solverVersion: 'v2.0-gurobi',
            owner: 'Head of Mine Planning',
            governanceApproved: true
        );
        $this->assertTrue((bool) $approved->solver_deployment_permitted);
    }

    public function test_extreme_recommendation_sanity_bounds_edge_case(): void
    {
        // Setup approved problem
        $this->service->registerOptimizationProblem(
            problemCode: 'OPT-FURNACE-TEMP-CONTROL',
            domainName: 'SMELTER',
            solverVersion: 'v3.1-mpc',
            owner: 'VP Smelter Operations',
            governanceApproved: true
        );

        // 1. Extreme recommendation outside safety bounds throws exception and blocks (348.5 Edge Case)
        try {
            $this->service->auditRecommendation(
                recCode: 'REC-FURNACE-TEMP-SPIKE',
                problemCode: 'OPT-FURNACE-TEMP-CONTROL',
                recommendedValue: 1850.0, // > 1600 max bound!
                minBound: 1100.0,
                maxBound: 1600.0
            );
            $this->fail('Expected exception for extreme recommendation exceeding bounds');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Solver produced extreme recommendation (1850) outside safe bounds', $e->getMessage());
        }

        // Verify blocked audit record exists
        $blocked = DB::table('decision_optimization_recommendation_audits')->where('recommendation_code', 'REC-FURNACE-TEMP-SPIKE')->first();
        $this->assertNotNull($blocked);
        $this->assertTrue((bool) $blocked->is_extreme_recommendation);
        $this->assertTrue((bool) $blocked->recommendation_blocked);

        // 2. Safe recommendation within bounds succeeds (348.5)
        $safeRec = $this->service->auditRecommendation(
            recCode: 'REC-FURNACE-TEMP-SAFE',
            problemCode: 'OPT-FURNACE-TEMP-CONTROL',
            recommendedValue: 1350.0, // Within [1100, 1600]
            minBound: 1100.0,
            maxBound: 1600.0
        );
        $this->assertFalse((bool) $safeRec->is_extreme_recommendation);
        $this->assertFalse((bool) $safeRec->recommendation_blocked);
    }

    public function test_ai_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->registerOptimizationProblem('OPT-AUD', 'DOMAIN', 'v1', 'Owner', true);
        $this->service->auditRecommendation('REC-AUD', 'OPT-AUD', 50.0, 10.0, 100.0);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: unblocked extreme recommendation
        DB::table('decision_optimization_recommendation_audits')->insert([
            'recommendation_code' => 'REC-DEFECT-UNBLOCKED',
            'problem_code' => 'OPT-AUD',
            'recommended_value' => 9999.0,
            'safety_min_bound' => 10.0,
            'safety_max_bound' => 100.0,
            'is_extreme_recommendation' => true,
            'recommendation_blocked' => false, // Discrepancy!
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
