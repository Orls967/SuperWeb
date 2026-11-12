<?php

declare(strict_types=1);

namespace Modules\Treasury\Application\Services;

use Carbon\CarbonImmutable;
use Database\Seeders\LedgerAccountsSeeder;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Contracts\LedgerTransaction;
use Modules\Banking\Support\Posting;
use Modules\Treasury\Domain\Exceptions\TreasuryException;
use Modules\Treasury\Ledger\TreasuryTransactionTypes;
use Psr\Clock\ClockInterface;

/**
 * Service pemostingan jurnal buku besar resmi (LedgerService) untuk modul Treasury.
 * Mematuhi Single-Sign Convention (Debit -, Credit +, Netto = 0) dan Idempotensi penuh.
 */
class TreasuryLedgerPoster
{
    public function __construct(
        protected readonly LedgerService $ledgerService,
        protected readonly ?ClockInterface $clock = null,
    ) {}

    protected function now(): CarbonImmutable
    {
        if ($this->clock !== null) {
            return CarbonImmutable::instance($this->clock->now());
        }

        return CarbonImmutable::now();
    }

    protected function ensureAccountsSeeded(): void
    {
        if ($this->ledgerService->findAccountByCode('trs:fx_reval_asset_adj:IDR') === null) {
            (new LedgerAccountsSeeder)->run();
        }
    }

    /**
     * Memosting jurnal penyesuaian revaluasi kurs akhir periode (PSAK 10).
     */
    public function postFxRevaluation(
        string $period,
        string $currency,
        int $gainLossIdr,
        string $idempotencyKey,
        ?string $revaluationId = null,
        ?int $userId = null
    ): LedgerTransaction {
        $this->ensureAccountsSeeded();

        if ($gainLossIdr === 0) {
            throw new TreasuryException('Tidak ada selisih kurs yang perlu diposting.');
        }

        $lines = Posting::lines();

        if ($gainLossIdr > 0) {
            // Keuntungan selisih kurs: Debit Penyesuaian Aset Valas, Kredit Keuntungan Selisih Kurs
            $lines->debit('trs:fx_reval_asset_adj:IDR', $gainLossIdr, 'IDR')
                ->credit('trs:fx_unrealized_gain_loss:IDR', $gainLossIdr, 'IDR');
        } else {
            // Kerugian selisih kurs: Debit Kerugian Selisih Kurs, Kredit Penyesuaian Aset Valas
            $lossAmount = abs($gainLossIdr);
            $lines->debit('trs:fx_unrealized_gain_loss:IDR', $lossAmount, 'IDR')
                ->credit('trs:fx_reval_asset_adj:IDR', $lossAmount, 'IDR');
        }

        $dto = new PostingDTO(
            type: TreasuryTransactionTypes::TRS_FX_REVAL->value,
            description: "Revaluasi kurs periode {$period} valas {$currency}",
            idempotencyKey: $idempotencyKey,
            entries: $lines->toEntries('IDR'),
            referenceType: 'trs_revaluations',
            referenceId: $revaluationId,
            meta: [
                'period' => $period,
                'currency' => $currency,
                'gain_loss_idr' => $gainLossIdr,
            ],
            createdBy: $userId,
            postedAt: $this->now(),
        );

        return $this->ledgerService->post($dto);
    }

