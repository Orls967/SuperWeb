<?php

declare(strict_types=1);

namespace Modules\Treasury\Console\Commands;

use Illuminate\Console\Command;
use Modules\Treasury\Application\Actions\ReconcileBankStatementsAction;
use Modules\Treasury\Domain\Models\BankAccount;

class ReconcileBankStatementsCommand extends Command
{
    protected $signature = 'treasury:match-bank-statements {--account= : Nomor rekening bank}';

    protected $description = 'Jalankan algoritma rekonsiliasi bank 3-tahap deterministik (D2)';

    public function handle(ReconcileBankStatementsAction $action): int
    {
        $accountNo = $this->option('account');

        $query = BankAccount::where('is_active', true);
        if ($accountNo) {
            $query->where('account_number', $accountNo);
        }

        $accounts = $query->get();

        if ($accounts->isEmpty()) {
            $this->error('Rekening bank tidak ditemukan.');

            return self::FAILURE;
        }

        foreach ($accounts as $acc) {
            $this->info("Menjalankan rekonsiliasi untuk rekening {$acc->account_number} ({$acc->bank_name})...");
            $res = $action->execute($acc);

            $this->line(" Exact matches       : {$res['exact_matches']}");
            $this->line(" Tolerance matches   : {$res['tolerance_matches']}");
            $this->line(" Many-to-one matches : {$res['many_to_one_matches']}");
            $this->line(" Total matched       : {$res['matched_count']}");
            $this->line(" Unmatched           : {$res['unmatched_count']}");
            $this->line(" Cert Hash           : {$res['certificate_hash']}");
        }

        $this->info('Selesai menjalankan rekonsiliasi bank.');

        return self::SUCCESS;
    }
}
