<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Modules\Core\Application\Boundaries\BoundaryComplianceReportService;

class BoundaryComplianceCommand extends Command
{
    protected $signature = 'boundary:compliance {--format=table : Format output (table|json)} {--min-grade= : Filter modul dengan peringkat minimal (A+, A, B, C, D, F)}';

    protected $description = 'Kompilasi laporan kepatuhan eksekutif, scorecard peringkat modul, dan status SLA waiver';

    public function handle(BoundaryComplianceReportService $reportService): int
    {
        $this->info('Mengompilasi laporan kepatuhan arsitektur platform...');

        $report = $reportService->generateReport(base_path());
        $format = (string) $this->option('format');

        if ($format === 'json') {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->line('');
        $this->info('=== SCORECARD KEPATUHAN ARSITEKTUR PLATFORM ===');
        $this->table(
            ['Metrik Eksekutif', 'Status / Nilai'],
            [
                ['Indeks Modularitas Platform', "{$report['platformModularityIndex']} / 1000 permil"],
                ['Peringkat Eksekutif', $report['executiveRating']],
                ['Total Pelanggaran Terdaftar', $report['totalViolationsTracked']],
                ['Waiver Aktif', $report['activeWaiversCount']],
                ['Waiver Kedaluwarsa (Breached SLA)', $report['slaStatus']['breachedCount']],
                ['Waiver Mendekati Batas (<= 7 Hari)', $report['slaStatus']['nearBreachCount']],
                ['Waiver Kepatuhan Sehat', $report['slaStatus']['compliantCount']],
            ]
        );

        $this->line('');
        $this->info('Rekomendasi Tindakan Prioritas Utama:');
        foreach ($report['topPriorityActions'] as $idx => $action) {
            $this->line(sprintf('  [%d] %s', $idx + 1, $action));
        }

        $this->line('');
        $this->info('Sampel Peringkat Modul Terpilih:');
        $sampleModules = ['Core', 'AutoServe', 'Logistics', 'Mall', 'Store', 'Pricing', 'Banking'];
        $rows = [];
        foreach ($sampleModules as $sm) {
            $info = $report['moduleGrades'][$sm] ?? null;
            if ($info !== null) {
                $rows[] = [
                    $sm,
                    $info['grade'],
                    "{$info['coveragePermille']} permil",
                    $info['activeViolations'],
                    $info['riskStatus'],
                ];
            }
        }

        $this->table(['Modul', 'Grade', 'Cakupan Kontrak', 'Pelanggaran Aktif', 'Status Risiko'], $rows);

        return self::SUCCESS;
    }
}

