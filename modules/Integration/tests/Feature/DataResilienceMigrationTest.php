<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\DataResilienceMigrationService;
use Tests\TestCase;

class DataResilienceMigrationTest extends TestCase
{
    use RefreshDatabase;

    protected DataResilienceMigrationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(DataResilienceMigrationService::class);
    }

    public function test_schema_change_proposal_compatibility_and_backfill_gates(): void
    {
        // 1. Incompatible schema migration rejected (343.4)
        try {
            $this->service->submitSchemaMigrationProposal(
                proposalCode: 'PROP-DROP-COLUMN-INCOMPATIBLE',
                tableName: 'customer_orders',
                isBackwardCompatible: false, // Incompatible!
                hasBackfillPlan: true
            );
            $this->fail('Expected exception for incompatible schema proposal');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Backward-incompatible migration proposal blocked', $e->getMessage());
        }

        // 2. Migration without backfill plan rejected (343.6 Risk)
        try {
            $this->service->submitSchemaMigrationProposal(
                proposalCode: 'PROP-NO-BACKFILL',
                tableName: 'ledger_entries',
                isBackwardCompatible: true,
                hasBackfillPlan: false // No backfill plan!
            );
            $this->fail('Expected exception for missing backfill plan');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('rejected due to absence of backfill execution plan', $e->getMessage());
        }

        // 3. Fully compliant proposal passes CI gate (343.2 & 343.4)
        $proposal = $this->service->submitSchemaMigrationProposal(
            proposalCode: 'PROP-ADD-INDEX-COMPLIANT',
            tableName: 'invoice_records',
            isBackwardCompatible: true,
            hasBackfillPlan: true
        );
        $this->assertTrue((bool) $proposal->is_backward_compatible);
        $this->assertTrue((bool) $proposal->has_backfill_plan);
        $this->assertTrue((bool) $proposal->ci_gate_approved);
    }

    public function test_restore_drill_certification_and_failure_blocker_edge_case(): void
    {
        // 1. Successful restore drill meeting RTO certifies production DR (343.3 & 343.4)
        $drillSuccess = $this->service->executeRestoreDrill(
            drillCode: 'DRILL-POSTGRES-PRIMARY-01',
            clusterName: 'CLUSTER-CORE-POSTGRES',
            measuredRtoMinutes: 42.5, // 42.5 <= 60 target
            validationPassed: true,
            targetRtoMinutes: 60.0
        );
        $this->assertTrue((bool) $drillSuccess->validation_suite_passed);
        $this->assertFalse((bool) $drillSuccess->is_blocker_failure);
        $this->assertTrue((bool) $drillSuccess->certified_for_production_dr);

        // 2. Drill failing validation or exceeding RTO is flagged as blocker (343.5 Edge Case)
        $drillFailure = $this->service->executeRestoreDrill(
            drillCode: 'DRILL-ANALYTICS-FAIL-02',
            clusterName: 'CLUSTER-BIGDATA-LAKE',
            measuredRtoMinutes: 95.0, // 95 > 60 target!
            validationPassed: false, // Validation failed!
            targetRtoMinutes: 60.0
        );
        $this->assertTrue((bool) $drillFailure->is_blocker_failure);
        $this->assertFalse((bool) $drillFailure->certified_for_production_dr);
    }

    public function test_data_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->submitSchemaMigrationProposal('PROP-AUD', 'TABLE', true, true);
        $this->service->executeRestoreDrill('DRILL-AUD', 'CLUSTER', 30.0, true, 60.0);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: unapproved backfill marked CI approved
        DB::table('schema_change_governance_proposals')->insert([
            'proposal_code' => 'PROP-DEFECT-UNPLANNED',
            'table_name' => 'PAYMENTS',
            'is_backward_compatible' => true,
            'has_backfill_plan' => false, // Discrepancy!
            'ci_gate_approved' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
