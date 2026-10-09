<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Integration\Application\Services\PartitionArchiveService;
use Tests\TestCase;

/**
 * Fase 192 — Skala: Partisi, Arsip & Query Budget 30 Lini Tests
 *
 * Covers:
 *  (a) cold archive recall validates SHA-256 checksum integrity
 *  (b) checksum mismatch blocks recall with exception
 *  (c) materialized domain rollup aggregates summary
 *  (d) query budget detects regression in queries count or latency
 *  (e) partition:audit = 0 discrepancy
 */
class PartitionArchiveTest extends TestCase
{
    use RefreshDatabase;

    protected PartitionArchiveService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(PartitionArchiveService::class);
    }

    /**
     * (a) & (b) Cold archive storage and SHA-256 recall integrity.
     */
    public function test_cold_archive_recall_integrity(): void
    {
        $payload = '{"partition":"2021-01","records":[{"id":1,"amount":1000}]}';
        $archive = $this->service->archivePartition('tel_telematics_events', '2021-01', 1, $payload);

        $this->assertSame('ARCHIVED', $archive->status);
        $this->assertNotNull($archive->checksum_sha256);

        // 1. Valid recall
        $recalled = $this->service->recallPartition($archive->archive_key, $payload);
        $this->assertSame('RECALLED', $recalled->status);

        // 2. Corrupted payload recall attempt -> Exception
        try {
            $this->service->recallPartition($archive->archive_key, 'tampered-payload-bytes');
            $this->fail('Expected exception for archive checksum mismatch.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('SHA-256 checksum mismatch', $e->getMessage());
        }
    }

    /**
     * (c) Materialized domain rollup summary.
     */
    public function test_domain_rollup_summary(): void
    {
        $rollup = $this->service->recordRollup('L13_RETAIL', '2026-10-08', 450000000.0, 1250);

        $this->assertSame('L13_RETAIL', $rollup->domain_code);
        $this->assertEquals(450000000.00, (float) $rollup->total_amount_idr);
        $this->assertSame(1250, (int) $rollup->total_events);
    }

    /**
     * (d) Query budget registry regression detection.
     */
    public function test_query_budget_regression_detection(): void
    {
        // 1. Within budget: max 5 queries / 100ms. Actual: 3 queries / 45ms -> OK
        $b1 = $this->service->registerAndCheckBudget('/api/v1/group/daily-cockpit', 5, 100.0, 3, 45.0);
        $this->assertFalse((bool) $b1->budget_breached);

        // 2. Regression: actual queries 8 > max 5 -> Breached
        $b2 = $this->service->registerAndCheckBudget('/api/v1/group/daily-cockpit', 5, 100.0, 8, 55.0);
        $this->assertTrue((bool) $b2->budget_breached);
    }

    /**
     * (e) Audit status healthy with 0 discrepancies (when budgets restored).
     */
    public function test_partition_archive_audit(): void
    {
        // Reset endpoint budget to normal
        $this->service->registerAndCheckBudget('/api/v1/group/daily-cockpit', 10, 100.0, 3, 45.0);

        $audit = $this->service->audit();
        $this->assertSame('HEALTHY', $audit['status']);
        $this->assertSame(0, $audit['discrepancy_count']);
    }
}
