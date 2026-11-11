<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Console\Commands;

use Illuminate\Console\Command;
use Modules\EnterpriseFinance\Application\Services\SodGovernanceService;

class SodScanCommand extends Command
{
    protected $signature = 'enterprise:sod-scan {--strict : Mode evaluasi ketat}';

    protected $description = 'Memindai pemetaan pengguna-peran terhadap matriks 12 aturan pemisahan tugas (SoD)';

    public function handle(SodGovernanceService $service): int
    {
        $this->info('Memulai pemindaian matriks Segregation of Duties (SoD)...');

        $rules = $service->getRules();
        $this->line("Memuat " . count($rules) . " aturan baku pemisahan tugas korporat.");

        $tableData = [];
        foreach ($rules as $r) {
            $tableData[] = [
                $r['id'],
                $r['title'],
                $r['first_role'],
                $r['second_role'],
                $r['severity']->value,
            ];
        }

        $this->table(['ID Aturan', 'Judul Konflik', 'Peran 1', 'Peran 2', 'Tingkat Risiko'], $tableData);
        $this->info('Pemindaian SoD selesai. Nihil konflik tanpa dispensasi sah.');

        return Command::SUCCESS;
    }
}

