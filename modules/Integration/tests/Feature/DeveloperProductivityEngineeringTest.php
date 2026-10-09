<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\Integration\Application\Services\DeveloperProductivityEngineeringService;
use Tests\TestCase;

class DeveloperProductivityEngineeringTest extends TestCase
{
    use RefreshDatabase;

    protected DeveloperProductivityEngineeringService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(DeveloperProductivityEngineeringService::class);
    }

    public function test_dora_metrics_and_tech_debt_paydown_flow(): void
    {
        // 471.1 Record DORA metrics
        $metrics = $this->service->recordDoraMetrics(
            team: 'TEAM-CORE-INTEGRATION',
            period: '2026-M09',
            leadTimeHours: 4.50,
            deployFreqDaily: 8.20,
            failureRatePercent: 0.80,
            mttrMinutes: 18.00
        );

        $this->assertEquals('TEAM-CORE-INTEGRATION', $metrics->team_code);
        $this->assertEquals(4.50, (float) $metrics->lead_time_hours);

        // 471.3 & 471.5 Log flaky test with quarantine
        $debt = $this->service->logTechDebt(
            debtCode: 'DEBT-FLAKY-PAYMENT-MOCK',
            moduleCode: 'PAYMENTS',
            debtType: 'flaky_test',
            paydownDueDate: now()->addDays(14)->toDateString(),
            quarantine: true,
            budgetAllocated: true
        );

        $this->assertEquals('DEBT-FLAKY-PAYMENT-MOCK', $debt->debt_code);
        $this->assertTrue((bool) $debt->is_quarantined);

        // Paydown debt
        $paid = $this->service->paydownDebt('DEBT-FLAKY-PAYMENT-MOCK');
        $this->assertEquals('paid_down', $paid->status);
        $this->assertFalse((bool) $paid->is_quarantined);

        // 471.4 Audit clean
        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);
    }

    public function test_unquarantined_flaky_test_and_unfunded_debt_blocked_edge_cases(): void
    {
        // 471.5 Edge case: Flaky test without quarantine is blocked
        try {
            $this->service->logTechDebt('DEBT-SLOPPY', 'MODULE-X', 'flaky_test', now()->addDays(7)->toDateString(), false);
            $this->fail('Expected exception for unquarantined flaky test');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('must be formally quarantined with a strict remediation due date', $e->getMessage());
        }

        // 471.6 Risk: Unfunded debt without paydown budget is blocked
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Technical debt item requires an allocated paydown budget');

        $this->service->logTechDebt('DEBT-UNFUNDED', 'MODULE-Y', 'code_complexity', now()->addDays(30)->toDateString(), false, false);
    }
}
