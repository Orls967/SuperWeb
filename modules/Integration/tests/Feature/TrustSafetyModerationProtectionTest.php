<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\TrustSafetyModerationProtectionService;
use Tests\TestCase;

class TrustSafetyModerationProtectionTest extends TestCase
{
    use RefreshDatabase;

    protected TrustSafetyModerationProtectionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(TrustSafetyModerationProtectionService::class);
    }

    public function test_safety_reporting_anonymization_and_sla_edge_case(): void
    {
        // 1. Reporter identity strictly anonymized into irreversible hash (374.2, 374.4, 374.5 Edge Case)
        $rawContact = 'whistleblower.employee@mining-site.com';
        $report = $this->service->fileSafetyReport(
            reportCode: 'REP-HAZARD-001',
            rawReporterEmailOrPhone: $rawContact,
            riskTier: 'URGENT',
            slaMinutes: 15,
            slaMet: true
        );

        $this->assertTrue((bool) $report->reporter_identity_redacted);
        $this->assertNotEquals($rawContact, $report->reporter_anonymized_hash);
        $this->assertEquals(hash('sha256', 'REPORTER_SALT_' . $rawContact), $report->reporter_anonymized_hash);
        $this->assertTrue((bool) $report->sla_met);
    }

    public function test_moderation_action_appeal_enforcement(): void
    {
        // 1. Moderation action without appeal channel fails (374.2 & 374.4)
        try {
            $this->service->takeModerationAction(
                actionCode: 'ACT-MOD-BAN-01',
                contentId: 'CONTENT-REVIEW-991',
                decisionType: 'TAKEDOWN',
                appealPermitted: false // Unappealable!
            );
            $this->fail('Expected exception for unappealable moderation decision');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('must permit an appeal channel', $e->getMessage());
        }

        // 2. Appealable moderation action succeeds (374.2 & 374.4)
        $action = $this->service->takeModerationAction(
            actionCode: 'ACT-MOD-BAN-02',
            contentId: 'CONTENT-REVIEW-992',
            decisionType: 'TAKEDOWN',
            appealPermitted: true
        );
        $this->assertTrue((bool) $action->appeal_permitted);
        $this->assertEquals('TAKEDOWN', $action->decision_type);
    }

    public function test_trust_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->fileSafetyReport('R-AUD', 'contact@mail.com', 'LOW', 60, true);
        $this->service->takeModerationAction('M-AUD', 'C-1', 'WARN', true);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: unredacted report
        DB::table('trust_safety_incident_reports')->insert([
            'report_code' => 'R-DEFECT-UNREDACTED',
            'reporter_anonymized_hash' => 'RAW_LEAKED_EMAIL',
            'reporter_identity_redacted' => false, // Discrepancy!
            'risk_tier' => 'URGENT',
            'sla_minutes' => 15,
            'sla_met' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
