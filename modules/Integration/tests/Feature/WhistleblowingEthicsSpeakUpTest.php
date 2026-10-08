<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Integration\Application\Services\WhistleblowingEthicsSpeakUpService;
use Tests\TestCase;

class WhistleblowingEthicsSpeakUpTest extends TestCase
{
    use RefreshDatabase;

    protected WhistleblowingEthicsSpeakUpService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(WhistleblowingEthicsSpeakUpService::class);
    }

    public function test_whistleblower_case_reporting_and_investigation(): void
    {
        // 406.1 & 406.5 Anonymous intake with pseudonym protection
        $case = $this->service->reportCase(
            caseCode: 'ETH-2026-001',
            channel: 'web',
            category: 'harassment',
            allegationDetails: 'Observed workplace safety violations on factory floor B.',
            isAnonymous: true
        );

        $this->assertEquals('ETH-2026-001', $case->case_code);
        $this->assertTrue((bool) $case->is_anonymous);
        $this->assertNotNull($case->whistleblower_pseudonym);
        $this->assertTrue((bool) $case->anti_retaliation_monitoring_active);

        // 406.2 & 406.3 Investigate and close
        $inv = $this->service->investigateCase(
            caseCode: 'ETH-2026-001',
            leadInvestigator: 'Chief Compliance Officer',
            findings: 'Safety gear protocols were not followed by supervisor.',
            disciplinaryAction: 'Mandatory retraining and written warning.',
            systemicActionClosed: true
        );

        $this->assertTrue((bool) $inv->systemic_action_closed);

        // 406.4 Audit clean
        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);
    }

    public function test_sla_aging_triggers_escalation_risk(): void
    {
        // 406.6 Risk: Unaddressed case aging triggers automatic escalation to ethics committee
        $case = $this->service->reportCase(
            caseCode: 'ETH-2026-002',
            channel: 'mobile',
            category: 'corruption',
            allegationDetails: 'Suspected tender collusion in procurement.',
            isAnonymous: true
        );

        $this->assertFalse((bool) $case->escalated_to_committee);

        // Advance 30 days
        $escalated = $this->service->advanceSlaDay('ETH-2026-002', 30);
        $this->assertEquals(0, $escalated->sla_days_remaining);
        $this->assertTrue((bool) $escalated->escalated_to_committee);
    }
}
