<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Quality\Modules\BoundariesScanner;
use Illuminate\Console\Command;

class ArchBoundariesCommand extends Command
{
    protected $signature = 'arch:boundaries
        {--format=table : Format keluaran (table|json|md|svg)}
        {--out= : Path berkas tujuan keluaran}
        {--path=* : Direktori modul yang dipindai (bawaan: modules)}';

    protected $description = 'Pindai peta dependensi modul, siklus SCC, kepemilikan data, dan skor cakupan kontrak';

    public function handle(BoundariesScanner $scanner): int
    {
        $paths = $this->option('path') ?: ['modules'];
        $analysis = $scanner->scan(base_path(), (array) config('modules'), $paths);

        $format = strtolower((string) $this->option('format'));
        $output = match ($format) {
            'json' => $this->formatJson($analysis),
            'md', 'markdown' => $this->formatMarkdown($analysis),
            'svg' => $this->formatSvg($analysis),
            default => null,
        };

        if ($output !== null) {
            $outPath = $this->option('out');
            if ($outPath) {
                $dir = dirname((string) $outPath);
                if (! is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }
                file_put_contents((string) $outPath, $output);
                $this->info("Keluaran ditulis ke: {$outPath}");
            } else {
                $this->line($output);
            }

            return self::SUCCESS;
        }

        // Default: Table format
        $this->info('=== RINGKASAN PETA DEPENDENSI MODUL ===');
        $this->table(
            ['Metrik', 'Nilai'],
            [
                ['Total Modul', $analysis['metrics']['totalModules']],
                ['Total Dependensi Lintas Modul', $analysis['metrics']['totalDependencies']],
                ['Total Pelanggaran Batas', $analysis['metrics']['totalViolations']],
                ['Skor Cakupan Kontrak Global', sprintf('%.1f%% (%d permil)', $analysis['metrics']['globalCoveragePermille'] / 10, $analysis['metrics']['globalCoveragePermille'])],
                ['Jumlah Siklus SCC Terlarang', $analysis['metrics']['cyclesCount']],
            ]
        );

        if ($analysis['scc']['hasCycles']) {
            $this->error('Siklus dependensi terlarang ditemukan:');
            foreach ($analysis['scc']['cycles'] as $idx => $cycle) {
                $this->line(sprintf('  [%d] %s -> %s', $idx + 1, implode(' -> ', $cycle), $cycle[0]));
            }
        } else {
            $this->info('Graf dependensi bersih: topologi adalah Directed Acyclic Graph (DAG).');
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $analysis
     */
    private function formatJson(array $analysis): string
    {
        return json_encode($analysis, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n";
    }

    /**
     * @param  array<string, mixed>  $analysis
     */
    private function formatMarkdown(array $analysis): string
    {
        $md = [];
        $md[] = '# Katalog Dependensi Modul SuperWeb';
        $md[] = '';
        $md[] = 'Dokumen ini dihasilkan secara deterministik oleh perintah `php artisan arch:boundaries --format=md`.';
        $md[] = '';
        $md[] = '## 1. Ringkasan Eksekutif';
        $md[] = sprintf('- **Total Modul**: %d modul terdaftar', $analysis['metrics']['totalModules']);
        $md[] = sprintf('- **Total Busur Dependensi**: %d dependensi', $analysis['metrics']['totalDependencies']);
        $md[] = sprintf('- **Total Pelanggaran Batas**: %d pelanggaran', $analysis['metrics']['totalViolations']);
        $md[] = sprintf('- **Skor Cakupan Kontrak**: %d permil (%.1f%%)', $analysis['metrics']['globalCoveragePermille'], $analysis['metrics']['globalCoveragePermille'] / 10);
        $md[] = sprintf('- **Jumlah Siklus Dependensi**: %d siklus', $analysis['metrics']['cyclesCount']);
        $md[] = '';
        $md[] = '## 2. Diagram Graf Dependensi (Mermaid)';
        $md[] = '```mermaid';
        $md[] = 'graph TD';

        $edges = [];
        foreach ($analysis['dependencyGraph'] as $u => $targets) {
            foreach ($targets as $v) {
                $edges[] = "    {$u} --> {$v}";
            }
        }
        sort($edges, SORT_STRING);
        $md = array_merge($md, $edges);

        $md[] = '```';
        $md[] = '';
        $md[] = '## 3. Komponen Terhubung Kuat (SCC)';
        $md[] = '| ID SCC | Jumlah Modul | Anggota Modul |';
        $md[] = '|---|---|---|';
        foreach ($analysis['scc']['components'] as $id => $comp) {
            $md[] = sprintf('| SCC-%02d | %d | %s |', $id + 1, count($comp), implode(', ', $comp));
        }

        $md[] = '';
        $md[] = '## 4. Skor Cakupan Kontrak per Modul';
        $md[] = '| Modul Pemanggil | Skor Permil | Persentase |';
        $md[] = '|---|---|---|';
        $scores = $analysis['coverage']['perModuleScores'];
        ksort($scores);
        foreach ($scores as $mod => $score) {
            $md[] = sprintf('| %s | %d | %.1f%% |', $mod, $score, $score / 10);
        }

        return implode("\n", $md)."\n";
    }

    /**
     * @param  array<string, mixed>  $analysis
     */
    private function formatSvg(array $analysis): string
    {
        $modules = $analysis['modules'];
        $cols = 8;
        $cellWidth = 140;
        $cellHeight = 60;
        $padding = 20;

        $rows = (int) ceil(count($modules) / $cols);
        $width = $cols * $cellWidth + $padding * 2;
        $height = $rows * $cellHeight + $padding * 2 + 60;

        $svg = [];
        $svg[] = sprintf('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %d %d" width="%d" height="%d">', $width, $height, $width, $height);
        $svg[] = '  <style>';
        $svg[] = '    .title { font-family: monospace; font-size: 16px; font-weight: bold; fill: #1e293b; }';
        $svg[] = '    .box { fill: #f8fafc; stroke: #cbd5e1; stroke-width: 1.5px; rx: 6px; }';
        $svg[] = '    .box-text { font-family: monospace; font-size: 12px; fill: #0f172a; text-anchor: middle; dominant-baseline: middle; }';
        $svg[] = '    .sub-text { font-family: monospace; font-size: 9px; fill: #64748b; text-anchor: middle; dominant-baseline: middle; }';
        $svg[] = '  </style>';
        $svg[] = '  <rect width="100%" height="100%" fill="#ffffff" />';
        $svg[] = sprintf('  <text x="%d" y="30" class="title">Peta Arsitektur Modul SuperWeb (Total: %d Modul)</text>', $padding, count($modules));

        foreach ($modules as $idx => $mod) {
            $r = (int) floor($idx / $cols);
            $c = $idx % $cols;
            $x = $padding + $c * $cellWidth;
            $y = $padding + 50 + $r * $cellHeight;

            $deps = count($analysis['dependencyGraph'][$mod] ?? []);
            $score = $analysis['coverage']['perModuleScores'][$mod] ?? 1000;

            $svg[] = sprintf('  <g transform="translate(%d,%d)">', $x, $y);
            $svg[] = '    <rect width="125" height="48" class="box" />';
            $svg[] = sprintf('    <text x="62" y="18" class="box-text">%s</text>', htmlspecialchars($mod));
            $svg[] = sprintf('    <text x="62" y="34" class="sub-text">deps: %d | cov: %d‰</text>', $deps, $score);
            $svg[] = '  </g>';
        }

        $svg[] = '</svg>'."\n";

        return implode("\n", $svg);
    }
}
