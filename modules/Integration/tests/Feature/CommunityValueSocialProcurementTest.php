<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\CommunityValueSocialProcurementService;
use Tests\TestCase;

class CommunityValueSocialProcurementTest extends TestCase
{
    use RefreshDatabase;

    protected CommunityValueSocialProcurementService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(CommunityValueSocialProcurementService::class);
    }

    public function test_supplier_development_milestone_and_tender_eligibility(): void
    {
        // 1. Supplier with verified milestone evidence unlocks tender eligibility (335.1 & 335.4)
        $prog = $this->service->verifyLocalSupplierMilestone(
            programCode: 'PROG-LOCAL-WELDING-01',
            supplierId: 'SUP-LOCAL-PAPUA-FABRICATION',
            regionCode: 'TIMIKA_PAPUA',
            hasMilestoneEvidence: true,
            hasMeasurableOutcome: true
        );
        $this->assertTrue((bool) $prog->has_objective_milestone_evidence);
        $this->assertTrue((bool) $prog->tender_eligibility_unlocked);
        $this->assertEquals('MEASURED_IMPACT', $prog->program_classification);

        // 2. Program without measurable outcomes is strictly classified as ACTIVITY_ONLY (335.5 Edge Case)
        $unmeasured = $this->service->verifyLocalSupplierMilestone(
            programCode: 'PROG-GENERAL-SEMINAR-02',
            supplierId: 'SUP-LOCAL-CATERING',
            regionCode: 'TIMIKA_PAPUA',
            hasMilestoneEvidence: false,
            hasMeasurableOutcome: false // No measurable outcome!
        );
        $this->assertFalse((bool) $unmeasured->tender_eligibility_unlocked);
        $this->assertEquals('ACTIVITY_ONLY', $unmeasured->program_classification);
    }

    public function test_grievance_closure_requires_community_representative_confirmation(): void
    {
        // 1. Grievance closure without community rep confirmation throws exception (335.3 & 335.4)
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Social grievance violation: Closure strictly requires independent confirmation');
        $this->service->closeCommunityGrievance(
            grievanceCode: 'GRIEV-DUST-ROAD-01',
            communityId: 'VILLAGE-BANTI-PAPUA',
            remedyBudgetUsd: 15000.0,
            remedyImplemented: true,
            confirmedByCommunityRep: false // Not confirmed!
        );
    }

    public function test_grievance_closure_success_with_confirmation(): void
    {
        // Fully verified grievance closure (335.3 & 335.4)
        $closure = $this->service->closeCommunityGrievance(
            grievanceCode: 'GRIEV-WATER-CANAL-02',
            communityId: 'VILLAGE-WAA-BONAL',
            remedyBudgetUsd: 25000.0,
            remedyImplemented: true,
            confirmedByCommunityRep: true
        );
        $this->assertTrue((bool) $closure->confirmed_by_community_rep);
        $this->assertTrue((bool) $closure->is_closed);
    }

    public function test_esg_community_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->verifyLocalSupplierMilestone('P-AUD', 'S1', 'R1', true, true);
        $this->service->closeCommunityGrievance('G-AUD', 'C1', 1000.0, true, true);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: closed grievance without community confirmation
        DB::table('community_grievance_remediations')->insert([
            'grievance_code' => 'G-DEFECT-UNCONFIRMED',
            'community_id' => 'C1',
            'remedy_budget_usd' => 5000.0,
            'remedy_implemented' => true,
            'confirmed_by_community_rep' => false, // Discrepancy!
            'is_closed' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
