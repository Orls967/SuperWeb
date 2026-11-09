<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Services;

use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;
use Modules\Hospital\Ledger\HospitalTransactionTypes;

/**
 * Layanan Penjurnalan Buku Besar Akuntansi Ganda Modul Hospital (Fase 87 & 88).
 * Menjamin prinsip saldo normal dan melarang posting pendapatan negatif.
 */
class HospitalLedgerPostingService
{
    public function __construct(
        protected readonly LedgerService $ledgerService
    ) {}

    /**
     * Posting deposit rawat inap pasien ke rekening escrow.
     * Debit: hsp:cash:IDR (clearing pembayaran)
     * Kredit: hsp:patient_deposit_escrow:IDR
     */
    public function postInpatientDeposit(int $admissionId, int $amountIdr, string $idempotencyKey): void
    {
        if ($amountIdr <= 0) {
            return;
        }

        $lines = Posting::lines()
            ->debit('hsp:cash:IDR', $amountIdr)
            ->credit('hsp:patient_deposit_escrow:IDR', $amountIdr);

        $this->ledgerService->post(new PostingDTO(
            type: HospitalTransactionTypes::HOSP_SURGICAL_ESCROW_DEPOSIT->value,
            description: "Deposit rawat inap admisi #{$admissionId}",
            idempotencyKey: $idempotencyKey,
            entries: $lines->toEntries('IDR'),
            referenceType: 'HOSP_ADMISSION',
            referenceId: (string) $admissionId,
        ));
    }

    /**
     * Pengakuan pendapatan bed-day harian.
     * Debit: hsp:patient_receivable:IDR
     * Kredit: hsp:hospital_revenue:IDR
     */
    public function postDailyBedDayAccrual(int $admissionId, int $rateIdr, string $idempotencyKey): void
    {
        if ($rateIdr <= 0) {
            return;
        }

        $lines = Posting::lines()
            ->debit('hsp:patient_receivable:IDR', $rateIdr)
            ->credit('hsp:hospital_revenue:IDR', $rateIdr);

        $this->ledgerService->post(new PostingDTO(
            type: HospitalTransactionTypes::HOSP_EPISODE_SETTLEMENT->value,
            description: "Pengakuan bed-day harian admisi #{$admissionId}",
            idempotencyKey: $idempotencyKey,
            entries: $lines->toEntries('IDR'),
            referenceType: 'HOSP_ADMISSION',
            referenceId: (string) $admissionId,
        ));
    }

    /**
     * Koreksi pembalik bed-day (misal salah hitung atau pembatalan hari rawat).
     * Debit: hsp:hospital_revenue:IDR (pembalik)
     * Kredit: hsp:patient_receivable:IDR
     * DILARANG posting nilai negatif; koreksi dicatat sebagai entri pembalik.
     */
    public function postBedDayReversal(int $admissionId, int $amountIdr, string $idempotencyKey, string $reason): void
    {
        if ($amountIdr <= 0) {
            return;
        }

        $lines = Posting::lines()
            ->debit('hsp:hospital_revenue:IDR', $amountIdr)
            ->credit('hsp:patient_receivable:IDR', $amountIdr);

        $this->ledgerService->post(new PostingDTO(
            type: HospitalTransactionTypes::HOSP_EPISODE_SETTLEMENT->value,
            description: "Pembalik koreksi bed-day admisi #{$admissionId}: {$reason}",
            idempotencyKey: $idempotencyKey,
            entries: $lines->toEntries('IDR'),
            referenceType: 'HOSP_ADMISSION',
            referenceId: (string) $admissionId,
        ));
    }

    /**
     * Penangkapan deposit escrow saat pelunasan discharge pasien.
     * Debit: hsp:patient_deposit_escrow:IDR
     * Kredit: hsp:patient_receivable:IDR
     */
    public function postDepositSettlementCapture(int $admissionId, int $capturedAmountIdr, string $idempotencyKey): void
    {
        if ($capturedAmountIdr <= 0) {
            return;
        }

        $lines = Posting::lines()
            ->debit('hsp:patient_deposit_escrow:IDR', $capturedAmountIdr)
            ->credit('hsp:patient_receivable:IDR', $capturedAmountIdr);

        $this->ledgerService->post(new PostingDTO(
            type: HospitalTransactionTypes::HOSP_EPISODE_SETTLEMENT->value,
            description: "Penangkapan escrow deposit pelunasan discharge admisi #{$admissionId}",
            idempotencyKey: $idempotencyKey,
            entries: $lines->toEntries('IDR'),
            referenceType: 'HOSP_ADMISSION',
            referenceId: (string) $admissionId,
        ));
    }
}
