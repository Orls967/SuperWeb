<?php

declare(strict_types=1);

namespace Modules\Core\Application\Services;

use App\Models\User;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

final class PlatformBenchmarkService
{
    /**
     * 12 Endpoint rute operasional kritis untuk uji latensi respon p95.
     */
    public const CRITICAL_ROUTES = [
        ['name' => 'Hospital Trials & Lab', 'uri' => '/hospital'],
        ['name' => 'Hotel Frontdesk & PMS', 'uri' => '/hotel'],
        ['name' => 'Venue Gates & Operations', 'uri' => '/venue'],
        ['name' => 'Mining Fleet Dispatch', 'uri' => '/mining'],
        ['name' => 'Proptech Digital Twin', 'uri' => '/proptech'],
        ['name' => 'Wealth & Robo-Advisor', 'uri' => '/wealth'],
        ['name' => 'EV Charging Stations', 'uri' => '/ev'],
        ['name' => 'Fleet Vehicle Leasing', 'uri' => '/fleet'],
        ['name' => 'InsurTech & Claims', 'uri' => '/insurance'],
        ['name' => 'CloudKitchen & Dispatch', 'uri' => '/cloud-kitchen'],
        ['name' => 'Telematics Ingestion', 'uri' => '/telematics'],
        ['name' => 'RWA Tokenization Engine', 'uri' => '/rwa'],
    ];

    public function __construct(
        private readonly ?HttpKernel $httpKernel = null
    ) {}

    /**
     * Menjalankan suite benchmark penuh: p95 latensi rute kritis, durasi audit, dan seeder throughput.
     *
     * @return array{
     *     timestamp: string,
     *     commit: string,
     *     php_version: string,
     *     database_driver: string,
     *     iterations: int,
     *     assert_p95_ms: int,
     *     routes: array<string, array{p50_ms: float, p90_ms: float, p95_ms: float, max_ms: float, status_code: int}>,
     *     audits: array<string, array{exit_code: int, duration_ms: float}>,
     *     seed_throughput: array<string, mixed>,
     *     passed: bool
     * }
     */
    public function runBenchmark(int $iterations = 20, int $assertP95Ms = 500): array
    {
        $kernel = $this->httpKernel ?? app(HttpKernel::class);

        // Ensure benchmark user with admin role exists
        $user = User::firstOrCreate(
            ['email' => 'benchmark-runner@example.com'],
            [
                'name' => 'Benchmark Runner',
                'password' => bcrypt('password'),
                'role' => 'admin',
            ]
        );

        Auth::login($user);

        $results = [
            'timestamp' => now()->toIso8601String(),
            'commit' => $this->resolveGitCommit(),
            'php_version' => PHP_VERSION,
            'database_driver' => DB::connection()->getDriverName(),
            'iterations' => $iterations,
            'assert_p95_ms' => $assertP95Ms,
            'routes' => [],
            'audits' => [],
            'seed_throughput' => $this->resolveSeedThroughputMetrics(),
            'passed' => true,
        ];

        // 1. Benchmark Audit Commands Durations (microsecond precision via hrtime)
        $auditCommands = ['bank:reconcile', 'chain:audit-all'];
        foreach ($auditCommands as $cmd) {
            $t0 = hrtime(true);
            $exitCode = Artisan::call($cmd);
            $durMs = (hrtime(true) - $t0) / 1_000_000;

            $results['audits'][$cmd] = [
                'exit_code' => $exitCode,
                'duration_ms' => round($durMs, 2),
            ];

            if ($exitCode !== 0) {
                $results['passed'] = false;
            }
        }

        // 2. Benchmark Endpoint Latency
        foreach (self::CRITICAL_ROUTES as $route) {
            $uri = $route['uri'];
            $name = $route['name'];
            $latencies = [];
            $lastStatusCode = 200;

            // Warm-up request
            $warmupReq = Request::create($uri, 'GET');
            $warmupReq->setUserResolver(fn () => $user);
            $kernel->handle($warmupReq);

            for ($iter = 0; $iter < $iterations; $iter++) {
                $req = Request::create($uri, 'GET');
                $req->setUserResolver(fn () => $user);

                $t0 = hrtime(true);
                $resp = $kernel->handle($req);
                $elapsedMs = (hrtime(true) - $t0) / 1_000_000;
                $lastStatusCode = $resp->getStatusCode();

                $latencies[] = $elapsedMs;
            }

            sort($latencies);
            $count = count($latencies);
            $p50Index = (int) floor($count * 0.50);
            $p90Index = (int) min($count - 1, ceil($count * 0.90) - 1);
            $p95Index = (int) min($count - 1, ceil($count * 0.95) - 1);

            $p50 = round($latencies[$p50Index], 2);
            $p90 = round($latencies[$p90Index], 2);
            $p95 = round($latencies[$p95Index], 2);
            $max = round(max($latencies), 2);

            $results['routes'][$name] = [
                'uri' => $uri,
                'status_code' => $lastStatusCode,
                'p50_ms' => $p50,
                'p90_ms' => $p90,
                'p95_ms' => $p95,
                'max_ms' => $max,
            ];

            // If p95 exceeds assertion threshold or status >= 500, mark as failed
            if ($p95 > $assertP95Ms || $lastStatusCode >= 500) {
                $results['passed'] = false;
            }
        }

        return $results;
    }

    public function saveBenchmarkArtifact(array $data, ?string $customPath = null): string
    {
        $dir = storage_path('app/benchmarks');
        File::ensureDirectoryExists($dir);

        $path = $customPath ?? ($dir.'/benchmark-'.date('Y-m-d').'.json');
        File::put($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        // Always update benchmark-latest.json
        File::put($dir.'/benchmark-latest.json', json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $path;
    }

    private function resolveGitCommit(): string
    {
        $hash = trim((string) @shell_exec('git rev-parse HEAD 2>/dev/null'));

        return $hash !== '' ? $hash : 'unknown';
    }

    private function resolveSeedThroughputMetrics(): array
    {
        $checkpoints = DB::table('sim_scale_checkpoints')->get();
        $totalRecords = $checkpoints->sum('total_inserted');

        return [
            'total_checkpoints' => $checkpoints->count(),
            'total_records_provisioned' => (int) $totalRecords,
            'status' => $checkpoints->isNotEmpty() ? 'ready' : 'empty',
        ];
    }
}
