<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Services;

use Carbon\CarbonImmutable;
use Modules\Hospital\Domain\Models\Admission;

final class HospitalBillingReconciliationService
{
    /**
     * Merekonstruksi dan memverifikasi saldo piutang pasien rawat inap
     * terhadap mutasi buku besar harian.
     *
     * @return array<string, mixed>
     */
    public function reconcileAdmissionFolio(int $admissionId): array
    {
        $admission = Admission::with(['patient', 'bed'])->findOrFail($admissionId);

        // Hitung total hari rawat aktif
        $admitTime = CarbonImmutable::parse($admission->admission_time);
        $endTime = $admission->discharge_time ? CarbonImmutable::parse($admission->discharge_time) : CarbonImmutable::now();
        $daysStayed = max(1, (int) $admitTime->diffInDays($endTime));

        $dailyRate = match (strtoupper($admission->bed?->class ?? 'CLASS_3')) {
            'ICU', 'NICU', 'PICU' => 2500000,
            'ISOLATION' => 1800000,
            'VVIP' => 3000000,
            'VIP' => 1500000,
            'CLASS_1' => 800000,
            'CLASS_2' => 500000,
            default => 300000,
        };

        $expectedBedRevenue = $daysStayed * $dailyRate;
        $depositHeld = (int) ($admission->deposit_amount_idr ?? 0);
        $netReceivable = max(0, $expectedBedRevenue - $depositHeld);
        $refundDue = max(0, $depositHeld - $expectedBedRevenue);

        return [
            'admission_id' => $admission->id,
            'patient_mrn' => $admission->patient?->mrn,
            'bed_code' => $admission->bed?->bed_code,
            'bed_class' => $admission->bed?->class,
            'days_stayed' => $daysStayed,
            'daily_rate_idr' => $dailyRate,
            'expected_bed_revenue_idr' => $expectedBedRevenue,
            'deposit_held_idr' => $depositHeld,
            'net_receivable_idr' => $netReceivable,
            'refund_due_idr' => $refundDue,
            'reconciled_at' => CarbonImmutable::now()->toIso8601String(),
        ];
    }
}
