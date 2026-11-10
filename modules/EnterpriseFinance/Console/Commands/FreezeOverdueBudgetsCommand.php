<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Console\Commands;

use Illuminate\Console\Command;
use Modules\EnterpriseFinance\Application\Services\EnterpriseFinanceService;

class FreezeOverdueBudgetsCommand extends Command
{
    protected $signature = 'ef:freeze-overdue-budgets {--year= : Tahun fiskal anggaran (mis. 2026)}';

    protected $description = 'Membekukan otomatis departemen anggaran yang telah menyerap 100% pagu alokasi';

    public function handle(EnterpriseFinanceService $service): int
    {
        $year = (string) ($this->option('year') ?: date('Y'));

        $this->info("Memeriksa anggaran yang melebihi batas utilisasi untuk tahun fiskal {$year}...");

        $frozenCount = $service->freezeOverdueBudgets($year);

        if ($frozenCount > 0) {
            $this->warn("Sebanyak {$frozenCount} departemen anggaran telah dibekukan (FROZEN) untuk mencegah overspend.");
        } else {
            $this->info('Tidak ada anggaran yang melebihi pagu kritis.');
        }

        return self::SUCCESS;
    }
}
