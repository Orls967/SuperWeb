<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Quality\Modules\BoundariesScanner;
use Illuminate\Console\Command;
use Modules\Core\Application\Boundaries\BoundaryMetricsExporterService;
use Modules\Core\Domain\Models\CoreBoundaryViolation;

class BoundaryMetricsCommand extends Command
{
    protected $signature = 'boundary:metrics {--format=table : Format keluaran (table|json|csv)} {--out= : Berkas keluaran opsional}';

    protected $description = 'Kompilasi dan ekspor metrik kepatuhan batas modul, waiver, dan skor kontrak';

    public function handle(
        BoundariesScanner $scanner,
        BoundaryMetricsExporterService $exporter
    ): int {
        $format = (string) $this->option('format');
        $out = $this->option('out');

        $analysis = $scanner->scan(base_path(), (array) config('modules'));

        $totalViolations = CoreBoundaryViolation::count();
        $activeWaivers = CoreBoundaryViolation::where('status', 'waived')->count();
        $closedViolations = CoreBoundaryViolation::where('status', 'closed')->count();

        $metricsData = [
            'total_modules' => count($analysis['modules']),
            'total_dependencies' => $analysis['metrics']['totalDependencies'],
            'global_contract_coverage_permille' => $analysis['coverage']['globalScore'],
            'total_recorded_violations' => $totalViolations,
            'active_waivers' => $activeWaivers,
            'closed_violations' => $closedViolations,
            'scc_cycles_count' => count($analysis['scc']['cycles']),
        ];

        if ($format === 'json') {
            $output = (string) json_encode([
                'metrics' => $metricsData,
                'coverage_by_module' => $analysis['coverage']['perModuleScores'],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        } elseif ($format === 'csv') {
            $output = $exporter->exportCoverageCsv($analysis['coverage']);
        } else {
            $this->info('=== METRIK KEPATUHAN BATAS MODUL SUPERWEB ===');
            $this->table(
                ['Indikator', 'Nilai'],
                [
                    ['Total Modul Terdaftar', $metricsData['total_modules']],
                    ['Total Busur Dependensi', $metricsData['total_dependencies']],
                    ['Skor Cakupan Kontrak Global', "{$metricsData['global_contract_coverage_permille']} / 1000 permil"],
                    ['Pelanggaran Tercatat di DB', $metricsData['total_recorded_violations']],
                    ['Waiver Aktif', $metricsData['active_waivers']],
                    ['Pelanggaran Closed', $metricsData['closed_violations']],
                    ['Siklus Terdeteksi (SCC)', $metricsData['scc_cycles_count']],
                ]
            );
            $output = '';
        }

        if ($out !== null && is_string($out) && $output !== '') {
            file_put_contents(base_path($out), $output);
            $this->info("Keluaran berhasil disimpan ke: {$out}");
        } elseif ($output !== '') {
            $this->line($output);
        }

        return self::SUCCESS;
    }
}