    /**
     * Memosting jurnal pembalik revaluasi otomatis pada awal periode berikutnya.
     */
    public function postFxReversal(
        string $period,
        string $currency,
        int $gainLossIdr,
        string $idempotencyKey,
        ?string $reversalId = null,
        ?int $userId = null
    ): LedgerTransaction {
        $this->ensureAccountsSeeded();

        if ($gainLossIdr === 0) {
            throw new TreasuryException('Tidak ada selisih kurs pembalik yang perlu diposting.');
        }

        $lines = Posting::lines();

        if ($gainLossIdr > 0) {
            // Pembalik keuntungan: Debit Keuntungan, Kredit Penyesuaian Aset Valas
            $lines->debit('trs:fx_unrealized_gain_loss:IDR', $gainLossIdr, 'IDR')
                ->credit('trs:fx_reval_asset_adj:IDR', $gainLossIdr, 'IDR');
        } else {
            // Pembalik kerugian: Debit Penyesuaian Aset Valas, Kredit Kerugian
            $lossAmount = abs($gainLossIdr);
            $lines->debit('trs:fx_reval_asset_adj:IDR', $lossAmount, 'IDR')
                ->credit('trs:fx_unrealized_gain_loss:IDR', $lossAmount, 'IDR');
        }

        $dto = new PostingDTO(
            type: TreasuryTransactionTypes::TRS_FX_REVAL_REV->value,
            description: "Pembalik revaluasi periode {$period} valas {$currency}",
            idempotencyKey: $idempotencyKey,
            entries: $lines->toEntries('IDR'),
            referenceType: 'trs_revaluations',
            referenceId: $reversalId,
            meta: [
                'period' => $period,
                'currency' => $currency,
                'gain_loss_idr' => $gainLossIdr,
            ],
            createdBy: $userId,
            postedAt: $this->now(),
        );

        return $this->ledgerService->post($dto);
    }

    /**
     * Memosting selisih rekonsiliasi bank yang disetujui resmi.
     */
    public function postReconciliationDifference(
        string $bankAccountCode,
        int $diffAmountIdr,
        string $reason,
        string $idempotencyKey,
        ?string $statementId = null,
        ?int $userId = null
    ): LedgerTransaction {
        $this->ensureAccountsSeeded();

        if ($diffAmountIdr === 0) {
            throw new TreasuryException('Tidak ada selisih rekonsiliasi yang perlu diposting.');
        }

        $lines = Posting::lines();

        if ($diffAmountIdr > 0) {
            // Saldo bank lebih besar dari buku: Debit Bank, Kredit Selisih Rekonsiliasi (pendapatan lain-lain)
            $lines->debit($bankAccountCode, $diffAmountIdr, 'IDR')
                ->credit('trs:bank_reconciliation_diff:IDR', $diffAmountIdr, 'IDR');
        } else {
            // Saldo bank lebih kecil dari buku: Debit Selisih Rekonsiliasi (beban), Kredit Bank
            $absDiff = abs($diffAmountIdr);
            $lines->debit('trs:bank_reconciliation_diff:IDR', $absDiff, 'IDR')
                ->credit($bankAccountCode, $absDiff, 'IDR');
        }

        $dto = new PostingDTO(
            type: TreasuryTransactionTypes::TRS_BANK_RECON_DIFF->value,
            description: "Selisih rekonsiliasi bank disetujui: {$reason}",
            idempotencyKey: $idempotencyKey,
            entries: $lines->toEntries('IDR'),
            referenceType: 'trs_bank_statements',
            referenceId: $statementId,
            meta: [
                'bank_account' => $bankAccountCode,
                'difference' => $diffAmountIdr,
                'reason' => $reason,
            ],
            createdBy: $userId,
            postedAt: $this->now(),
        );

        return $this->ledgerService->post($dto);
    }

    /**
     * Memosting penyelesaian kontrak lindung nilai forward (Forward Contract Settle).
     */
    public function postForwardSettlement(
        string $contractNumber,
        int $netSettlementIdr,
        string $bankAccountCode,
        string $idempotencyKey,
        ?string $contractId = null,
        ?int $userId = null
    ): LedgerTransaction {
        $this->ensureAccountsSeeded();

        $lines = Posting::lines();

        if ($netSettlementIdr >= 0) {
            // Keuntungan penyelesaian: Debit Bank Operational, Kredit Untung Derivatif
            $lines->debit($bankAccountCode, $netSettlementIdr, 'IDR')
                ->credit('trs:derivative_gain_loss:IDR', $netSettlementIdr, 'IDR');
        } else {
            // Kerugian penyelesaian: Debit Rugi Derivatif, Kredit Bank Operational
            $lossAmount = abs($netSettlementIdr);
            $lines->debit('trs:derivative_gain_loss:IDR', $lossAmount, 'IDR')
                ->credit($bankAccountCode, $lossAmount, 'IDR');
        }

        $dto = new PostingDTO(
            type: TreasuryTransactionTypes::TRS_FORWARD_SETTLE->value,
            description: "Penyelesaian kontrak forward {$contractNumber}",
            idempotencyKey: $idempotencyKey,
            entries: $lines->toEntries('IDR'),
            referenceType: 'trs_forward_contracts',
            referenceId: $contractId,
            meta: [
                'contract_number' => $contractNumber,
                'net_settlement_idr' => $netSettlementIdr,
            ],
            createdBy: $userId,
            postedAt: $this->now(),
        );

        return $this->ledgerService->post($dto);
    }

