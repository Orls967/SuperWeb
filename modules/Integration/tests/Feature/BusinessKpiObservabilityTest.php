<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\Integration\Application\Services\BusinessKpiObservabilityService;
use Tests\TestCase;

class BusinessKpiObservabilityTest extends TestCase
{
    use RefreshDatabase;

    protected BusinessKpiObservabilityService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(BusinessKpiObservabilityService::class);
    }

    public function test_business_kpi_observability_and_anomaly_triage_flow(): void
    {
        // 475.1 Record business KPI observable with direct lineage to ledger
        $kpi = $this->service->recordKpiObservable(
            code: 'KPI-PAYMENT-SUCCESS-RATE',
            type: 'payment_success',
            value: 99.40,
            lineageRef: 'LEDGER-TX-SETTLE-BATCH-99'
        );

        $this->assertEquals('KPI-PAYMENT-SUCCESS-RATE', $kpi->kpi_code);
        $this->assertFalse((bool) $kpi->is_stale);

        // 475.2 Detect sudden drop (from 100.00 to 72.00, drop 28% >= 20%)
        $anom = $this->service->detectAndFlagAnomaly('KPI-PAYMENT-SUCCESS-RATE', 100.00, 72.00);
        $this->assertNotNull($anom);
        $this->assertEquals('triaging', $anom->status);
        $this->assertEquals(28.00, (float) $anom->drop_percentage);

        // Triage and resolve anomaly
        $resolved = $this->service->triageAndResolveAnomaly($anom->anomaly_code, 'technical', 'Principal SRE Lead');
        $this->assertEquals('resolved', $resolved->status);
        $this->assertEquals('technical', $resolved->triage_category);

        // 475.4 Audit clean
        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);
    }

    public function test_freshness_sla_evaluation_and_invalid_triage_category_edge_cases(): void
    {
        // 475.3 & 475.5 Edge case: Stale KPI is flagged automatically
        $this->service->recordKpiObservable(
            code: 'KPI-STALE-REVENUE-RATE',
            type: 'revenue_rate',
            value: 50000000.00,
            lineageRef: 'LEDGER-REV-BATCH',
            refreshedAt: now()->subHours(2)->toDateTimeString() // 2 hours old
        );

        $staleCount = $this->service->evaluateFreshnessSla(30); // 30 mins SLA
        $this->assertGreaterThan(0, $staleCount);

        // Invalid triage category throws exception
        $anom = $this->service->detectAndFlagAnomaly('KPI-STALE-REVENUE-RATE', 100.00, 50.00);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Triage category must be \'technical\' or \'business_market\'');

        $this->service->triageAndResolveAnomaly($anom->anomaly_code, 'random_guess', 'Dev');
    }
}
