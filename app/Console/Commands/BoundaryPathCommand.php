<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Quality\Modules\BoundariesScanner;
use Illuminate\Console\Command;
use Modules\Core\Domain\Algorithms\Boundaries\ModuleDependencyPathFinder;
use Modules\Core\Domain\Algorithms\Boundaries\PathFinderCertificateVerifier;

class BoundaryPathCommand extends Command
{
    protected $signature = 'boundary:path {source : Modul sumber} {target : Modul tujuan} {--max-depth=6 : Kedalaman maksimum pencarian} {--json : Format keluaran JSON}';

    protected $description = 'Temukan seluruh jalur ketergantungan dan titik leher botol (bottlenecks) antara dua modul';

    public function handle(
        BoundariesScanner $scanner,
        ModuleDependencyPathFinder $finder,
        PathFinderCertificateVerifier $verifier
    ): int {
        $source = (string) $this->argument('source');
        $target = (string) $this->argument('target');
        $maxDepth = (int) $this->option('max-depth');

        $this->info("Mencari jalur dependensi dari [{$source}] ke [{$target}]...");

        $analysis = $scanner->scan(base_path(), (array) config('modules'));
        $graph = $analysis['dependencyGraph'];

        if (! in_array($source, $analysis['modules'], true)) {
            $this->error("Modul sumber '{$source}' tidak ditemukan.");

            return self::FAILURE;
        }

        if (! in_array($target, $analysis['modules'], true)) {
            $this->error("Modul tujuan '{$target}' tidak ditemukan.");

            return self::FAILURE;
        }

        $report = $finder->findPaths($source, $target, $graph, $maxDepth);

        // Verify formal certificate
        if (! $verifier->verify($report, $graph)) {
            $this->error('VERIFIKASI GAGAL: Sertifikat matematis penelusuran jalur tidak valid.');

            return self::FAILURE;
        }

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->line('');
        $this->info("=== HASIL ANALISIS JALUR DEPENDENSI: {$source} -> {$target} ===");
        $this->line("Total Jalur Ditemukan : {$report['pathCount']}");
        $this->line("Lompatan Terpendek    : {$report['shortestHops']} hops");
        $this->line('Jalur Terpendek       : '.implode(' -> ', $report['shortestPath'] ?: ['(tidak ada jalur langsung)']));
        $this->line('Titik Leher Botol     : '.implode(', ', $report['bottleneckNodes'] ?: ['(tidak ada simpul wajib)']));

        if ($report['allPaths'] !== []) {
            $this->line('');
            $this->info('Daftar Seluruh Jalur Sederhana:');
            foreach ($report['allPaths'] as $idx => $path) {
                $this->line(sprintf('  [%d] %s (%d hops)', $idx + 1, implode(' -> ', $path), count($path) - 1));
            }
        }

        return self::SUCCESS;
    }
}

