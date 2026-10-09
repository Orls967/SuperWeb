<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Integration\Application\Services\AnalyticsFederationService;
use Tests\TestCase;

/**
 * Fase 189 — Integrasi 30 Lini D: Data Product & Analytics Federation Tests
 *
 * Covers:
 *  (a) data product SLA freshness breach detection
 *  (b) federated metric lineage consistent with ledger voucher
 *  (c) privacy check fails and rejects export when below k-anonymity
 *  (d) analytics:audit = 0 discrepancy
 */
class AnalyticsFederationTest extends TestCase
{
    use RefreshDatabase;

    protected AnalyticsFederationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(AnalyticsFederationService::class);
    }

    /**
     * (a) Data product SLA freshness detection.
     */
    public function test_data_product_sla_freshness(): void
    {
        // 1. Fresh data product (refreshed 10 minutes ago, SLA 60 minutes)
        $dp1 = $this->service->registerDataProduct('L27', 'Port Container Moves', 60, Carbon::now()->subMinutes(10));
        $this->assertFalse((bool) $dp1->sla_breached);

        // 2. Stale data product (refreshed 120 minutes ago, SLA 60 minutes)
        $dp2 = $this->service->registerDataProduct('L27', 'Berth Utilization History', 60, Carbon::now()->subMinutes(120));
        $this->assertTrue((bool) $dp2->sla_breached);
    }

    /**
     * (b) Federated KPI metric lineage to voucher ledger.
     */
    public function test_federated_metric_ledger_lineage(): void
    {
        $metric = $this->service->recordMetric('GMV_CONSOLIDATED', 'L01_TO_L30', 250000000000.0, 'VCHR-GL-20261008-0099');

        $this->assertSame('GMV_CONSOLIDATED', $metric->metric_code);
        $this->assertSame('VCHR-GL-20261008-0099', $metric->ledger_voucher_reference);
        $this->assertEquals(250000000000.00, (float) $metric->metric_value);
    }

    /**
     * (c) Privacy-preserving k-anonymity threshold enforcement.
     */
    public function test_cross_line_export_k_anonymity(): void
    {
        // 1. Below threshold (5 < 10) -> Exception
        try {
            $this->service->requestCrossLineExport('HEALTHCARE', 5, 10);
            $this->fail('Expected exception for privacy k-anonymity check failure.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('fails minimum k-anonymity threshold', $e->getMessage());
        }

        // 2. Above threshold (25 >= 10) -> APPROVED
        $export = $this->service->requestCrossLineExport('HEALTHCARE', 25, 10);
        $this->assertSame('APPROVED', $export->status);
        $this->assertTrue((bool) $export->privacy_check_passed);
    }

    /**
     * (d) Audit status healthy with 0 discrepancies.
     */
    public function test_analytics_federation_audit(): void
    {
        $audit = $this->service->audit();
        $this->assertSame('HEALTHY', $audit['status']);
        $this->assertSame(0, $audit['discrepancy_count']);
    }
}
