<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\LegalRegulatoryChangeExecutionService;
use Tests\TestCase;

class LegalRegulatoryChangeExecutionTest extends TestCase
{
    use RefreshDatabase;

    protected LegalRegulatoryChangeExecutionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(LegalRegulatoryChangeExecutionService::class);
    }

    public function test_regulatory_control_gap_closure_and_major_change_replan_edge_case(): void
    {
        // 1. Regular control gap closure without evidence throws exception (338.4)
        try {
            $this->service->closeRegulatoryControlGap(
                pipelineCode: 'PIPE-UNVERIFIED-GAP',
                regulationRef: 'OJK-POJK-51-ESG',
                jurisdictionCountry: 'ID',
                hasEvidence: false
            );
            $this->fail('Expected exception for unevidenced gap closure');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Control gap cannot be marked closed without verification evidence', $e->getMessage());
        }

        // 2. Regular control gap closure with evidence succeeds (338.1 & 338.4)
        $cleanGap = $this->service->closeRegulatoryControlGap(
            pipelineCode: 'PIPE-POJK-REPORTING-01',
            regulationRef: 'OJK-POJK-51-ESG',
            jurisdictionCountry: 'ID',
            hasEvidence: true,
            isMajorSystemChange: false
        );
        $this->assertTrue((bool) $cleanGap->has_closure_evidence);
        $this->assertTrue((bool) $cleanGap->gap_closed);

        // 3. Major system change without approved replan throws exception (338.5 Edge Case)
        try {
            $this->service->closeRegulatoryControlGap(
                pipelineCode: 'PIPE-CORE-BANKING-MIGRATION',
                regulationRef: 'BI-SNAP-API-MANDATE',
                jurisdictionCountry: 'ID',
                hasEvidence: true,
                isMajorSystemChange: true,
                replanApproved: false // Unapproved replan!
            );
            $this->fail('Expected exception for unapproved major replan');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Major system changes require formal approved replan before deployment/closure', $e->getMessage());
        }

        // 4. Major system change with approved replan succeeds (338.5)
        $majorGap = $this->service->closeRegulatoryControlGap(
            pipelineCode: 'PIPE-CORE-BANKING-APPROVED',
            regulationRef: 'BI-SNAP-API-MANDATE',
            jurisdictionCountry: 'ID',
            hasEvidence: true,
            isMajorSystemChange: true,
            replanApproved: true
        );
        $this->assertTrue((bool) $majorGap->is_major_system_change);
        $this->assertTrue((bool) $majorGap->formal_replan_approved);
    }

    public function test_litigation_provision_materiality_determination(): void
    {
        // 1. Litigation provision without documented materiality throws exception (338.4)
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Litigation provision requires documented materiality determination');
        $this->service->recordLitigationProvision(
            caseCode: 'CASE-DISMISS-01',
            caseTitle: 'Contract dispute PT Logistik Mandiri',
            provisionUsd: 500000.0,
            materialityDocumented: false, // Not documented!
            provisionApproved: true
        );
    }

    public function test_litigation_provision_success(): void
    {
        // Documented materiality succeeds (338.3 & 338.4)
        $provision = $this->service->recordLitigationProvision(
            caseCode: 'CASE-ENV-PROCEEDING-02',
            caseTitle: 'Environmental permitting clarification',
            provisionUsd: 1500000.0,
            materialityDocumented: true,
            provisionApproved: true
        );
        $this->assertEquals(1500000.0, (float) $provision->accounting_provision_usd);
        $this->assertTrue((bool) $provision->materiality_determination_documented);
    }

    public function test_compliance_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->closeRegulatoryControlGap('PIPE-AUD', 'REG-1', 'ID', true, false, true);
        $this->service->recordLitigationProvision('CASE-AUD', 'Title', 1000.0, true, true);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: unapproved major replan closed
        DB::table('regulatory_control_gap_closures')->insert([
            'pipeline_code' => 'PIPE-DEFECT-UNAPPROVED-REPLAN',
            'regulation_reference' => 'REG-X',
            'jurisdiction_country' => 'ID',
            'has_closure_evidence' => true,
            'is_major_system_change' => true,
            'formal_replan_approved' => false, // Discrepancy!
            'gap_closed' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
