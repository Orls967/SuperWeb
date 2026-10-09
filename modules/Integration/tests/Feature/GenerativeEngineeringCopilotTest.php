<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\GenerativeEngineeringCopilotService;
use Tests\TestCase;

class GenerativeEngineeringCopilotTest extends TestCase
{
    use RefreshDatabase;

    protected GenerativeEngineeringCopilotService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(GenerativeEngineeringCopilotService::class);
    }

    public function test_design_copilot_fto_ip_review_edge_case(): void
    {
        // 1. Component failing FTO review throws exception (352.5 Edge Case)
        try {
            $this->service->adoptDesignEco(
                ecoCode: 'ECO-PATENT-INFRINGING-01',
                componentName: 'Proprietary Induction Smelting Nozzle',
                passedFto: false, // FTO failed!
                engineerValidated: true
            );
            $this->fail('Expected exception for component failing FTO review');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('failed Freedom-To-Operate (FTO) IP review', $e->getMessage());
        }

        // 2. Component not validated by engineer throws exception (352.4)
        try {
            $this->service->adoptDesignEco(
                ecoCode: 'ECO-UNVALIDATED-02',
                componentName: 'Haul Truck Suspension Bracket',
                passedFto: true,
                engineerValidated: false // Not validated!
            );
            $this->fail('Expected exception for unvalidated design suggestion');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Design suggestion must be validated by licensed engineer', $e->getMessage());
        }

        // 3. Fully compliant design adopted into ECO workflow (352.1 & 352.4)
        $eco = $this->service->adoptDesignEco(
            ecoCode: 'ECO-APPROVED-BRACKET-03',
            componentName: 'Lightweight Haul Suspension Arm',
            passedFto: true,
            engineerValidated: true
        );
        $this->assertTrue((bool) $eco->passed_fto_ip_review);
        $this->assertTrue((bool) $eco->engineer_reviewed_and_validated);
        $this->assertTrue((bool) $eco->eco_workflow_adopted);
    }

    public function test_code_copilot_direct_write_prohibition_and_ci_gate(): void
    {
        // 1. Direct production write attempt throws exception (352.2)
        try {
            $this->service->mergeCodeProposal(
                proposalCode: 'PROP-DIRECT-WRITE',
                repoName: 'autoserve-backend',
                humanApproved: true,
                ciPassed: true,
                directWriteAttempt: true // Direct write attempt!
            );
            $this->fail('Expected exception for direct production write');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Copilot direct production writes are strictly prohibited', $e->getMessage());
        }

        // 2. Code lacking CI pass throws exception (352.4 & 352.6)
        try {
            $this->service->mergeCodeProposal(
                proposalCode: 'PROP-CI-FAILED',
                repoName: 'autoserve-backend',
                humanApproved: true,
                ciPassed: false, // CI failed!
                directWriteAttempt: false
            );
            $this->fail('Expected exception for unverified CI code');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Code proposal cannot merge without human approval and passing CI tests', $e->getMessage());
        }

        // 3. Compliant proposal merges successfully (352.2 & 352.4)
        $code = $this->service->mergeCodeProposal(
            proposalCode: 'PROP-MERGE-CLEAN',
            repoName: 'autoserve-backend',
            humanApproved: true,
            ciPassed: true,
            directWriteAttempt: false
        );
        $this->assertTrue((bool) $code->human_approved);
        $this->assertTrue((bool) $code->ci_tests_passed);
        $this->assertTrue((bool) $code->merged_to_production);
    }

    public function test_ai_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->adoptDesignEco('ECO-AUD', 'PART', true, true);
        $this->service->mergeCodeProposal('PROP-AUD', 'REPO', true, true);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: merged code that failed CI
        DB::table('copilot_code_generation_proposals')->insert([
            'proposal_code' => 'PROP-DEFECT-NO-CI',
            'repository_name' => 'REPO',
            'human_approved' => true,
            'ci_tests_passed' => false, // Discrepancy!
            'direct_production_write_attempted' => false,
            'merged_to_production' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
