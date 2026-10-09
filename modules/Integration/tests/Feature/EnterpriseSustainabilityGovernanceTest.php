<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\Integration\Application\Services\EnterpriseSustainabilityGovernanceService;
use Tests\TestCase;

class EnterpriseSustainabilityGovernanceTest extends TestCase
{
    use RefreshDatabase;

    protected EnterpriseSustainabilityGovernanceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(EnterpriseSustainabilityGovernanceService::class);
    }

    public function test_sustainability_steering_decision_and_action_flow(): void
    {
        // 464.1 & 464.5 Record trade-off decision: Electrify heavy haulage despite initial capex bump
        $decision = $this->service->recordSteeringDecision(
            code: 'STR-LOGISTICS-EV-TRUCKS',
            title: 'Transition 50 Heavy Prime Movers to Electric with Battery Swapping',
            tradeoffRationale: 'Accept 12% higher short-term fleet capex to achieve 40% Scope 1 diesel emissions cut and long-term TCO parity by year 3',
            owner: 'VP Group Sustainability & Fleet Operations',
            dueDate: now()->addMonths(6)->toDateString(),
            integratedIntoRisk: true,
            verifiedMetrics: true
        );

        $this->assertEquals('STR-LOGISTICS-EV-TRUCKS', $decision->decision_code);
        $this->assertEquals('actionable', $decision->status);

        // Execute action
        $executed = $this->service->executeSteeringAction('STR-LOGISTICS-EV-TRUCKS');
        $this->assertEquals('executed', $executed->status);

        // 464.4 Audit clean
        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);
    }

    public function test_missing_tradeoff_rationale_and_unverified_metric_blocked_edge_cases(): void
    {
        // 464.1 Missing trade-off rationale is blocked
        try {
            $this->service->recordSteeringDecision(
                code: 'STR-VAGUE',
                title: 'Vague Green Plan',
                tradeoffRationale: '', // Empty!
                owner: 'Officer',
                dueDate: now()->addDays(30)->toDateString()
            );
            $this->fail('Expected exception for missing trade-off rationale');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Explicit documented trade-off rationale between cost, carbon', $e->getMessage());
        }

        // 464.3 & 464.4 Unverified metric is blocked
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Sustainability steering KPI metrics must be third-party verified');

        $this->service->recordSteeringDecision(
            code: 'STR-SELF-REPORTED',
            title: 'Unverified Offsets',
            tradeoffRationale: 'Cheap carbon credits without verification',
            owner: 'Procurement',
            dueDate: now()->addDays(15)->toDateString(),
            integratedIntoRisk: true,
            verifiedMetrics: false // Unverified!
        );
    }
}
