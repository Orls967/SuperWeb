<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Integration\Application\Services\ScaleBenchmarkService;
use Tests\TestCase;

/**
 * Fase 191 — Skala Gelombang 3: Seeder 30 Lini Ultra & Benchmark Tests
 *
 * Covers:
 *  (a) seeder idempotent execution across domains with ledger balance Σ=0
 *  (b) seeder fails if debit does not equal credit
 *  (c) benchmark execution metrics recording (throughput, memory, latency)
 *  (d) scale:audit = 0 discrepancy
 */
class ScaleBenchmarkTest extends TestCase
{
    use RefreshDatabase;

    protected ScaleBenchmarkService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ScaleBenchmarkService::class);
    }

    /**
     * (a) & (b) Seeder checkpointing and ledger parity.
     */
    public function test_seeder_checkpoint_idempotent_and_balanced(): void
    {
        // 1. Valid balanced seeder: 100,000 records, Rp 10B debit & credit
        $cp1 = $this->service->recordSeederCheckpoint('L27_PORTS', 100000, 10000000000.0, 10000000000.0);
        $this->assertSame('COMPLETED', $cp1->status);
        $this->assertSame(100000, (int) $cp1->total_records_seeded);

        // Idempotent re-run updates without duplicate keys
        $cp2 = $this->service->recordSeederCheckpoint('L27_PORTS', 100000, 10000000000.0, 10000000000.0);
        $this->assertSame($cp1->domain_code, $cp2->domain_code);

        // 2. Unbalanced ledger -> Exception
        try {
            $this->service->recordSeederCheckpoint('L26_AVIATION', 50000, 5000000000.0, 4999000000.0);
            $this->fail('Expected exception for unbalanced seeder ledger.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Seeder ledger imbalance in L26_AVIATION', $e->getMessage());
        }
    }

    /**
     * (c) Benchmark performance tracking.
     */
    public function test_benchmark_performance_recording(): void
    {
        $bm = $this->service->recordBenchmark(
            'L29_TELECOM',
            'INGEST_TELEMATICS_BATCH',
            500000,
            124.50, // 124.5 ms
            64.20   // 64.2 MB peak memory
        );

        $this->assertNotNull($bm->benchmark_key);
        $this->assertSame('L29_TELECOM', $bm->domain_code);
        $this->assertEquals(124.50, (float) $bm->elapsed_ms);
    }

    /**
     * (d) Audit status healthy with 0 discrepancies.
     */
    public function test_scale_benchmark_audit(): void
    {
        $audit = $this->service->audit();
        $this->assertSame('HEALTHY', $audit['status']);
        $this->assertSame(0, $audit['discrepancy_count']);
    }
}