    /**
     * Memosting biaya penalti pembatalan forward contract.
     */
    public function postForwardCancellationFee(
        string $contractNumber,
        int $feeAmountIdr,
        string $bankAccountCode,
        string $idempotencyKey,
        ?string $contractId = null,
        ?int $userId = null
    ): LedgerTransaction {
        $this->ensureAccountsSeeded();

        if ($feeAmountIdr <= 0) {
            throw new TreasuryException('Biaya pembatalan forward harus lebih besar dari 0.');
        }

        $lines = Posting::lines()
            ->debit('trs:hedge_cancellation_cost:IDR', $feeAmountIdr, 'IDR')
            ->credit($bankAccountCode, $feeAmountIdr, 'IDR');

        $dto = new PostingDTO(
            type: TreasuryTransactionTypes::TRS_FORWARD_CANCEL->value,
            description: "Biaya pembatalan kontrak forward {$contractNumber}",
            idempotencyKey: $idempotencyKey,
            entries: $lines->toEntries('IDR'),
            referenceType: 'trs_forward_contracts',
            referenceId: $contractId,
            meta: [
                'contract_number' => $contractNumber,
                'cancellation_fee' => $feeAmountIdr,
            ],
            createdBy: $userId,
            postedAt: $this->now(),
        );

        return $this->ledgerService->post($dto);
    }

    /**
     * Memosting penarikan fasilitas kredit bank (Drawdown).
     */
    public function postFacilityDrawdown(
        string $facilityCode,
        int $amountIdr,
        string $destinationAccountCode,
        string $idempotencyKey,
        ?string $facilityId = null,
        ?int $userId = null
    ): LedgerTransaction {
        $this->ensureAccountsSeeded();

        if ($amountIdr <= 0) {
            throw new TreasuryException('Nominal penarikan fasilitas kredit harus lebih besar dari 0.');
        }

        $lines = Posting::lines()
            ->debit($destinationAccountCode, $amountIdr, 'IDR')
            ->credit('trs:facility_payable:IDR', $amountIdr, 'IDR');

        $dto = new PostingDTO(
            type: TreasuryTransactionTypes::TRS_FACILITY_DRAW->value,
            description: "Penarikan fasilitas kredit {$facilityCode}",
            idempotencyKey: $idempotencyKey,
            entries: $lines->toEntries('IDR'),
            referenceType: 'trs_credit_facilities',
            referenceId: $facilityId,
            meta: [
                'facility_code' => $facilityCode,
                'amount_idr' => $amountIdr,
            ],
            createdBy: $userId,
            postedAt: $this->now(),
        );

        return $this->ledgerService->post($dto);
    }

