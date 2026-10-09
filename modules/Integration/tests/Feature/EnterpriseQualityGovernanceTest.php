<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\Integration\Application\Services\EnterpriseQualityGovernanceService;
use Tests\TestCase;

class EnterpriseQualityGovernanceTest extends TestCase
{
    use RefreshDatabase;

    protected EnterpriseQualityGovernanceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(EnterpriseQualityGovernanceService::class);
    }

    public function test_cross_boundary_quality_incident_and_culture_flow(): void
    {
        // 462.2 & 462.5 Log cross-line incident between Rental and Workshop with coordinator
        $inc = $this->service->logCrossLineIncident(
            code: 'INC-BRAKE-MAINT-01',
            originatingLine: 'LINE-CENTRAL-WORKSHOP',
            impactedLine: 'LINE-CAR-RENTAL',
            coordinator: 'QA Principal Lead'
        );

        $this->assertEquals('INC-BRAKE-MAINT-01', $inc->incident_code);
        $this->assertEquals('investigating', $inc->status);

        // 462.2 & 462.4 Implement fix and verify effectiveness
        $fixed = $this->service->implementAndVerifyFix(
            code: 'INC-BRAKE-MAINT-01',
            rca: 'Torque calibration wrench sensor drifted 4% over quarterly threshold',
            systemFix: 'Mandatory pre-shift digital calibration lock added to handheld diagnostic terminal',
            verified: true
        );

        $this->assertEquals('verified_closed', $fixed->status);
        $this->assertTrue((bool) $fixed->effectiveness_verified);

        // 462.3 & 462.6 Submit non-punitive quality report
        $report = $this->service->submitNonPunitiveReport(
            reportCode: 'OBS-LINE-01',
            lineCode: 'LINE-CENTRAL-WORKSHOP',
            observation: 'Hydraulic lift secondary safety pin latch spring feels sluggish on bay 4'
        );

        $this->assertTrue((bool) $report->is_non_punitive_submission);

        // 462.4 Audit clean
        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);
    }

    public function test_missing_coordinator_blocked_edge_case(): void
    {
        // 462.5 Edge case: Cross-boundary incident without coordinator is blocked
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cross-boundary quality incident must have a designated responsible coordinator');

        $this->service->logCrossLineIncident('INC-NO-OWNER', 'LINE-A', 'LINE-B', '');
    }
}
