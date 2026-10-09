<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\ArchitectureFitnessMonolithHealthService;
use Tests\TestCase;

class ArchitectureFitnessMonolithHealthTest extends TestCase
{
    use RefreshDatabase;

    protected ArchitectureFitnessMonolithHealthService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ArchitectureFitnessMonolithHealthService::class);
    }

    public function test_fitness_function_violation_blocks_ci_merge_edge_case(): void
    {
        // 1. Cross-module direct persistence access fails fitness gate and blocks CI merge (370.2, 370.4, 370.5 Edge Case)
        try {
            $this->service->evaluateArchitectureFitness(
                evaluationCode: 'FIT-INTEGRATION-VIOLATION-01',
                moduleName: 'Commerce',
                hasCrossModulePersistenceViolation: true, // Violation!
                allTablesOwned: true
            );
            $this->fail('Expected exception for cross-module persistence violation in fitness function');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Cross-module persistence violation in \'Commerce\' blocks CI merge', $e->getMessage());
        }

        // Verify rejected record exists
        $rejected = DB::table('platform_architecture_fitness_evaluations')->where('evaluation_code', 'FIT-INTEGRATION-VIOLATION-01')->first();
        $this->assertNotNull($rejected);
        $this->assertFalse((bool) $rejected->fitness_gate_passed);
        $this->assertFalse((bool) $rejected->ci_merge_permitted);

        // 2. Clean modular boundaries pass fitness gate and permit merge (370.1 & 370.4)
        $clean = $this->service->evaluateArchitectureFitness(
            evaluationCode: 'FIT-INTEGRATION-CLEAN-02',
            moduleName: 'Mining',
            hasCrossModulePersistenceViolation: false,
            allTablesOwned: true
        );
        $this->assertTrue((bool) $clean->fitness_gate_passed);
        $this->assertTrue((bool) $clean->ci_merge_permitted);
    }

    public function test_quarterly_coupling_review_recorded_risk(): void
    {
        // Quarterly architecture review is tracked (370.6 Risk)
        $review = $this->service->recordQuarterlyCouplingReview(
            reviewCode: 'REV-ARCH-2026-Q1',
            quarterCode: 'Q1-2026'
        );
        $this->assertTrue((bool) $review->coupling_review_completed);
        $this->assertEquals('Q1-2026', $review->quarter_code);
    }

    public function test_arch_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->evaluateArchitectureFitness('F-AUD', 'CORE', false, true);
        $this->service->recordQuarterlyCouplingReview('Q-AUD', 'Q1-2026');

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: violation permitted to merge
        DB::table('platform_architecture_fitness_evaluations')->insert([
            'evaluation_code' => 'F-DEFECT-MERGED',
            'module_name' => 'DEFECT_MODULE',
            'has_cross_module_persistence_violation' => true, // Discrepancy!
            'all_tables_owned_by_domain' => true,
            'fitness_gate_passed' => true, // Discrepancy!
            'ci_merge_permitted' => true, // Discrepancy!
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
