<?php

declare(strict_types=1);

namespace Modules\Treasury\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Core\Contracts\DocumentNumberingInterface;
use Modules\Treasury\Application\Services\TreasuryLedgerPoster;
use Modules\Treasury\Domain\Models\BankAccount;
use Modules\Treasury\Domain\Models\PettyCashVoucher;
use Psr\Clock\ClockInterface;

class ProcessPettyCashVoucherAction
{
    public function __construct(
        protected readonly ?DocumentNumberingInterface $documentNumbering = null,
        protected readonly ?TreasuryLedgerPoster $ledgerPoster = null,
        protected readonly ?ClockInterface $clock = null,
    ) {}

    protected function now(): CarbonImmutable
    {
        if ($this->clock !== null) {
            return CarbonImmutable::instance($this->clock->now());
        }

        return CarbonImmutable::now();
    }

    /**
     * Mengajukan voucher pengeluaran kas kecil (Imprest Petty Cash).
     */
    public function submitVoucher(
        BankAccount $bankAccount,
        int $amount,
        string $payee,
        string $expenseAccountCode,
        string $description,
        int $creatorId,
        ?string $customVoucherNumber = null
    ): PettyCashVoucher {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Nominal voucher kas kecil harus lebih besar dari 0.');
        }

        if (! $bankAccount->is_petty_cash) {
            throw new InvalidArgumentException("Rekening {$bankAccount->account_number} bukan rekening kas kecil.");
        }

        return DB::transaction(function () use ($bankAccount, $amount, $payee, $expenseAccountCode, $description, $creatorId, $customVoucherNumber) {
            $now = $this->now();

            $voucherNumber = $customVoucherNumber;
            if ($voucherNumber === null) {
                if ($this->documentNumbering !== null) {
                    $voucherNumber = $this->documentNumbering->nextNumber('HOLDING', 'PCV', true, 'PCV/{YYYY}/{MM}/', 4);
                } else {
                    $voucherNumber = 'PCV-'.date('Ymd').'-'.str_pad((string) (PettyCashVoucher::count() + 1), 4, '0', STR_PAD_LEFT);
                }
            }

            return PettyCashVoucher::create([
                'bank_account_id' => $bankAccount->id,
                'voucher_number' => $voucherNumber,
                'voucher_date' => $now->toDateString(),
                'amount' => $amount,
                'payee' => $payee,
                'expense_account_code' => $expenseAccountCode,
                'description' => $description,
                'status' => 'submitted',
                'created_by' => $creatorId,
            ]);
        });
    }

    /**
     * Menyetujui dan mencairkan voucher kas kecil.
     */
    public function approveAndReimburse(
        PettyCashVoucher $voucher,
        int $approverId,
        string $pettyCashAccountCode = 'trs:petty_cash:IDR'
    ): PettyCashVoucher {
        if ($voucher->status !== 'submitted') {
            throw new InvalidArgumentException("Voucher {$voucher->voucher_number} sudah diproses (status: {$voucher->status}).");
        }

        if ($voucher->created_by !== null && (int) $voucher->created_by === $approverId) {
            throw new InvalidArgumentException('Penyetuju voucher kas kecil tidak boleh sama dengan pembuat voucher (Four-Eyes principle).');
        }

        return DB::transaction(function () use ($voucher, $approverId, $pettyCashAccountCode) {
            $amount = (int) $voucher->amount;
            $bankAcc = $voucher->bankAccount;

            if ($bankAcc !== null) {
                if ($bankAcc->balance < $amount) {
                    throw new InvalidArgumentException("Saldo kas kecil ({$bankAcc->balance}) tidak mencukupi untuk pembayaran {$amount}.");
                }
                $bankAcc->decrement('balance', $amount);
            }

            $voucher->update([
                'status' => 'reimbursed',
                'approved_by' => $approverId,
            ]);

            // Posting ledger pengeluaran kas kecil
            if ($this->ledgerPoster !== null) {
                $idempotencyKey = "IDEM:TRS:PETTY_CASH:{$voucher->id}";
                $tx = $this->ledgerPoster->postPettyCashExpense(
                    voucherNumber: $voucher->voucher_number,
                    amountIdr: $amount,
                    expenseAccountCode: $voucher->expense_account_code ?: 'trs:petty_cash_expense:IDR',
                    pettyCashAccountCode: $pettyCashAccountCode,
                    idempotencyKey: $idempotencyKey,
                    voucherId: $voucher->id,
                    userId: $approverId
                );
                $voucher->update(['ledger_tx_id' => $tx->id]);
            }

            return $voucher->fresh();
        });
    }
}
