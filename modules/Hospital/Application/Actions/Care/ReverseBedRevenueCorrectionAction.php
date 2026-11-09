<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\Actions\Care;

use Modules\Hospital\Domain\Models\Admission;
use Modules\Hospital\Domain\Services\HospitalLedgerPostingService;

/**
 * Aksi Pembalik Koreksi Pendapatan Tempat Tidur (Fase 87.7).
 * Debit: hsp:hospital_revenue:IDR (pembalik)
 * Kredit: hsp:patient_receivable:IDR
 * DILARANG memposting angka negatif; seluruh pembatalan menggunakan pembalik.
 */
class ReverseBedRevenueCorrectionAction
{
    public function __construct(
        protected readonly HospitalLedgerPostingService $ledgerPostingService
    ) {}

    public function execute(int $admissionId, int $reversedAmountIdr, string $reason, string $idempotencyKey): void
    {
        $admission = Admission::findOrFail($admissionId);

        $this->ledgerPostingService->postBedDayReversal(
            $admission->id,
            $reversedAmountIdr,
            $idempotencyKey,
            $reason
        );

        $admission->total_billed_idr = max(0, $admission->total_billed_idr - $reversedAmountIdr);
        $admission->save();
    }
}
