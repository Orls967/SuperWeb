<?php

declare(strict_types=1);

namespace Modules\Core\Console\Commands;

use Illuminate\Console\Command;
use Modules\Core\Application\Services\PlatformBenchmarkService;

final class BenchmarkRunCommand extends Command
{
    protected $signature = 'benchmark:run
        {--iterations=15 : Jumlah iterasi per endpoint}
        {--assert-p95=500 : Ambang batas p95 maksimal dalam milidetik}
        {--output= : Path file output JSON kustom}';

    protected $description = 'Jalankan benchmark suite platform: latensi p95 endpoint kritis dan durasi audit finansial (PROGRESS R9.2)';

    public function handle(PlatformBenchmarkService $service): int
    {
        $iterations = max(1, (int) $this->option('iterations'));
        $assertP95 = max(10, (int) $this->option('assert-p95'));
        $customOutput = $this->option('output');

        $this->info('=== Platform Benchmark Suite (Fase R9.2) ===');
        $this->line("Iterasi per rute: <comment>{$iterations}</comment>");
        $this->line("Ambang batas p95: <comment>{$assertP95} ms</comment>\n");

        $this->line('Menjalankan pengujian performa endpoint dan audit finansial...');
        $data = $service->runBenchmark(iterations: $iterations, assertP95Ms: $assertP95);

        // 1. Tampilkan tabel durasi audit
        $this->info("\n[1] Durasi Audit Finansial & State Chains");
        $auditRows = [];
        foreach ($data['audits'] as $cmd => $info) {
            $status = $info['exit_code'] === 0 ? '<info>PASS 🟢</info>' : '<error>FAIL 🔴</error>';
            $auditRows[] = [
                $cmd,
                $info['duration_ms'].' ms',
                $status,
            ];
        }
        $this->table(['Command Audit', 'Durasi Eksekusi', 'Status'], $auditRows);

        // 2. Tampilkan tabel latensi p95 endpoint kritis
        $this->info("\n[2] Latensi Respon Endpoint Kritis (p50, p90, p95)");
        $routeRows = [];
        foreach ($data['routes'] as $name => $metrics) {
            $p95Formatted = $metrics['p95_ms'] <= $assertP95
                ? "<info>{$metrics['p95_ms']} ms</info>"
                : "<error>{$metrics['p95_ms']} ms (MELEBIHI {$assertP95}ms)</error>";

            $routeRows[] = [
                $name,
                $metrics['uri'],
                $metrics['p50_ms'].' ms',
                $metrics['p90_ms'].' ms',
                $p95Formatted,
                $metrics['max_ms'].' ms',
                $metrics['status_code'],
            ];
        }
        $this->table(['Pilar / Endpoint', 'URI', 'p50', 'p90', 'p95', 'Max', 'HTTP'], $routeRows);

        // 3. Simpan artefak
        $savedPath = $service->saveBenchmarkArtifact($data, is_string($customOutput) ? $customOutput : null);
        $this->info("\nArtefak benchmark disimpan di: <comment>{$savedPath}</comment>");
        $this->line('Artefak terkini disalin ke: <comment>storage/app/benchmarks/benchmark-latest.json</comment>');

        if (! $data['passed']) {
            $this->error("\n❌ Benchmark GAGAL: Terjadi pelanggaran ambang batas p95 atau error pada endpoint/audit.");

            return self::FAILURE;
        }

        $this->info("\n✅ Seluruh endpoint dan audit lulus ambang batas benchmark (p95 < {$assertP95}ms)!");

        return self::SUCCESS;
    }
}
