<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\Integration\Application\Services\EnterpriseStakeholderValueReportingService;
use Tests\TestCase;

class EnterpriseStakeholderValueReportingTest extends TestCase
{
    use RefreshDatabase;

    protected EnterpriseStakeholderValueReportingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(EnterpriseStakeholderValueReportingService::class);
    }

    public function test_stakeholder_value_metric_and_feedback_action_flow(): void
    {
        // 478.1 & 478.2 Record stakeholder value metric with direct lineage
        $metric = $this->service->recordStakeholderMetric(
            code: 'METRIC-COMMUNITY-CLEAN-KM',
            group: 'communities',
            name: 'Electric Kilometers Driven in Clean Low-Emission Zones',
            value: 4850000.00,
            lineageRef: 'IOT-TELEMATICS-BATTERY-DISPATCH-2026'
        );

        $this->assertEquals('METRIC-COMMUNITY-CLEAN-KM', $metric->metric_code);
        $this->assertEquals('communities', $metric->stakeholder_group);

        // 478.3 Log and close accommodated feedback
        $fb = $this->service->logFeedbackAction(
            code: 'FB-FLEET-CLIENTS-API',
            group: 'customers',
            summary: 'Request webhook events for live truck breakdown updates',
            isAccommodated: true
        );

        $this->assertEquals('tracked', $fb->status);

        $closed = $this->service->closeFeedbackAction('FB-FLEET-CLIENTS-API');
        $this->assertEquals('closed', $closed->status);

        // 478.4 Audit clean
        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);
    }

    public function test_missing_lineage_and_unaccommodated_feedback_blocked_edge_cases(): void
    {
        // 478.4 & 478.6 Missing source lineage reference is blocked
        try {
            $this->service->recordStakeholderMetric('MTR-BOGUS', 'shareholders', 'Fake ROI', 99.00, '');
            $this->fail('Expected exception for missing metric lineage');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('requires direct source lineage reference', $e->getMessage());
        }

        // 478.5 Edge case: Non-accommodated feedback without rationale is blocked
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Non-accommodated stakeholder feedback requires explicit documented rationale');

        $this->service->logFeedbackAction(
            code: 'FB-UNACCOMMODATED-SILENT',
            group: 'employees',
            summary: 'Free espresso machine on every truck cab',
            isAccommodated: false,
            nonAccommodationRationale: null // Missing!
        );
    }
}
