<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Integration\Application\Services\WhistleblowingEthicsService;
use Tests\TestCase;

/**
 * Fase 232 — Tata Kelola: Ethics, Whistleblowing & Speak-Up Culture Tests
 *
 * Covers:
 *  (a) Anonymous speak-up reporting and four-eyes investigator requirement
 *  (b) Fraud mesh referral bridge without investigation duplication
 *  (c) Edge Case 232.6 & 232.7: Identity leak auto-triggers anti-retaliation & anti-SLAPP defense
 *  (d) Ethics sanctions isolated from general HR views
 *  (e) Quality audit ethics:audit clean with 0 discrepancies
 */
class WhistleblowingEthicsTest extends TestCase
{
    use RefreshDatabase;

    protected WhistleblowingEthicsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(WhistleblowingEthicsService::class);
    }

    /**
     * (a) Speak-up reporting and four-eyes investigator principle (232.1 & 232.5).
     */
    public function test_speak_up_and_four_eyes_assignment(): void
    {
        $report = $this->service->submitReport(
            'CORRUPTION',
            'Encrypted allegations regarding procurement kickbacks in shipping charter',
            true,
            true
        );

        $this->assertSame('SUBMITTED', $report->status);
        $this->assertNotEmpty($report->anonymous_token);
        $this->assertTrue((bool) $report->is_anonymous);

        // Attempting to assign the same person for both roles -> Throws exception
        try {
            $this->service->assignInvestigators($report->report_code, 'INV-LEAD-01', 'INV-LEAD-01');
            $this->fail('Expected exception for four-eyes investigator violation.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('Four-eyes principle violation', $e->getMessage());
        }

        // Distinct dual investigators -> OK
        $assigned = $this->service->assignInvestigators($report->report_code, 'INV-LEAD-01', 'INV-PEER-02');
        $this->assertSame('INVESTIGATING', $assigned->status);
        $this->assertSame('INV-LEAD-01', $assigned->primary_investigator_id);
        $this->assertSame('INV-PEER-02', $assigned->secondary_investigator_id);
    }

    /**
     * (b) Fraud referral bridge (232.4).
     */
    public function test_fraud_mesh_referral_bridge(): void
    {
        $report = $this->service->submitReport('FRAUD', 'Fictitious billing from shell subcontractor', true, true);
        $bridged = $this->service->referToFraudMesh($report->report_code);

        $this->assertTrue((bool) $bridged->has_fraud_indication);
        $this->assertStringStartsWith('FRD-MESH-', $bridged->fraud_mesh_case_id);
    }

    /**
     * (c) Edge Case 232.6 & 232.7: Identity leak handling with anti-retaliation & anti-SLAPP support.
     */
    public function test_identity_leak_and_anti_retaliation_protection(): void
    {
        $report = $this->service->submitReport('SAFETY', 'Misfired safety alarms in chemical warehouse', true, false);
        $this->service->assignInvestigators($report->report_code, 'INV-01', 'INV-02');

        // Reporter identity leak occurs -> Triggers leak protocol
        $updated = $this->service->reportIdentityLeak($report->report_code, 'Metadata accidentally exposed during document handover');
        $this->assertTrue((bool) $updated->identity_leaked);
        $this->assertSame('INVESTIGATING', $updated->status); // Core investigation does NOT stop

        // Verify protective anti-retaliation alert was automatically triggered with legal defense support (Anti-SLAPP)
        $alert = DB::table('gov_anti_retaliation_alerts')->where('report_code', $report->report_code)->first();
        $this->assertNotNull($alert);
        $this->assertSame('TRIGGERED', $alert->investigation_status);
        $this->assertTrue((bool) $alert->legal_support_provided); // 232.7 Anti-SLAPP support
    }

    /**
     * (d) Ethics sanctions isolated from general HR view (232.3).
     */
    public function test_ethics_sanctions_isolation(): void
    {
        $sanction = $this->service->applySanction(
            'WB-CASE-01',
            'EMP-CORRUPT-01',
            'GROSS_MISCONDUCT',
            'TERMINATION'
        );

        $this->assertSame('GROSS_MISCONDUCT', $sanction->violation_severity);
        $this->assertSame('TERMINATION', $sanction->sanction_applied);
        $this->assertTrue((bool) $sanction->isolated_from_hr_view);
    }

    /**
     * (e) Audit status healthy with 0 discrepancies.
     */
    public function test_ethics_audit(): void
    {
        $audit = $this->service->audit();
        $this->assertSame('HEALTHY', $audit['status']);
        $this->assertSame(0, $audit['discrepancy_count']);
    }
}
