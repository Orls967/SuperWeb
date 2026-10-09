<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Integration\Application\Services\EnterprisePerformanceManagementService;
use Tests\TestCase;

class EnterprisePerformanceManagementTest extends TestCase
{
    use RefreshDatabase;

    protected EnterprisePerformanceManagementService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(EnterprisePerformanceManagementService::class);
    }

    public function test_balanced_scorecard_and_review_action_flow(): void
    {
        // 459.1 Record harmonious balanced scorecard for fleet operations
        $sc = $this->service->recordScorecard(
            code: 'BSC-2026-Q3-FLEET',
            lineCode: 'LINE-FLEET-LOGISTICS',
            period: '2026-Q3',
            financial: 82.00,
            customer: 88.00,
            process: 90.00,
            people: 85.00,
            sustainability: 80.00
        );

        $this->assertEquals('BSC-2026-Q3-FLEET', $sc->scorecard_code);
        $this->assertFalse((bool) $sc->tradeoff_flagged);

        // 459.2 Create review action
        $act = $this->service->createReviewAction(
            actionCode: 'ACT-ROUTE-OPTIMIZE',
            scorecardCode: 'BSC-2026-Q3-FLEET',
            decision: 'Deploy dynamic AI dispatch to lift on-time delivery from 88 to 94%',
            owner: 'Head of Fleet Dispatch',
            dueDate: now()->addDays(30)->toDateString()
        );

        $this->assertEquals('open', $act->status);

        // 459.4 Close review action
        $closed = $this->service->closeReviewAction('ACT-ROUTE-OPTIMIZE');
        $this->assertEquals('closed', $closed->status);

        // 459.4 Audit clean
        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);
    }

    public function test_tradeoff_flagging_and_aging_action_escalation_edge_cases(): void
    {
        // 459.5 Edge case: Conflicting scores (Financial high 92, People low 45) triggers trade-off flag
        $sc = $this->service->recordScorecard(
            code: 'BSC-2026-Q3-WAREHOUSE',
            lineCode: 'LINE-WAREHOUSE',
            period: '2026-Q3',
            financial: 92.00,
            customer: 85.00,
            process: 80.00,
            people: 45.00, // Conflict! Burnout / high turnover
            sustainability: 75.00
        );

        $this->assertTrue((bool) $sc->tradeoff_flagged);

        // Resolve trade-off with explicit executive note
        $resolved = $this->service->resolveTradeOff('BSC-2026-Q3-WAREHOUSE', 'Approved shift restructuring & headcount addition to balance frontline workload');
        $this->assertFalse((bool) $resolved->tradeoff_flagged);

        // 459.6 Risk: Overdue action escalation
        $this->service->createReviewAction(
            actionCode: 'ACT-OVERDUE-01',
            scorecardCode: 'BSC-2026-Q3-WAREHOUSE',
            decision: 'Review warehouse ergonomics',
            owner: 'HR Manager',
            dueDate: now()->subDays(5)->toDateString() // Overdue!
        );

        $escalatedCount = $this->service->escalateOverdueActions();
        $this->assertGreaterThan(0, $escalatedCount);
    }
}
