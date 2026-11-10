<?php

declare(strict_types=1);

namespace Modules\Core\Application\Services;

use Modules\Core\Application\Actions\Search\SearchOrchestratorService;

/**
 * Service benchmarking performa kueri pencarian (Latency SLA Benchmark & Percentiles).
 */
class SearchPerformanceBenchmarkService
{
    public function __construct(
        protected SearchOrchestratorService $searchOrchestrator,
    ) {}

    /**
     * Menjalankan kueri uji coba berturut-turut untuk mengukur distribusi latensi.
     *
     * @param  string[]  $sampleQueries
     * @return array{
     *     iterations: int,
     *     min_ms: float,
     *     max_ms: float,
     *     p50_ms: float,
     *     p90_ms: float,
     *     p99_ms: float,
     *     avg_ms: float
     * }
     */
    public function runBenchmark(array $sampleQueries = ['fleet', 'kontrak', 'invoice', 'mesin', 'vessel'], int $iterations = 20): array
    {
        $latencies = [];

        for ($i = 0; $i < $iterations; $i++) {
            $q = $sampleQueries[$i % count($sampleQueries)];
            $start = microtime(true);
            $this->searchOrchestrator->search($q, null);
            $latencies[] = round((microtime(true) - $start) * 1000, 2);
        }

        sort($latencies);
        $count = count($latencies);

        $min = $latencies[0];
        $max = $latencies[$count - 1];
        $avg = round(array_sum($latencies) / $count, 2);

        $p50Index = (int) floor($count * 0.50);
        $p90Index = (int) floor($count * 0.90);
        $p99Index = (int) floor($count * 0.99);

        return [
            'iterations' => $count,
            'min_ms' => $min,
            'max_ms' => $max,
            'p50_ms' => $latencies[$p50Index],
            'p90_ms' => $latencies[$p90Index],
            'p99_ms' => $latencies[$p99Index],
            'avg_ms' => $avg,
        ];
    }
}
