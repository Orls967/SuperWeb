<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Quality\Modules\BoundariesScanner;
use Illuminate\Console\Command;
use Modules\Core\Domain\Algorithms\Boundaries\BlastRadiusCertificateVerifier;
use Modules\Core\Domain\Algorithms\Boundaries\CrossModuleBlastRadiusAnalyzer;

class BoundaryBlastRadiusCommand extends Command
{
    protected $signature = 'boundary:blast-radius {module : Nama modul target yang akan dianalisis} {--format=table : Format output (table|json)}';

    protected $description = 'Hitung jangkauan riak dan radius ledakan perubahan arsitektur lintas modul';

    public function handle(
        BoundariesScanner $scanner,
        CrossModuleBlastRadiusAnalyzer $analyzer,
        BlastRadiusCertificateVerifier $verifier
    ): int {
        $module = trim((string) $this->argument('module'));
        $format = (string) $this->option('format');

        $this->info("Menganalisis radius ledakan untuk modul: {$module}...");

        $analysis = $scanner->scan(base_path(), (array) config('modules'));
        $dependencyGraph = $analysis['dependencyGraph'];

        if (! in_array($module, $analysis['modules'], true)) {
            $this->error("Modul '{$module}' tidak ditemukan dalam daftar modul sistem.");

            return self::FAILURE;
        }

        $report = $analyzer->analyze($module, $dependencyGraph, $analysis['coverage']['perModuleScores']);

        // Verify formal certificate
        if (! $verifier->verify($report, $dependencyGraph)) {
            $this->error('VERIFIKASI GAGAL: Sertifikat matematis blast radius tidak valid.');

            return self::FAILURE;
        }

        if ($format === 'json') {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->line('');
        $this->info("=== HASIL ANALISIS BLAST RADIUS: {$module} ===");
        $this->line("Skor Dampak        : {$report['impactScorePermille']} / 1000 permil");
        $this->line("Maksimal Riak      : {$report['maxRippleDepth']} level kedalaman");
        $this->line('Konsumen Langsung  : '.implode(', ', $report['directConsumers'] ?: ['(tidak ada)']));
        $this->line('Konsumen Transitif : '.implode(', ', $report['transitiveConsumers'] ?: ['(tidak ada)']));
        $this->line('Dependensi Langsung: '.implode(', ', $report['directDependencies'] ?: ['(tidak ada)']));
        $this->line('Dependensi Transitf: '.implode(', ', $report['transitiveDependencies'] ?: ['(tidak ada)']));

        $this->line('');
        $this->info('Jalur Dampak Kritis:');
        foreach ($report['criticalPaths'] as $idx => $path) {
            $this->line(sprintf('  [%d] %s', $idx + 1, implode(' -> ', $path)));
        }

        $this->line('');
        $this->info('Rekomendasi Mitigasi:');
        foreach ($report['mitigationRecommendations'] as $idx => $rec) {
            $this->line(sprintf('  * %s', $rec));
        }

        return self::SUCCESS;
    }
}

