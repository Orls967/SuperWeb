<?php

declare(strict_types=1);

namespace Modules\Mall\Console\Commands;

use Illuminate\Console\Command;
use Modules\Mall\Application\Actions\MallAutoDebitAction;

class AutoDebitCommand extends Command
{
    protected $signature = 'mall:auto-debit {--month= : Periode bulan invoice yang ingin di-autodebit}';

    protected $description = 'Jalankan pemotongan otomatis tagihan mall dari saldo dompet tenant tanpa PIN';

    public function handle(MallAutoDebitAction $action): int
    {
        $month = $this->option('month');
        $this->info('Menjalankan auto-debit tagihan mall'.($month ? " periode [{$month}]" : '').'...');

        $result = $action->autoDebitAll($month);

        $this->info('Auto-debit selesai.');
        $this->line("- Invoice diproses: <comment>{$result['processed_invoices']}</comment>");
        $this->line('- Total terdebet: <comment>Rp '.number_format($result['total_debited'], 0, ',', '.').'</comment>');

        return self::SUCCESS;
    }
}
