<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\Integration\Application\Services\CostEfficiencyPerformanceService;
use Tests\TestCase;

class CostEfficiencyPerformanceTest extends TestCase
{
    use RefreshDatabase;

    protected CostEfficiencyPerformanceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(CostEfficiencyPerformanceService::class);
    }

    public function test_efficiency_savings_validation_and_performance_budget_flow(): void
    {
        // 434.1 & 434.2 Register efficiency item with FinOps baseline
        $item = $this->service->registerEfficiencyItem(
            itemCode: 'OPT-REDIS-CACHE-01',
            category: 'cache_miss',
            baselineCostPerMonth: 120000000.00
        );

        $this->assertEquals('OPT-REDIS-CACHE-01', $item->item_code);

        // Validate savings after optimization
        $validated = $this->service->validateEfficiencySavings(
            itemCode: 'OPT-REDIS-CACHE-01',
            postOptCost: 40000000.00,
            tradeoffApproved: true
        );

        $this->assertEquals(80000000.00, (float) $validated->validated_monthly_savings);
        $this->assertEquals('validated', $validated->status);

        // 434.3 Define performance budget for new feature
        $budget = $this->service->definePerformanceBudget(
            featureCode: 'FEAT-MULTI-INVOICE',
            maxLatencyMs: 150.00,
            maxMemoryMb: 64.00
        );

        $this->assertEquals('FEAT-MULTI-INVOICE', $budget->feature_code);

        // Evaluate in CI within budget
        $evaluated = $this->service->evaluatePerformanceBudget(
            featureCode: 'FEAT-MULTI-INVOICE',
            testedLatency: 110.00,
            testedMemory: 48.00
        );

        $this->assertTrue((bool) $evaluated->budget_passed);

        // 434.4 Audit clean
        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);
    }

    public function test_budget_breach_and_missing_baseline_blocked_edge_cases(): void
    {
        // 434.6 Risk: Zero/negative baseline blocked
        try {
            $this->service->registerEfficiencyItem('OPT-BAD-BASE', 'slow_query', 0.00);
            $this->fail('Expected exception for zero baseline');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('valid baseline cost (> 0) is mandatory', $e->getMessage());
        }

        // 434.4 Performance regression exceeding budget blocks release
        $this->service->definePerformanceBudget('FEAT-HEAVY-EXPORT', 200.00, 128.00);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('violates performance budget');

        $this->service->evaluatePerformanceBudget('FEAT-HEAVY-EXPORT', 350.00, 256.00);
    }
}
