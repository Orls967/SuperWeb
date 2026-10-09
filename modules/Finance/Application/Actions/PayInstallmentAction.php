<?php

declare(strict_types=1);

namespace Modules\Finance\Application\Actions;

use Brick\Math\BigDecimal;
use Exception;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Banking\Domain\Enums\TransactionType;
use Modules\Banking\Domain\Exceptions\InsufficientFundsException;
use Modules\Finance\Domain\Enums\InstallmentStatus;
use Modules\Finance\Domain\Models\Installment;
use Modules\Finance\Domain\Models\Loan;
use Modules\Shared\Application\BaseAction;

/**
 * Debit satu cicilan dari dompet peminjam: pokok mengurangi piutang,
 * bunga dan denda masuk akun pendapatan pembiayaan.
 */
class PayInstallmentAction extends BaseAction
{
    public function __construct(
        private readonly Ledger $ledger,
        private readonly ReleaseCollateralAction $releaseCollateral,
    ) {}

    public function execute(Installment $installment, ?string $idempotencyKey = null): Installment
    {
        $installment->loadMissing('loan.user');
        $loan = $installment->loan;

        if ($loan === null) {
            throw new Exception('Cicilan tidak terhubung dengan pinjaman manapun.');
        }

        $insufficient = $this->charge($installment, $loan, $idempotencyKey);

        if ($insufficient !== null) {
            // Penanda Overdue/denda sudah disimpan di transaksi terpisah supaya
            // tidak ikut ter-rollback bersama galat saldo yang tidak cukup.
            throw $insufficient;
        }

        return $installment->fresh();
    }

    /**
     * Debit satu cicilan di bawah kunci baris.
     */
    private function charge(Installment $installment, Loan $loan, ?string $idempotencyKey): ?InsufficientFundsException
    {
        return $this->transaction(function () use ($installment, $loan, $idempotencyKey): ?InsufficientFundsException {
            // Kunci baris fin_installments dan fin_loans terlebih dahulu, lalu baca
            // ulang status, denda, dan sisa pokok di bawah kunci yang sama.
            $lockedInstallment = Installment::whereKey($installment->getKey())->lockForUpdate()->firstOrFail();
            $lockedLoan = Loan::whereKey($loan->getKey())->lockForUpdate()->firstOrFail();

            if ($lockedInstallment->status === InstallmentStatus::Paid) {
                throw new Exception("Cicilan ke-{$lockedInstallment->sequence} sudah terbayar.");
            }

            if (! $lockedLoan->status->isOpen()) {
                throw new Exception("Pinjaman berstatus {$lockedLoan->status->label()} tidak menerima pembayaran cicilan.");
            }

            $penalty = $lockedInstallment->calculatePenalty();
            $total = $lockedInstallment->amount + $penalty;
            $user = $lockedLoan->user;
            $wallet = $user->walletAccount('IDR');

            if ((int) $wallet->cached_balance < $total) {
                // Saldo tidak cukup: tandai terlambat agar denda terus berjalan
                if ($lockedInstallment->status === InstallmentStatus::Scheduled && $lockedInstallment->daysLate() > 0) {
                    $lockedInstallment->transitionTo(InstallmentStatus::Overdue);
                }

                $lockedInstallment->update(['penalty' => $penalty]);

                $installment->setRawAttributes($lockedInstallment->getAttributes(), true);
                $installment->syncOriginal();

                return new InsufficientFundsException(
                    $wallet->code,
                    (string) $wallet->cached_balance,
                    (string) $total
                );
            }

            $entries = [
                PostingEntryDTO::forAccount($wallet->id, 'IDR', BigDecimal::of((string) $total)->negated()),
                PostingEntryDTO::forCode('loan_receivable:IDR', 'IDR', (string) $lockedInstallment->principal_part),
            ];

            if ($lockedInstallment->interest_part > 0) {
                $entries[] = PostingEntryDTO::forCode('fin:interest:IDR', 'IDR', (string) $lockedInstallment->interest_part);
            }

            if ($penalty > 0) {
                $entries[] = PostingEntryDTO::forCode('fin:penalty:IDR', 'IDR', (string) $penalty);
            }

            $tx = $this->ledger->post(new PostingDTO(
                type: TransactionType::LOAN_REPAYMENT->value,
                description: "Cicilan ke-{$lockedInstallment->sequence} pembiayaan {$loan->uuid}",
                idempotencyKey: $idempotencyKey ?? ('loan_inst_'.$loan->uuid.'_'.$lockedInstallment->sequence),
                entries: $entries,
                referenceType: 'fin_loan',
                referenceId: $loan->id,
                meta: [
                    'loan_id' => $loan->id,
                    'installment_sequence' => $lockedInstallment->sequence,
                    'principal_part' => $lockedInstallment->principal_part,
                    'interest_part' => $lockedInstallment->interest_part,
                    'penalty' => $penalty,
                ],
                createdBy: $loan->user_id,
                postedAt: now(),
            ));

            // LedgerService mengembalikan transaksi lama bila kunci sama (replay),
            // jadi saldo pinjaman hanya dikurangi bila posting ini benar-benar baru.
            if ($tx->wasRecentlyCreated) {
                $lockedInstallment->status = InstallmentStatus::Paid;
                $lockedInstallment->penalty = $penalty;
                $lockedInstallment->paid_at = now();
                $lockedInstallment->ledger_transaction_id = $tx->id;
                $lockedInstallment->save();

                $lockedLoan->outstanding_principal = max(0, $lockedLoan->outstanding_principal - $lockedInstallment->principal_part);
                $lockedLoan->save();

                $installment->setRawAttributes($lockedInstallment->getAttributes(), true);
                $installment->syncOriginal();
                $loan->setRawAttributes($lockedLoan->getAttributes(), true);
                $loan->syncOriginal();
            }

            // Seluruh cicilan lunas → kolateral dikembalikan ke pemiliknya
            $remaining = $lockedLoan->installments()
                ->where('status', '!=', InstallmentStatus::Paid->value)
                ->count();

            if ($remaining === 0) {
                $this->releaseCollateral->execute($lockedLoan, 'Pelunasan seluruh cicilan');
            }

            return null;
        });
    }

    /**
     * Pelunasan awal: bayar seluruh cicilan yang belum terbayar sekaligus.
     */
    public function payOff(Loan $loan): Loan
    {
        if (! $loan->status->isOpen()) {
            throw new Exception("Pinjaman berstatus {$loan->status->label()} tidak dapat dilunasi.");
        }

        $unpaid = $loan->installments()
            ->where('status', '!=', InstallmentStatus::Paid->value)
            ->orderBy('sequence')
            ->get();

        if ($unpaid->isEmpty()) {
            throw new Exception('Tidak ada cicilan yang tersisa.');
        }

        return $this->transaction(function () use ($loan, $unpaid) {
            foreach ($unpaid as $installment) {
                $this->execute($installment);
            }

            return $loan->fresh(['installments', 'collateralAsset']);
        });
    }
}
