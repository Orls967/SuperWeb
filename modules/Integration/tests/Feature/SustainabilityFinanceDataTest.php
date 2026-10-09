<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\SustainabilityFinanceDataService;
use Tests\TestCase;

class SustainabilityFinanceDataTest extends TestCase
{
    use RefreshDatabase;

    protected SustainabilityFinanceDataService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(SustainabilityFinanceDataService::class);
    }

    public function test_unique_evidence_id_prevents_double_counting_edge_case(): void
    {
        // 1. First claim with unique evidence ID succeeds (380.2 & 380.4)
        $first = $this->service->recordEvidenceClaim(
            uniqueEvidenceId: 'EVD-CARBON-VERRA-2026-99',
            claimType: 'CARBON_CREDIT',
            metricValue: 1500.50
        );
        $this->assertEquals('EVD-CARBON-VERRA-2026-99', $first->unique_evidence_id);
        $this->assertTrue((bool) $first->double_counting_prevented);

        // 2. Second claim with duplicate evidence ID is rejected (380.2, 380.4, 380.5 Edge Case)
        try {
            $this->service->recordEvidenceClaim(
                uniqueEvidenceId: 'EVD-CARBON-VERRA-2026-99', // Duplicate!
                claimType: 'CARBON_CREDIT',
                metricValue: 1500.50
            );
            $this->fail('Expected exception for duplicate sustainability evidence claim');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('duplicate claim rejected', $e->getMessage());
        }
    }

    public function test_transition_plan_linked_to_capex_and_ledger_risk(): void
    {
        // 1. Unlinked transition plan fails (380.3 & 380.6 Risk)
        try {
            $this->service->registerTransitionPlan(
                planCode: 'PLAN-NETZERO-2030-01',
                allocatedCapexUsd: 5000000.00,
                emissionsAbatedMt: 12000.00,
                projectAssetLedgerLinked: false // Unlinked!
            );
            $this->fail('Expected exception for unlinked transition plan');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Transition plan capex must link to project asset ledger views', $e->getMessage());
        }

        // 2. Linked transition plan succeeds (380.3 & 380.4)
        $plan = $this->service->registerTransitionPlan(
            planCode: 'PLAN-NETZERO-2030-02',
            allocatedCapexUsd: 5000000.00,
            emissionsAbatedMt: 12000.00,
            projectAssetLedgerLinked: true
        );
        $this->assertTrue((bool) $plan->project_asset_ledger_linked);
    }

    public function test_esg_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->recordEvidenceClaim('E-AUD', 'REC', 500.0);
        $this->service->registerTransitionPlan('P-AUD', 100000.0, 50.0, true);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: unlinked transition plan
        DB::table('global_sustainability_transition_plans')->insert([
            'plan_code' => 'P-DEFECT-UNLINKED',
            'allocated_capex_usd' => 200000.00,
            'emissions_abated_mt' => 80.00,
            'project_asset_ledger_linked' => false, // Discrepancy!
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
