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
        if ($installment->status === InstallmentStatus::Paid) {
            throw new Exception("Cicilan ke-{$installment->sequence} sudah terbayar.");
        }

        $installment->loadMissing('loan.user');
        $loan = $installment->loan;

        if ($loan === null) {
            throw new Exception('Cicilan tidak terhubung dengan pinjaman manapun.');
        }

        if (! $loan->status->isOpen()) {
            throw new Exception("Pinjaman berstatus {$loan->status->label()} tidak menerima pembayaran cicilan.");
        }

        $penalty = $installment->calculatePenalty();
        $total = $installment->amount + $penalty;
        $user = $loan->user;
        $wallet = $user->walletAccount('IDR');

        if ((int) $wallet->cached_balance < $total) {
            // Saldo tidak cukup: tandai terlambat agar denda terus berjalan
            if ($installment->status === InstallmentStatus::Scheduled && $installment->daysLate() > 0) {
                $installment->transitionTo(InstallmentStatus::Overdue);
            }

            $installment->update(['penalty' => $penalty]);

            throw new InsufficientFundsException(
                $wallet->code,
                (string) $wallet->cached_balance,
                (string) $total
            );
        }

        return $this->transaction(function () use ($installment, $loan, $wallet, $penalty, $total, $idempotencyKey) {
            $entries = [
                PostingEntryDTO::forAccount($wallet->id, 'IDR', BigDecimal::of((string) $total)->negated()),
                PostingEntryDTO::forCode('loan_receivable:IDR', 'IDR', (string) $installment->principal_part),
            ];

            if ($installment->interest_part > 0) {
                $entries[] = PostingEntryDTO::forCode('fin:interest:IDR', 'IDR', (string) $installment->interest_part);
            }

            if ($penalty > 0) {
                $entries[] = PostingEntryDTO::forCode('fin:penalty:IDR', 'IDR', (string) $penalty);
            }

            $tx = $this->ledger->post(new PostingDTO(
                type: TransactionType::LOAN_REPAYMENT->value,
                description: "Cicilan ke-{$installment->sequence} pembiayaan {$loan->uuid}",
                idempotencyKey: $idempotencyKey ?? ('loan_inst_'.$loan->uuid.'_'.$installment->sequence),
                entries: $entries,
                referenceType: 'fin_loan',
                referenceId: $loan->id,
                meta: [
                    'loan_id' => $loan->id,
                    'installment_sequence' => $installment->sequence,
                    'principal_part' => $installment->principal_part,
                    'interest_part' => $installment->interest_part,
                    'penalty' => $penalty,
                ],
                createdBy: $loan->user_id,
                postedAt: now(),
            ));

            $installment->status = InstallmentStatus::Paid;
            $installment->penalty = $penalty;
            $installment->paid_at = now();
            $installment->ledger_transaction_id = $tx->id;
            $installment->save();

            $loan->outstanding_principal = max(0, $loan->outstanding_principal - $installment->principal_part);
            $loan->save();

            // Seluruh cicilan lunas → kolateral dikembalikan ke pemiliknya
            $remaining = $loan->installments()
                ->where('status', '!=', InstallmentStatus::Paid->value)
                ->count();

            if ($remaining === 0) {
                $this->releaseCollateral->execute($loan, 'Pelunasan seluruh cicilan');
            }

            return $installment->fresh();
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
