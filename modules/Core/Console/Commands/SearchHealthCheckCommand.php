<?php

declare(strict_types=1);

namespace Modules\Core\Console\Commands;

use Illuminate\Console\Command;
use Modules\Core\Application\Services\SearchDiagnosticsService;

class SearchHealthCheckCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'search:health-check';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Memeriksa skor kesehatan dan mendiagnosa status indeks pencarian global';

    /**
     * Execute the console command.
     */
    public function handle(SearchDiagnosticsService $diagnostics): int
    {
        $this->info('Menjalankan Pemeriksaan Kesehatan Sistem Pencarian Global...');

        $report = $diagnostics->runDiagnostics();
        $health = $report['health'];

        $this->info(sprintf('Status Kesehatan: [%s] (Skor: %s / 100)', $health['status'], $health['overall_score']));

        $this->table(
            ['Dimensi', 'Skor'],
            [
                ['Latensi Kueri', $health['dimensions']['latency_score']],
                ['Rasio Zero-Result', $health['dimensions']['zero_result_score']],
                ['Kepadatan Term', $health['dimensions']['density_score']],
                ['Kebaruan Dokumen', $health['dimensions']['freshness_score']],
                ['Integritas Struktur', $health['dimensions']['integrity_score']],
            ]
        );

        $this->info("\nRekomendasi Tindakan:");
        foreach ($report['action_items'] as $item) {
            $this->line(' • '.$item);
        }

        return $health['overall_score'] >= 60.0 ? 0 : 1;
    }
}
