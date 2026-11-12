<?php

declare(strict_types=1);

namespace Modules\Treasury\Console\Commands;

use Illuminate\Console\Command;
use Modules\Treasury\Application\Actions\ReconcileBankStatementsAction;
use Modules\Treasury\Domain\Models\BankAccount;
use Modules\Treasury\Domain\Models\BankReconciliationUnmatched;
use Modules\Treasury\Domain\Models\BankStatement;

class ReconcileBankStatementsCommand extends Command
{
    protected $signature = 'treasury:reconcile {--account= : Nomor rekening bank} {--account-id= : UUID rekening bank}';

    protected $description = 'Jalankan algoritma rekonsiliasi bank 3-tahap deterministik (D2) harian secara idempoten';

    public function handle(ReconcileBankStatementsAction $action): int
    {
        // Pre-flight single-state integrity check (D48-12 Check 4)
        $reconciledIds = BankStatement::where('is_reconciled', true)->pluck('id')->all();
        if (! empty($reconciledIds)) {
            $conflictingPending = BankReconciliationUnmatched::whereIn('statement_id', $reconciledIds)
                ->where('status', 'pending')
                ->count();
            if ($conflictingPending > 0) {
                $this->error("Treasury reconcile FAILED: {$conflictingPending} reconciled statement(s) still have pending unmatched queue entries!");

                return self::FAILURE;
            }
        }

        $corruptStatusCount = BankStatement::where('is_reconciled', true)
            ->where('reconciliation_status', 'unmatched')
            ->count();
        if ($corruptStatusCount > 0) {
            $this->error("Treasury reconcile FAILED: {$corruptStatusCount} statement(s) have conflicting single-state (is_reconciled=true vs reconciliation_status=unmatched)!");

            return self::FAILURE;
        }

        $accountNo = $this->option('account');
        $accountId = $this->option('account-id');

        $query = BankAccount::where('is_active', true);
        if ($accountNo) {
            $query->where('account_number', $accountNo);
        }
        if ($accountId) {
            $query->where('id', $accountId);
        }

        $accounts = $query->get();

        if ($accounts->isEmpty()) {
            if (! $accountNo && ! $accountId) {
                $this->info('Tidak ada rekening bank aktif untuk direkonsiliasi.');

                return self::SUCCESS;
            }

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
