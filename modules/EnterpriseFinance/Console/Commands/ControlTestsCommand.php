<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Console\Commands;

use Illuminate\Console\Command;
use Modules\EnterpriseFinance\Application\Services\InternalControlTestingService;

class ControlTestsCommand extends Command
{
    protected $signature = 'enterprise:control-tests {--sample-size=50 : Ukuran sampel pengujian}';

    protected $description = 'Menjalankan pengujian otomatis kontrol internal pelaporan keuangan (RCM ICFR)';

    public function handle(InternalControlTestingService $service): int
    {
        $sampleSize = (int) $this->option('sample-size');
        $this->info("Menjalankan uji kontrol internal RCM (ukuran sampel: {$sampleSize})...");

        $runs = $service->runDailyControlTests($sampleSize);

        $tableData = [];
        foreach ($runs as $run) {
            $tableData[] = [
                $run->control_id,
                $run->status,
                $run->sample_count,
                $run->deviation_count,
                $run->task_id ?? '-',
            ];
        }

        $this->table(['Kode Titik Kendali', 'Status Efektivitas', 'Sampel Diuji', 'Penyimpangan', 'Tiket Tugas'], $tableData);
        $this->info('Pengujian kontrol internal selesai dieksekusi.');

        return Command::SUCCESS;
    }
}
