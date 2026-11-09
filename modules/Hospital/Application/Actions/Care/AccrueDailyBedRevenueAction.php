<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\Actions\Care;

use Modules\Hospital\Domain\Models\Admission;
use Modules\Hospital\Domain\Services\HospitalLedgerPostingService;

/**
 * Aksi Pengakuan Pendapatan Harian Tempat Tidur Rawat Inap (Bed-Day) (Fase 87.7).
 * Debit: hsp:patient_receivable:IDR
 * Kredit: hsp:hospital_revenue:IDR
 */
class AccrueDailyBedRevenueAction
{
    public function __construct(
        protected readonly HospitalLedgerPostingService $ledgerPostingService
    ) {}

    public function execute(int $admissionId, int $days = 1, ?string $idempotencyKey = null): void
    {
        $admission = Admission::with('bed')->findOrFail($admissionId);
        $dailyRate = $admission->bed?->rate_per_day_idr ?? 500000;
        $totalAccrual = $dailyRate * $days;

        $key = $idempotencyKey ?? "hsp:bedday:{$admission->id}:" . date('Ymd');

        $this->ledgerPostingService->postDailyBedDayAccrual($admission->id, (int) $totalAccrual, $key);

        $admission->total_billed_idr += $totalAccrual;
        $admission->save();
    }
}
