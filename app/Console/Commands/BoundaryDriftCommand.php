<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Modules\Core\Application\Boundaries\ModuleContractDriftDetector;

class BoundaryDriftCommand extends Command
{
    protected $signature = 'boundary:drift {--format=table : Format output (table|json)} {--orphans-only : Tampilkan hanya kontrak tanpa pemanggil}';

    protected $description = 'Pindai pergeseran kontrak (contract drift), interface yatim, dan kesehatan binding container';

    public function handle(ModuleContractDriftDetector $detector): int
    {
        $this->info('Memindai pergeseran kontrak dan pemanggilan interface lintas modul...');

        $report = $detector->detect(base_path());
        $format = (string) $this->option('format');
        $orphansOnly = (bool) $this->option('orphans-only');

        if ($format === 'json') {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->line('');
        $this->info('=== RINGKASAN KESEHATAN KONTRAK MODUL ===');
        $this->table(
            ['Metrik', 'Nilai'],
            [
                ['Total Kontrak Ditemukan', $report['totalContracts']],
                ['Kontrak Terikat di Container', "{$report['boundContracts']} / {$report['totalContracts']}"],
                ['Kontrak Yatim (0 Pemanggil)', count($report['orphanedContracts'])],
                ['Skor Kesehatan Kontrak', "{$report['driftScorePermille']} / 1000 permil"],
                ['Status Peringkat', $report['healthRating']],
            ]
        );

        if ($report['orphanedContracts'] !== []) {
            $this->line('');
            $this->warn('Daftar Kontrak Yatim (Tidak Memiliki Pemanggil di Modul Lain):');
            foreach ($report['orphanedContracts'] as $idx => $orphan) {
                $this->line(sprintf('  [%d] %s', $idx + 1, $orphan));
            }
        }

        if (! $orphansOnly) {
            $this->line('');
            $this->info('Frekuensi Pemanggilan Kontrak Teratas:');
            arsort($report['contractUsageCount']);
            $top10 = array_slice($report['contractUsageCount'], 0, 10, true);
            $usageRows = [];
            foreach ($top10 as $contract => $count) {
                $usageRows[] = [$contract, $count];
            }
            $this->table(['Kontrak Interface', 'Total Pemanggilan'], $usageRows);
        }

        return self::SUCCESS;
    }
}
