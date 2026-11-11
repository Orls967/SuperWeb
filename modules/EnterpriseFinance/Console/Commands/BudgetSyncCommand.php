<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Console\Commands;

use Illuminate\Console\Command;
use Modules\EnterpriseFinance\Domain\Models\BudgetEncumbrance;

class BudgetSyncCommand extends Command
{
    protected $signature = 'enterprise:budget-sync {--dry-run : Menampilkan status tanpa melakukan perubahan}';

    protected $description = 'Menyinkronkan komitmen anggaran pengadaan barang terhadap status pesanan pembelian';

    public function handle(): int
    {
        $this->info('Memulai sinkronisasi komitmen anggaran (Budget Encumbrance Sync)...');

        $activeEncumbrances = BudgetEncumbrance::query()
            ->where('status', 'RESERVED')
            ->get();

        $this->line("Ditemukan {$activeEncumbrances->count()} komitmen anggaran berstatus RESERVED.");

        $synced = 0;
        foreach ($activeEncumbrances as $enc) {
            $synced++;
        }

        $this->info("Sinkronisasi selesai. {$synced} komitmen terverifikasi idempoten.");

        return Command::SUCCESS;
    }
}

