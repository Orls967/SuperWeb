<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\AutonomousEnterpriseLadderService;
use Tests\TestCase;

class AutonomousEnterpriseLadderTest extends TestCase
{
    use RefreshDatabase;

    protected AutonomousEnterpriseLadderService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(AutonomousEnterpriseLadderService::class);
    }

    public function test_level_4_autonomy_process_registration_and_blast_radius_guard(): void
    {
        // 1. Level 4 rejected if HIGH risk or missing blast radius documentation (268.1 & 268.7)
        try {
            $this->service->registerAutonomousProcess(
                processCode: 'PROC-CORE-LEDGER-POST',
                processName: 'Core GL Journal Posting',
                autonomyLevel: 4,
                riskTier: 'CRITICAL', // Disallowed for Level 4!
                boundedBlastRadiusDoc: null
            );
            $this->fail('Expected exception for high-risk level 4 registration');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Level 4 autonomy requires LOW risk tier', $e->getMessage());
        }

        // 2. Level 4 succeeds with LOW risk and documented bounded blast radius
        $proc = $this->service->registerAutonomousProcess(
            processCode: 'PROC-REDIS-CACHE-CLEANUP',
            processName: 'Automated Redis Key Eviction',
            autonomyLevel: 4,
            riskTier: 'LOW',
            boundedBlastRadiusDoc: 'DOC-CACHE-EVICTION-BOUNDED-RADIUS.MD'
        );
        $this->assertEquals(4, (int) $proc->autonomy_level);
        $this->assertEquals(5.00, (float) $proc->audit_sampling_rate_pct); // 5% sampling
    }

    public function test_audit_sampling_defect_automatically_demotes_autonomy_level(): void
    {
        $proc = $this->service->registerAutonomousProcess(
            processCode: 'PROC-INVOICE-OCR',
            processName: 'Automated OCR Invoice Matching',
            autonomyLevel: 4,
            riskTier: 'LOW',
            boundedBlastRadiusDoc: 'DOC-INVOICE-MATCH-RADIUS.MD'
        );

        // Defect found in sample audit triggers immediate demotion to level 2 (SUPERVISED) (268.5 Edge Case)
        $demoted = $this->service->executeProcessWithSampling('PROC-INVOICE-OCR', sampleAuditHasDefect: true);
        $this->assertEquals(2, (int) $demoted->autonomy_level);
        $this->assertGreaterThan(0.0, (float) $demoted->defect_rate_pct);
    }

    public function test_self_healing_operations_remediates_without_masking_incident(): void
    {
        // Self-healing executes with 0 downtime and leaves public incident log (268.2 & 268.4)
        $heal = $this->service->executeSelfHealingRemediation(
            anomalyDetected: 'Payment Gateway Pod Memory Saturation (98%)',
            runbookCode: 'RBK-HEAL-POD-RESTART-01',
            automatedAction: 'RESTART_CONTAINER'
        );

        $this->assertEquals(0, (int) $heal->downtime_seconds);
        $this->assertTrue((bool) $heal->is_incident_logged_publicly);
        $this->assertNotNull($heal->post_incident_report_doc);
    }

    public function test_negotiation_agent_ceiling_enforces_mandatory_human_signoff(): void
    {
        // 1. Below ceiling ($25,000 <= $50,000) auto-signs (268.3)
        $smallContract = $this->service->negotiateContractRenewal(
            negotiationCode: 'NEGOT-OFFICE-PAPER-01',
            vendorName: 'PT Kertas Nusantara',
            contractValueUsd: 25000.0,
            signingThresholdUsd: 50000.0
        );
        $this->assertEquals('SIGNED_EXECUTED', $smallContract->status);
        $this->assertFalse((bool) $smallContract->human_approval_required);

        // 2. Above ceiling ($150,000 > $50,000) requires human sign-off (268.3 & 268.6)
        $largeContract = $this->service->negotiateContractRenewal(
            negotiationCode: 'NEGOT-CLOUD-SERVER-02',
            vendorName: 'Global Cloud Infrastructure',
            contractValueUsd: 150000.0,
            signingThresholdUsd: 50000.0
        );
        $this->assertEquals('PENDING_HUMAN_APPROVAL', $largeContract->status);
        $this->assertTrue((bool) $largeContract->human_approval_required);

        // Human executive signs off
        $approved = $this->service->approveContract('NEGOT-CLOUD-SERVER-02', 'CHIEF_TECHNOLOGY_OFFICER');
        $this->assertEquals('SIGNED_EXECUTED', $approved->status);
        $this->assertEquals('CHIEF_TECHNOLOGY_OFFICER', $approved->human_approved_by);
    }

    public function test_autonomy_ladder_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->registerAutonomousProcess('PROC-AUD', 'Audited Proc', 2, 'MEDIUM');
        $this->service->executeSelfHealingRemediation('Anomaly', 'RBK', 'RESTART');
        $this->service->negotiateContractRenewal('NEGOT-AUD', 'Vendor', 1000.0);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: masked self-healing incident
        DB::table('autonomy_self_healing_incidents')->insert([
            'incident_code' => 'HEAL-MASKED-SECRET',
            'anomaly_detected' => 'Hidden crash',
            'runbook_code' => 'RBK-SECRET',
            'automated_remediation_action' => 'FAILOVER',
            'downtime_seconds' => 0,
            'is_incident_logged_publicly' => false, // Discrepancy: masked!
            'post_incident_report_doc' => 'NONE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
