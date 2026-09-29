<?php

declare(strict_types=1);

namespace Modules\Finance\Console\Commands;

use Illuminate\Console\Command;
use Modules\Finance\Application\Actions\PayInstallmentAction;
use Modules\Finance\Domain\Enums\InstallmentStatus;
use Modules\Finance\Domain\Enums\LoanStatus;
use Modules\Finance\Domain\Models\Installment;

class ChargeDueInstallmentsCommand extends Command
{
    protected $signature = 'finance:charge-installments';

    protected $description = 'Debit cicilan pembiayaan yang jatuh tempo dari dompet peminjam';

    public function handle(PayInstallmentAction $payInstallment): int
    {
        $due = Installment::whereIn('status', [
            InstallmentStatus::Scheduled->value,
            InstallmentStatus::Overdue->value,
        ])
            ->whereDate('due_date', '<=', now()->toDateString())
            ->whereHas('loan', fn ($query) => $query->whereIn('status', [
                LoanStatus::Active->value,
                LoanStatus::MarginCall->value,
            ]))
            ->with('loan.user')
            ->orderBy('due_date')
            ->orderBy('sequence')
            ->get();

        $this->info("Menemukan {$due->count()} cicilan jatuh tempo.");

        $paid = 0;
        $failed = 0;

        foreach ($due as $installment) {
            try {
                $payInstallment->execute($installment);
                $paid++;
                $this->info("✓ Cicilan ke-{$installment->sequence} pinjaman #{$installment->loan_id} terdebit.");
            } catch (\Throwable $e) {
                $failed++;
                $this->warn("✗ Cicilan ke-{$installment->sequence} pinjaman #{$installment->loan_id} gagal: {$e->getMessage()}");
            }
        }

        $this->info("Selesai: {$paid} terbayar, {$failed} tertunda/terlambat.");

        return self::SUCCESS;
    }
}
