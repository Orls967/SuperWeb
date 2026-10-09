<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Integration\Application\Services\EsgDataFabricService;
use Tests\TestCase;

/**
 * Fase 228 — Keberlanjutan: ESG Data Fabric & Double Materiality Tests
 *
 * Covers:
 *  (a) Provenance & quality score gate for public ESG claims (< 0.70 disqualified)
 *  (b) Edge Case 228.5: Sensor outage flagged as data gap + alert (no silent estimation)
 *  (c) Double materiality assessment determining reporting scope
 *  (d) Reporting standards bridge (GRI/ISSB) where gaps are never recorded as compliant
 *  (e) Group aggregation sum invariant across business lines
 *  (f) Quality audit esg:audit clean with 0 discrepancies
 */
class EsgDataFabricTest extends TestCase
{
    use RefreshDatabase;

    protected EsgDataFabricService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(EsgDataFabricService::class);
    }

    /**
     * (a) & (b) Metric recording, quality score gating, and sensor outage edge case (228.5 & 228.7).
     */
    public function test_esg_metric_quality_and_sensor_outage_edge_case(): void
    {
        // 1. High quality sensor metric -> Eligible for public claim
        $m1 = $this->service->recordMetric(
            'HOTEL',
            'ENVIRONMENT',
            'Water Consumption',
            'm3',
            1450.5,
            'SENSOR',
            0.95,
            '2026-Q3'
        );
        $this->assertTrue((bool) $m1->public_disclosure_eligible);
        $this->assertFalse((bool) $m1->is_data_gap);

        // 2. Low quality score metric (< 0.70) -> Disqualified from public claims (228.7)
        $m2 = $this->service->recordMetric(
            'RETAIL',
            'SOCIAL',
            'Supplier Diversity Ratio',
            '%',
            35.0,
            'MANUAL',
            0.55,
            '2026-Q3'
        );
        $this->assertFalse((bool) $m2->public_disclosure_eligible);

        // 3. Edge Case 228.5: Sensor outage -> Flagged as data gap, never silently estimated
        $m3 = $this->service->recordMetric(
            'MINING',
            'ENVIRONMENT',
            'Scope 1 Emissions',
            'tCO2e',
            null,
            'IOT_GATEWAY',
            0.90,
            '2026-Q3',
            true, // Sensor down
            'Smelter emission sensor offline during maintenance'
        );
        $this->assertTrue((bool) $m3->is_data_gap);
        $this->assertNull($m3->reported_value);
        $this->assertFalse((bool) $m3->public_disclosure_eligible);
        $this->assertStringContainsString('Smelter emission sensor offline', $m3->gap_reason);
    }

    /**
     * (c) Double materiality assessment (228.2).
     */
    public function test_double_materiality_scope(): void
    {
        // High impact score -> Material and in scope
        $topic1 = $this->service->assessDoubleMateriality(
            'ENERGY',
            'TOPIC-CARBON',
            'Decarbonization & Grid Transition',
            4.8,
            4.2,
            true
        );
        $this->assertTrue((bool) $topic1->is_material);
        $this->assertTrue((bool) $topic1->in_reporting_scope);

        // Low scores -> Not material
        $topic2 = $this->service->assessDoubleMateriality(
            'HOSPITAL',
            'TOPIC-PACKAGING',
            'Biodegradable Packaging',
            2.1,
            1.8,
            true
        );
        $this->assertFalse((bool) $topic2->is_material);
        $this->assertFalse((bool) $topic2->in_reporting_scope);
    }

    /**
     * (d) Standards disclosure bridge ensuring gaps are never marked compliant (228.3 & 228.5).
     */
    public function test_standards_disclosure_mapping_gap_integrity(): void
    {
        // Valid metric with evidence
        $validMetric = $this->service->recordMetric('LOGISTICS', 'ENVIRONMENT', 'Fleet Fuel Burn', 'kL', 5200.0, 'LEDGER', 0.92, '2026-Q3');
        $map1 = $this->service->mapDisclosure('GRI', 'GRI-302-1', $validMetric->metric_code, 's3://evidence/fuel_ledger_q3.pdf');
        $this->assertSame('COMPLIANT', $map1->compliance_status);

        // Metric with data gap -> Must be GAP_IDENTIFIED even if evidence URI provided
        $gapMetric = $this->service->recordMetric('LOGISTICS', 'ENVIRONMENT', 'Fleet Tire Waste', 'kg', null, 'MANUAL', 0.50, '2026-Q3', true);
        $map2 = $this->service->mapDisclosure('GRI', 'GRI-306-3', $gapMetric->metric_code, 's3://evidence/waste_gap.pdf');
        $this->assertSame('GAP_IDENTIFIED', $map2->compliance_status);
    }

    /**
     * (e) Group metric aggregation invariant across lines (228.5).
     */
    public function test_group_metric_aggregation(): void
    {
        $this->service->recordMetric('HOTEL', 'ENVIRONMENT', 'Direct Energy Use', 'MWh', 100.5, 'SENSOR', 0.95, '2026-Q3');
        $this->service->recordMetric('CAMPUS', 'ENVIRONMENT', 'Direct Energy Use', 'MWh', 250.5, 'SENSOR', 0.95, '2026-Q3');

        $agg = $this->service->getGroupMetricAggregation('Direct Energy Use', '2026-Q3');
        $this->assertSame(2, $agg['total_lines_reported']);
        $this->assertEquals(351.0, $agg['aggregated_sum']);
    }

    /**
     * (f) Audit status healthy with 0 discrepancies.
     */
    public function test_esg_audit(): void
    {
        $audit = $this->service->audit();
        $this->assertSame('HEALTHY', $audit['status']);
        $this->assertSame(0, $audit['discrepancy_count']);
    }
}