    /**
     * Memosting penyapuan cash pool (Zero-balancing sweep).
     */
    public function postCashPoolSweep(
        string $poolName,
        int $amountIdr,
        string $headerAccountCode,
        string $subAccountCode,
        string $clearingAccountCode,
        string $direction,
        string $idempotencyKey,
        ?string $poolId = null,
        ?int $userId = null
    ): LedgerTransaction {
        $this->ensureAccountsSeeded();

        if ($amountIdr <= 0) {
            throw new TreasuryException('Nominal sweep kas harus lebih besar dari 0.');
        }

        $lines = Posting::lines();

        if ($direction === 'sub_to_header') {
            // Anak menyetor surplus ke Holding:
            // 1. Debit Kliring Anak, Kredit Kas Sub Account
            // 2. Debit Kas Header Account, Kredit Kliring Anak
            $lines->debit($clearingAccountCode, $amountIdr, 'IDR')
                ->credit($subAccountCode, $amountIdr, 'IDR')
                ->debit($headerAccountCode, $amountIdr, 'IDR')
                ->credit($clearingAccountCode, $amountIdr, 'IDR');
        } else {
            // Holding menutupi defisit Anak:
            // 1. Debit Kas Sub Account, Kredit Kliring Anak
            // 2. Debit Kliring Anak, Kredit Kas Header Account
            $lines->debit($subAccountCode, $amountIdr, 'IDR')
                ->credit($clearingAccountCode, $amountIdr, 'IDR')
                ->debit($clearingAccountCode, $amountIdr, 'IDR')
                ->credit($headerAccountCode, $amountIdr, 'IDR');
        }

        $dto = new PostingDTO(
            type: TreasuryTransactionTypes::TRS_POOL_SWEEP->value,
            description: "Penyapuan pooling kas {$poolName} ({$direction})",
            idempotencyKey: $idempotencyKey,
            entries: $lines->toEntries('IDR'),
            referenceType: 'trs_cash_pools',
            referenceId: $poolId,
            meta: [
                'pool_name' => $poolName,
                'direction' => $direction,
                'amount_idr' => $amountIdr,
            ],
            createdBy: $userId,
            postedAt: $this->now(),
        );

        return $this->ledgerService->post($dto);
    }

    /**
     * Memosting pengeluaran kas kecil (Petty Cash Voucher).
     */
    public function postPettyCashExpense(
        string $voucherNumber,
        int $amountIdr,
        string $expenseAccountCode,
        string $pettyCashAccountCode,
        string $idempotencyKey,
        ?string $voucherId = null,
        ?int $userId = null
    ): LedgerTransaction {
        $this->ensureAccountsSeeded();

        if ($amountIdr <= 0) {
            throw new TreasuryException('Nominal voucher kas kecil harus lebih besar dari 0.');
        }

        $lines = Posting::lines()
            ->debit($expenseAccountCode, $amountIdr, 'IDR')
            ->credit($pettyCashAccountCode, $amountIdr, 'IDR');

        $dto = new PostingDTO(
            type: TreasuryTransactionTypes::TRS_PETTY_CASH_EXPENSE->value,
            description: "Pengeluaran kas kecil voucher {$voucherNumber}",
            idempotencyKey: $idempotencyKey,
            entries: $lines->toEntries('IDR'),
            referenceType: 'trs_petty_cash_vouchers',
            referenceId: $voucherId,
            meta: [
                'voucher_number' => $voucherNumber,
                'amount_idr' => $amountIdr,
            ],
            createdBy: $userId,
            postedAt: $this->now(),
        );

        return $this->ledgerService->post($dto);
    }

    /**
     * Memosting revaluasi liabilitas valas akhir periode (PSAK 10 — D48-10 #2).
     * Saat kurs naik (gainLossIdr < 0 bagi liabilitas): Debit Rugi Selisih Kurs Belum Terealisasi, Kredit Penyesuaian Liabilitas Valas.
     * Saat kurs turun (gainLossIdr > 0 bagi liabilitas): Debit Penyesuaian Liabilitas Valas, Kredit Untung Selisih Kurs Belum Terealisasi.
     */
    public function postFxLiabilityRevaluation(
        string $period,
        string $currency,
        int $gainLossIdr,
        string $idempotencyKey,
        ?string $revaluationId = null,
        ?int $userId = null
    ): LedgerTransaction {
        $this->ensureAccountsSeeded();

        if ($gainLossIdr === 0) {
            throw new TreasuryException('Tidak ada selisih kurs liabilitas yang perlu diposting.');
        }

        $lines = Posting::lines();

        if ($gainLossIdr > 0) {
            // Keuntungan revaluasi liabilitas (kurs turun): Debit Penyesuaian Liabilitas Valas, Kredit Untung Belum Terealisasi
            $lines->debit('trs:fx_reval_liability_adj:IDR', $gainLossIdr, 'IDR')
                ->credit('trs:fx_unrealized_gain_loss:IDR', $gainLossIdr, 'IDR');
        } else {
            // Kerugian revaluasi liabilitas (kurs naik): Debit Rugi Belum Terealisasi, Kredit Penyesuaian Liabilitas Valas
            $lossAmount = abs($gainLossIdr);
            $lines->debit('trs:fx_unrealized_gain_loss:IDR', $lossAmount, 'IDR')
                ->credit('trs:fx_reval_liability_adj:IDR', $lossAmount, 'IDR');
        }

        $dto = new PostingDTO(
            type: TreasuryTransactionTypes::TRS_FX_REVAL->value,
            description: "Revaluasi kurs liabilitas periode {$period} valas {$currency}",
            idempotencyKey: $idempotencyKey,
            entries: $lines->toEntries('IDR'),
            referenceType: 'trs_revaluations',
            referenceId: $revaluationId,
            meta: [
                'period' => $period,
                'currency' => $currency,
                'gain_loss_idr' => $gainLossIdr,
                'item_side' => 'liability',
            ],
            createdBy: $userId,
            postedAt: $this->now(),
        );

        return $this->ledgerService->post($dto);
    }

