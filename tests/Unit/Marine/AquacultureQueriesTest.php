<?php

declare(strict_types=1);

namespace Tests\Unit\Marine;

use Database\Seeders\MarineAquacultureT1Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Marine\Application\Queries\AquacultureTraceabilitySearchQuery;
use Modules\Marine\Application\Queries\BlueCarbonRegistryReportQuery;
use Modules\Marine\Application\Queries\ExportComplianceAnalyticsQuery;
use Tests\TestCase;

class AquacultureQueriesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MarineAquacultureT1Seeder::class);
    }

    public function test_traceability_search_query_filters_and_returns_full_lineage(): void
    {
        $query = new AquacultureTraceabilitySearchQuery;

        $results = $query->searchLots(['commercial_grade' => 'GRADE_A1']);

        $this->assertNotEmpty($results);
        $first = $results[0];
        $this->assertStringContainsString('LOT-EXP-2026-USA-001', $first['lot_tracking_number']);
        $this->assertEquals('PT Benur Indah Makmur (SPF Center)', $first['hatchery_name']);
        $this->assertEquals('PASSED_FOR_EXPORT', $first['sps_verdict']);
    }

    public function test_blue_carbon_registry_report_query_summarizes_portfolio(): void
    {
        $query = new BlueCarbonRegistryReportQuery;

        $summary = $query->getExecutiveSummary();

        $this->assertGreaterThan(0, $summary['total_active_projects']);
        $this->assertGreaterThan(0.0, $summary['total_restoration_hectares']);
        $this->assertGreaterThan(0.0, $summary['total_issued_credits_tco2e']);
        $this->assertArrayHasKey('VERRA_VM0033', $summary['registry_standard_distribution']);
    }

    public function test_export_compliance_analytics_query_calculates_rates(): void
    {
        $query = new ExportComplianceAnalyticsQuery;

        $metrics = $query->getComplianceMetrics();

        $this->assertGreaterThan(0, $metrics['total_assays_conducted']);
        $this->assertEquals(100.0, $metrics['compliance_rate_percentage']);
        $this->assertEquals(0, $metrics['zero_tolerance_chloramphenicol_breaches']);
    }
}
