<?php

declare(strict_types=1);

namespace Modules\Intercompany\Application\Actions;

use InvalidArgumentException;
use Modules\Intercompany\Application\Services\IntercompanyLedgerPoster;
use Modules\Intercompany\Domain\Models\IntercompanyLoan;
use Modules\Intercompany\Domain\Models\IntercompanyLoanSchedule;
use Psr\Clock\ClockInterface;

class AccrueAndPayIntercompanyInterestAction
{
    public function __construct(
        protected readonly IntercompanyLedgerPoster $ledgerPoster,
        protected readonly ?ClockInterface $clock = null
    ) {}

    public function execute(
        IntercompanyLoan $loan,
        int $installmentNumber,
        ?int $userId = null
    ): IntercompanyLoanSchedule {
        $schedule = $loan->schedules()->where('installment_number', $installmentNumber)->first();
        if (! $schedule) {
            throw new InvalidArgumentException("Cicilan ke-{$installmentNumber} untuk pinjaman [{$loan->loan_agreement_number}] tidak ditemukan.");
        }

        if ($schedule->payment_status === 'paid') {
            return $schedule;
        }

        $now = $this->clock !== null ? $this->clock->now() : new \DateTimeImmutable;

        // Post ke ledger: beban bunga di debitur, pendapatan di kreditur, potongan PPh 23
        $ledgerTx = $this->ledgerPoster->postLoanInterestAccrual(
            lenderEntity: $loan->lender_entity,
            borrowerEntity: $loan->borrower_entity,
            interestGrossMinor: (int) $schedule->interest_due_minor,
            taxWithheldMinor: (int) $schedule->tax_withheld_minor,
            currency: $loan->currency,
            loanNumber: $loan->loan_agreement_number,
            installmentNumber: $installmentNumber,
            idempotencyKey: "IC:LOAN:ACCRUE:{$loan->loan_agreement_number}:{$installmentNumber}",
            userId: $userId
        );

        $schedule->payment_status = 'paid';
        $schedule->paid_at = $now;
        $schedule->ledger_transaction_id = $ledgerTx->id;
        $schedule->save();

        // Update repaid principal bila ada pokok terbayar
        if ($schedule->principal_due_minor > 0) {
            $loan->repaid_principal_idr += $schedule->principal_due_minor;
            $loan->repaid_principal_minor += $schedule->principal_due_minor;
            if ($loan->repaid_principal_idr >= $loan->principal_idr) {
                $loan->status = 'settled';
            }
            $loan->save();
        }

        return $schedule;
    }
}
