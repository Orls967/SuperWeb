<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\PublicAffairsLicenseService;
use Tests\TestCase;

class PublicAffairsLicenseTest extends TestCase
{
    use RefreshDatabase;

    protected PublicAffairsLicenseService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(PublicAffairsLicenseService::class);
    }

    public function test_issue_statement_approval_gate_and_viral_crisis_activation(): void
    {
        // 1. Releasing statement without CorpComms approval throws exception (339.2 & 339.4)
        try {
            $this->service->processIssueStatement(
                issueCode: 'ISSUE-ACCIDENT-HAUL-01',
                lineCode: 'LINE_MINING',
                severity: 'HIGH',
                draftStatement: 'Initial facts on the haul road incident...',
                approvedByCorpComms: false, // Unapproved!
                releaseStatement: true // Attempting release!
            );
            $this->fail('Expected exception for unapproved statement release');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Cannot release holding statement without CorpComms executive approval', $e->getMessage());
        }

        // 2. Approved release succeeds (339.2 & 339.4)
        $approved = $this->service->processIssueStatement(
            issueCode: 'ISSUE-ACCIDENT-APPROVED-02',
            lineCode: 'LINE_MINING',
            severity: 'HIGH',
            draftStatement: 'Official statement on safety response...',
            approvedByCorpComms: true,
            releaseStatement: true
        );
        $this->assertTrue((bool) $approved->statement_approved_by_corpcomms);
        $this->assertTrue((bool) $approved->statement_released);
        $this->assertFalse((bool) $approved->crisis_comms_activated);

        // 3. Viral critical issue automatically activates crisis communications (339.5 Edge Case)
        $viral = $this->service->processIssueStatement(
            issueCode: 'ISSUE-COMMUNITY-PROTEST-VIRAL',
            lineCode: 'LINE_SMELTER',
            severity: 'CRITICAL_VIRAL',
            draftStatement: 'Holding statement regarding community demonstration...',
            approvedByCorpComms: true,
            releaseStatement: false
        );
        $this->assertTrue((bool) $viral->crisis_comms_activated);
    }

    public function test_government_engagement_conflict_screening(): void
    {
        // 1. Engagement without conflict screening throws exception (339.3 & 339.4)
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Government relations compliance breach: Engagement must be screened');
        $this->service->logGovernmentEngagement(
            engagementCode: 'ENG-MINISTRY-MINING-01',
            agency: 'ESDM',
            officialNameAndTitle: 'Ir. Budi Director of Mineral Mining',
            repName: 'John Doe Head of Regulatory Affairs',
            topic: 'RKAB production quota review',
            conflictScreened: false // Not screened!
        );
    }

    public function test_government_engagement_success(): void
    {
        // Screened engagement succeeds (339.3 & 339.4)
        $eng = $this->service->logGovernmentEngagement(
            engagementCode: 'ENG-MINISTRY-FORESTRY-02',
            agency: 'KLHK',
            officialNameAndTitle: 'Dr. Siti Director of Environmental Impact',
            repName: 'Jane Smith Head of Government Relations',
            topic: 'Borrow-to-use forestry permit (PPKH) monitoring',
            conflictScreened: true
        );
        $this->assertTrue((bool) $eng->conflict_of_interest_screened);
        $this->assertTrue((bool) $eng->transparency_register_logged);
    }

    public function test_ethics_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->processIssueStatement('I-AUD', 'L1', 'LOW', 'Draft', true, true);
        $this->service->logGovernmentEngagement('E-AUD', 'AGENCY', 'Official', 'Rep', 'Topic', true);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: unapproved statement released
        DB::table('public_stakeholder_issue_statements')->insert([
            'issue_code' => 'ISSUE-DEFECT-LEAKED',
            'line_code' => 'L2',
            'issue_severity' => 'HIGH',
            'holding_statement_draft' => 'Leaked statement',
            'statement_approved_by_corpcomms' => false, // Discrepancy!
            'crisis_comms_activated' => false,
            'statement_released' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