    /**
     * Memosting penyelesaian lot valas terealisasi (Realized FX Gain/Loss — D48-10 #4 & #5).
     */
    public function postFxRealizedSettlement(
        string $lotId,
        string $currency,
        int $realizedGainLossIdr,
        string $idempotencyKey,
        ?int $userId = null
    ): LedgerTransaction {
        $this->ensureAccountsSeeded();

        if ($realizedGainLossIdr === 0) {
            throw new TreasuryException('Tidak ada selisih kurs terealisasi pada penyelesaian lot.');
        }

        $lines = Posting::lines();

        if ($realizedGainLossIdr > 0) {
            $lines->debit('trs:fx_settlement_clearing:IDR', $realizedGainLossIdr, 'IDR')
                ->credit('trs:fx_realized_gain_loss:IDR', $realizedGainLossIdr, 'IDR');
        } else {
            $lossAmount = abs($realizedGainLossIdr);
            $lines->debit('trs:fx_realized_gain_loss:IDR', $lossAmount, 'IDR')
                ->credit('trs:fx_settlement_clearing:IDR', $lossAmount, 'IDR');
        }

        $dto = new PostingDTO(
            type: TreasuryTransactionTypes::TRS_FX_SETTLE_REALIZED->value,
            description: "Realisasi selisih kurs settlement lot {$lotId} ({$currency})",
            idempotencyKey: $idempotencyKey,
            entries: $lines->toEntries('IDR'),
            referenceType: 'trs_fx_lots',
            referenceId: $lotId,
            meta: [
                'lot_id' => $lotId,
                'currency' => $currency,
                'realized_gain_loss_idr' => $realizedGainLossIdr,
            ],
            createdBy: $userId,
            postedAt: $this->now(),
        );

        return $this->ledgerService->post($dto);
    }

    /**
     * Memosting penyesuaian nilai wajar harian (Mark-to-Market) kontrak forward (D48-10 #6 & #7).
     */
    public function postForwardMarkToMarket(
        string $contractNumber,
        int $mtmDeltaIdr,
        string $idempotencyKey,
        ?string $contractId = null,
        ?int $userId = null
    ): LedgerTransaction {
        $this->ensureAccountsSeeded();

        if ($mtmDeltaIdr === 0) {
            throw new TreasuryException('Tidak ada perubahan nilai wajar MTM untuk diposting.');
        }

        $lines = Posting::lines();

        if ($mtmDeltaIdr > 0) {
            // MTM kontrak lindung nilai naik: Debit Aset Derivatif, Kredit Untung/Rugi Derivatif
            $lines->debit('trs:derivative_asset:IDR', $mtmDeltaIdr, 'IDR')
                ->credit('trs:derivative_gain_loss:IDR', $mtmDeltaIdr, 'IDR');
        } else {
            // MTM kontrak lindung nilai turun: Debit Untung/Rugi Derivatif, Kredit Liabilitas Derivatif
            $absDelta = abs($mtmDeltaIdr);
            $lines->debit('trs:derivative_gain_loss:IDR', $absDelta, 'IDR')
                ->credit('trs:derivative_liability:IDR', $absDelta, 'IDR');
        }

        $dto = new PostingDTO(
            type: TreasuryTransactionTypes::TRS_FORWARD_MTM->value,
            description: "Penyesuaian nilai wajar MTM kontrak forward {$contractNumber}",
            idempotencyKey: $idempotencyKey,
            entries: $lines->toEntries('IDR'),
            referenceType: 'trs_forward_contracts',
            referenceId: $contractId,
            meta: [
                'contract_number' => $contractNumber,
                'mtm_delta_idr' => $mtmDeltaIdr,
            ],
            createdBy: $userId,
            postedAt: $this->now(),
        );

        return $this->ledgerService->post($dto);
    }

