<?php

declare(strict_types=1);

namespace Modules\Treasury\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Core\Contracts\DocumentNumberingInterface;
use Modules\Treasury\Application\Services\TreasuryEventPublisher;
use Modules\Treasury\Application\Services\TreasuryLedgerPoster;
use Modules\Treasury\Application\Services\TreasuryTaskInboxNotifier;
use Modules\Treasury\Domain\Events\PettyCashVoucherProcessedEvent;
use Modules\Treasury\Domain\Models\BankAccount;
use Modules\Treasury\Domain\Models\PettyCashReplenishment;
use Modules\Treasury\Domain\Models\PettyCashVoucher;
use Psr\Clock\ClockInterface;

class ProcessPettyCashVoucherAction
{
    public function __construct(
        protected readonly ?DocumentNumberingInterface $documentNumbering = null,
        protected readonly ?TreasuryLedgerPoster $ledgerPoster = null,
        protected readonly ?ClockInterface $clock = null,
        protected readonly ?TreasuryTaskInboxNotifier $taskNotifier = null,
        protected readonly ?TreasuryEventPublisher $eventPublisher = null,
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
                    $voucherNumber = 'PCV-'.$now->format('Ymd').'-'.str_pad((string) (PettyCashVoucher::count() + 1), 4, '0', STR_PAD_LEFT);
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

            if ($bankAcc !== null) {
                $freshAcc = $bankAcc->fresh() ?? $bankAcc;
                $notifier = $this->taskNotifier ?? (app()->bound(TreasuryTaskInboxNotifier::class) ? app(TreasuryTaskInboxNotifier::class) : null);
                if ($notifier !== null) {
                    $notifier->notifyLowPettyCashBalance($freshAcc);
                }
            }

            $publisher = $this->eventPublisher ?? (app()->bound(TreasuryEventPublisher::class) ? app(TreasuryEventPublisher::class) : null);
            if ($publisher !== null) {
                $publisher->publishPettyCashVoucher(new PettyCashVoucherProcessedEvent(
                    voucherId: (string) $voucher->id,
                    voucherNumber: (string) $voucher->voucher_number,
                    actionType: 'reimbursed',
                    amountMinor: $amount,
                    currency: (string) ($bankAcc?->currency ?? 'IDR'),
                    remainingBalanceMinor: (int) ($bankAcc?->fresh()?->balance ?? 0),
                    expenseCategory: (string) $voucher->expense_account_code,
                    actorId: $approverId
                ));
            }

            return $voucher->fresh();
        });
    }

    /**
     * Pengisian kembali (replenishment) dana kas kecil sistem imprest (D48-5),
     * termasuk pencatatan selisih opname fisik kas (cash count shortage / overage).
     */
    public function replenishImprestFund(
        BankAccount $pettyCashAccount,
        int $physicalCashCountedIdr,
        int $approverId,
        ?int $creatorId = null,
        string $sourceBankAccountCode = 'trs:bank_operational:HOLDING:IDR',
        string $pettyCashLedgerCode = 'trs:petty_cash:IDR'
    ): PettyCashReplenishment {
        if (! $pettyCashAccount->is_petty_cash) {
            throw new InvalidArgumentException("Rekening {$pettyCashAccount->account_number} bukan akun kas kecil imprest.");
        }

        if ($physicalCashCountedIdr < 0) {
            throw new InvalidArgumentException('Hasil opname fisik kas kecil tidak boleh bernilai negatif.');
        }

        if ($creatorId !== null && $creatorId === $approverId) {
            throw new InvalidArgumentException('Penyetuju pengisian kembali kas kecil tidak boleh sama dengan pengaju (Four-Eyes principle).');
        }

        $imprestLimit = (int) $pettyCashAccount->imprest_limit;
        if ($imprestLimit <= 0) {
            throw new InvalidArgumentException("Rekening kas kecil {$pettyCashAccount->account_number} belum memiliki batas pagu imprest_limit (> 0).");
        }

        return DB::transaction(function () use (
            $pettyCashAccount,
            $physicalCashCountedIdr,
            $imprestLimit,
            $approverId,
            $creatorId,
            $sourceBankAccountCode,
            $pettyCashLedgerCode
        ) {
            $now = $this->now();
            $lockedAccount = BankAccount::lockForUpdate()->findOrFail($pettyCashAccount->id);

            $bookBalanceBefore = (int) $lockedAccount->balance;
            $shortageOverage = $physicalCashCountedIdr - $bookBalanceBefore;
            $replenishedAmount = max(0, $imprestLimit - $physicalCashCountedIdr);

            if ($replenishedAmount <= 0) {
                throw new InvalidArgumentException('Saldo fisik kas kecil sudah mencapai atau melampaui batas pagu imprest.');
            }

            $repNumber = 'PCR-'.$now->format('Ymd').'-'.str_pad((string) (PettyCashReplenishment::count() + 1), 4, '0', STR_PAD_LEFT);

            // Jika terdapat selisih kas fisik vs buku, posting ke akun selisih rekonsiliasi/kas
            if ($this->ledgerPoster !== null && $shortageOverage !== 0) {
                $diffIdemKey = "IDEM:TRS:PETTY_DIFF:{$repNumber}";
                $this->ledgerPoster->postReconciliationDifference(
                    bankAccountCode: $pettyCashLedgerCode,
                    diffAmountIdr: $shortageOverage,
                    reason: "Selisih opname fisik kas kecil {$repNumber}",
                    idempotencyKey: $diffIdemKey,
                    statementId: $lockedAccount->id,
                    userId: $approverId
                );
            }

            // Posting replenishment ke buku besar
            $ledgerTxId = null;
            if ($this->ledgerPoster !== null) {
                $repIdemKey = "IDEM:TRS:PETTY_REPLENISH:{$repNumber}";
                $tx = $this->ledgerPoster->postPettyCashReplenishment(
                    replenishmentRef: $repNumber,
                    amountIdr: $replenishedAmount,
                    pettyCashAccountCode: $pettyCashLedgerCode,
                    sourceBankAccountCode: $sourceBankAccountCode,
                    idempotencyKey: $repIdemKey,
                    bankAccountId: $lockedAccount->id,
                    userId: $approverId
                );
                $ledgerTxId = (int) $tx->id;
            }

            $lockedAccount->update([
                'balance' => $imprestLimit,
            ]);

            return PettyCashReplenishment::create([
                'bank_account_id' => $lockedAccount->id,
                'replenishment_number' => $repNumber,
                'replenishment_date' => $now->toDateString(),
                'imprest_limit_idr' => $imprestLimit,
                'book_balance_before_idr' => $bookBalanceBefore,
                'physical_cash_counted_idr' => $physicalCashCountedIdr,
                'shortage_overage_idr' => $shortageOverage,
                'replenished_amount_idr' => $replenishedAmount,
                'balance_after_idr' => $imprestLimit,
                'source_bank_account_code' => $sourceBankAccountCode,
                'ledger_tx_id' => $ledgerTxId,
                'status' => 'completed',
                'created_by' => $creatorId,
                'approved_by' => $approverId,
            ]);
        });
    }
}
