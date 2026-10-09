<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\GovInternalAuditAssuranceService;
use Tests\TestCase;

class GovInternalAuditAssuranceTest extends TestCase
{
    use RefreshDatabase;

    protected GovInternalAuditAssuranceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(GovInternalAuditAssuranceService::class);
    }

    public function test_deterministic_reproducible_sample_selection(): void
    {
        // 1. Start engagement with seed 999 (293.1 & 293.8)
        $this->service->startEngagement('ENG-2026-PROCUREMENT-01', 'PROCUREMENT_DEPT', 'Vendor PO Splitting', 999);

        $population = range(1, 100);

        // 2. Generate sample twice; must produce identical ordering and members (293.5 & 293.8)
        $sample1 = $this->service->generateDeterministicSample('ENG-2026-PROCUREMENT-01', $population, 10);
        $sample2 = $this->service->generateDeterministicSample('ENG-2026-PROCUREMENT-01', $population, 10);

        $this->assertCount(10, $sample1);
        $this->assertEquals($sample1, $sample2);
    }

    public function test_finding_lifecycle_management_response_and_closure_evidence(): void
    {
        $this->service->startEngagement('ENG-2026-IT-02', 'IT_INFRA', 'Firewall rules');

        // 1. Log out-of-scope observation formally (293.6 Edge Case)
        $observation = $this->service->logFinding(
            findingCode: 'FIND-OBS-HVAC-01',
            engagementCode: 'ENG-2026-IT-02',
            title: 'Data center HVAC coolant leak',
            severity: 'HIGH',
            isOutOfScope: true
        );
        $this->assertEquals('OUT_OF_SCOPE_OBSERVATION', $observation->severity);

        // 2. Attempting to close without management response rejected (293.7)
        try {
            $this->service->closeFindingWithEvidence('FIND-OBS-HVAC-01', 'DOC-HVAC-REPAIR.PDF');
            $this->fail('Expected exception for closing finding without management response');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('requires formal management response', $e->getMessage());
        }

        // 3. Submit management response (293.2)
        $this->service->submitManagementResponse('FIND-OBS-HVAC-01', 'Facilities contractor replaced the primary coolant valve.');

        // 4. Attempting to close without evidence document rejected (293.5)
        try {
            $this->service->closeFindingWithEvidence('FIND-OBS-HVAC-01', '');
            $this->fail('Expected exception for closing finding without evidence reference');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Verified remediation evidence document reference is mandatory', $e->getMessage());
        }

        // 5. Verified closure succeeds (293.5)
        $closed = $this->service->closeFindingWithEvidence('FIND-OBS-HVAC-01', 'DOC-HVAC-REPAIR-SIGN-OFF.PDF');
        $this->assertEquals('CLOSED_VERIFIED', $closed->status);
        $this->assertEquals('DOC-HVAC-REPAIR-SIGN-OFF.PDF', $closed->closure_evidence_ref);
    }

    public function test_external_auditor_access_strictly_read_only(): void
    {
        // 1. Read-only external auditor access succeeds (293.4 & 293.5)
        $log = $this->service->logExternalAuditorAccess('PWC_SENIOR_AUDITOR_01', 'PKG-Q3-BALANCE-SHEET-RECON', true);
        $this->assertTrue((bool) $log->is_read_only);

        // 2. Non read-only access attempt rejected (293.5)
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('External auditor role is strictly read-only');
        $this->service->logExternalAuditorAccess('PWC_SENIOR_AUDITOR_01', 'PKG-Q3-BALANCE-SHEET-RECON', false);
    }

    public function test_gov_internal_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->startEngagement('ENG-AUD', 'ENTITY', 'SCOPE');
        $this->service->logFinding('FIND-AUD', 'ENG-AUD', 'Title');
        $this->service->submitManagementResponse('FIND-AUD', 'Resp');
        $this->service->closeFindingWithEvidence('FIND-AUD', 'DOC.PDF');
        $this->service->logExternalAuditorAccess('AUD-1', 'PKG-1', true);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: closed finding without evidence
        DB::table('gov_internal_audit_findings')->insert([
            'finding_code' => 'FIND-ILLEGAL-CLOSE',
            'engagement_code' => 'ENG-AUD',
            'finding_title' => 'Title',
            'severity' => 'HIGH',
            'management_response' => 'Done',
            'closure_evidence_ref' => null, // Discrepancy!
            'status' => 'CLOSED_VERIFIED',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