    /**
     * Memosting akrual bunga fasilitas kredit bank harian/bulanan (D48-10 #13).
     * Debit trs:interest_expense:IDR / Kredit trs:interest_payable:IDR.
     */
    public function postFacilityInterestAccrual(
        string $facilityCode,
        int $interestAmountIdr,
        string $periodOrDate,
        string $idempotencyKey,
        ?string $facilityId = null,
        ?int $userId = null
    ): LedgerTransaction {
        $this->ensureAccountsSeeded();

        if ($interestAmountIdr <= 0) {
            throw new TreasuryException('Nominal akrual bunga fasilitas kredit harus lebih besar dari 0.');
        }

        $lines = Posting::lines()
            ->debit('trs:interest_expense:IDR', $interestAmountIdr, 'IDR')
            ->credit('trs:interest_payable:IDR', $interestAmountIdr, 'IDR');

        $dto = new PostingDTO(
            type: TreasuryTransactionTypes::TRS_FACILITY_INTEREST->value,
            description: "Akrual bunga fasilitas kredit {$facilityCode} ({$periodOrDate})",
            idempotencyKey: $idempotencyKey,
            entries: $lines->toEntries('IDR'),
            referenceType: 'trs_credit_facilities',
            referenceId: $facilityId,
            meta: [
                'facility_code' => $facilityCode,
                'interest_amount_idr' => $interestAmountIdr,
                'period' => $periodOrDate,
            ],
            createdBy: $userId,
            postedAt: $this->now(),
        );

        return $this->ledgerService->post($dto);
    }

    /**
     * Memosting pengisian kembali (replenishment) kas kecil sistem imprest
     * dari rekening bank operasional ke akun kas kecil.
     */
    public function postPettyCashReplenishment(
        string $replenishmentRef,
        int $amountIdr,
        string $pettyCashAccountCode,
        string $sourceBankAccountCode,
        string $idempotencyKey,
        ?string $bankAccountId = null,
        ?int $userId = null
    ): LedgerTransaction {
        $this->ensureAccountsSeeded();

        if ($amountIdr <= 0) {
            throw new TreasuryException('Nominal pengisian kembali kas kecil harus lebih besar dari 0.');
        }

        $lines = Posting::lines()
            ->debit($pettyCashAccountCode, $amountIdr, 'IDR')
            ->credit($sourceBankAccountCode, $amountIdr, 'IDR');

        $dto = new PostingDTO(
            type: TreasuryTransactionTypes::TRS_PETTY_CASH_REPLENISH->value,
            description: "Pengisian kembali kas kecil imprest {$replenishmentRef}",
            idempotencyKey: $idempotencyKey,
            entries: $lines->toEntries('IDR'),
            referenceType: 'trs_bank_accounts',
            referenceId: $bankAccountId,
            meta: [
                'replenishment_ref' => $replenishmentRef,
                'amount_idr' => $amountIdr,
                'petty_cash_account' => $pettyCashAccountCode,
                'source_bank_account' => $sourceBankAccountCode,
            ],
            createdBy: $userId,
            postedAt: $this->now(),
        );

        return $this->ledgerService->post($dto);
    }
}
