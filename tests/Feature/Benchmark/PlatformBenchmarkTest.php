<?php

declare(strict_types=1);

namespace Tests\Feature\Benchmark;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Modules\Core\Application\Services\PlatformBenchmarkService;
use Tests\TestCase;

class PlatformBenchmarkTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_benchmark_service_measures_latencies_and_audits(): void
    {
        /** @var PlatformBenchmarkService $service */
        $service = app(PlatformBenchmarkService::class);

        $result = $service->runBenchmark(iterations: 3, assertP95Ms: 1500);

        $this->assertIsArray($result);
        $this->assertTrue($result['passed']);
        $this->assertArrayHasKey('bank:reconcile', $result['audits']);
        $this->assertArrayHasKey('chain:audit-all', $result['audits']);
        $this->assertSame(0, $result['audits']['bank:reconcile']['exit_code']);
        $this->assertSame(0, $result['audits']['chain:audit-all']['exit_code']);

        $this->assertCount(12, $result['routes']);
        foreach ($result['routes'] as $name => $metrics) {
            $this->assertArrayHasKey('p95_ms', $metrics);
            $this->assertLessThanOrEqual(1500, $metrics['p95_ms']);
            $this->assertSame(200, $metrics['status_code']);
        }

        // Test saving artifact
        $path = $service->saveBenchmarkArtifact($result);
        $this->assertFileExists($path);
        $this->assertFileExists(storage_path('app/benchmarks/benchmark-latest.json'));

        $decoded = json_decode((string) File::get($path), true);
        $this->assertSame($result['timestamp'], $decoded['timestamp']);
    }

    public function test_artisan_benchmark_run_command_exits_cleanly(): void
    {
        $exit = Artisan::call('benchmark:run', [
            '--iterations' => 2,
            '--assert-p95' => 1500,
        ]);

        $this->assertSame(0, $exit);
    }
}
