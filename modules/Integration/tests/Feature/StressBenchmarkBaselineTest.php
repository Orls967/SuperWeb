<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\StressBenchmarkBaselineService;
use Tests\TestCase;

class StressBenchmarkBaselineTest extends TestCase
{
    use RefreshDatabase;

    protected StressBenchmarkBaselineService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(StressBenchmarkBaselineService::class);
    }

    public function test_omitted_workload_completeness_rejection_risk(): void
    {
        // 1. Suite with omitted workloads fails completeness check (391.4 & 391.6 Risk)
        try {
            $this->service->recordBenchmarkRun(
                benchmarkCode: 'BMK-DEV-SUITE-01',
                environmentFingerprint: 'CI-ARM64-LINUX-16CORE',
                measuredTps: 15000.0,
                baselineTps: 15500.0,
                suiteCompletenessVerified: false // Omitted workloads!
            );
            $this->fail('Expected exception for benchmark with omitted workloads');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Workloads missing from stress suite against domain registry', $e->getMessage());
        }

        // 2. Complete suite succeeds (391.4)
        $run = $this->service->recordBenchmarkRun(
            benchmarkCode: 'BMK-DEV-SUITE-02',
            environmentFingerprint: 'CI-ARM64-LINUX-16CORE',
            measuredTps: 15000.0,
            baselineTps: 15500.0,
            suiteCompletenessVerified: true
        );
        $this->assertEquals('CI-ARM64-LINUX-16CORE', $run->environment_fingerprint);
        $this->assertTrue((bool) $run->suite_completeness_verified);
        $this->assertTrue((bool) $run->within_variance_band);
    }

    public function test_variance_band_monitoring_edge_case(): void
    {
        // Measured TPS has severe degradation (> 20% drop)
        $run = $this->service->recordBenchmarkRun(
            benchmarkCode: 'BMK-DEV-DEGRADED-01',
            environmentFingerprint: 'CI-ARM64-LINUX-16CORE',
            measuredTps: 8000.0, // 8000 vs 15000 baseline (> 46% degradation!)
            baselineTps: 15000.0,
            suiteCompletenessVerified: true
        );

        $this->assertFalse((bool) $run->within_variance_band);
    }

    public function test_stress_benchmark_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->recordBenchmarkRun('B-AUD', 'ENV1', 1000.0, 1000.0, true);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: out of band run
        DB::table('global_stress_benchmark_profiles')->insert([
            'benchmark_code' => 'B-DEFECT-OUT-OF-BAND',
            'environment_fingerprint' => 'ENV1',
            'measured_tps' => 100.0,
            'within_variance_band' => false, // Discrepancy!
            'suite_completeness_verified' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
