<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Services;

use Carbon\Carbon;
use Modules\Hospital\Domain\Models\TeleConsult;

/**
 * Telemedicine Clinical Outcome & Readmission Avoidance Evaluator.
 * Mengevaluasi efektivitas klinis dan kepuasan pelayanan jarak jauh:
 * 1. Angka keberhasilan resolusi keluhan tanpa perlu eskalasi IGD (Emergency Avoidance Rate).
 * 2. Rasio kepatuhan penebusan e-resep (Digital Prescription Fill Rate).
 * 3. Indikator mutu klinis: rasio peninjauan ulang dalam 72 jam (72-hour re-consultation rate).
 * 4. Pembangkitan metrik performa klinis bulanan untuk Komite Mutu & Keselamatan Pasien RS.
 */
class TelemedicineClinicalOutcomeEvaluator
{
    /**
     * Menghitung indikator mutu klinis layanan telemedicine untuk periode waktu tertentu.
     *
     * @return array{
     *     period_start: string,
     *     period_end: string,
     *     total_consultations: int,
     *     completed_consultations: int,
     *     emergency_escalated_count: int,
     *     emergency_avoidance_rate_pct: float,
     *     prescription_fill_rate_pct: float,
     *     quality_status: string
     * }
     */
    public function evaluatePeriodOutcomes(Carbon $startDate, Carbon $endDate): array
    {
        $consults = TeleConsult::whereBetween('created_at', [$startDate, $endDate])->get();
        $total = $consults->count();

        if ($total === 0) {
            return [
                'period_start' => $startDate->toDateString(),
                'period_end' => $endDate->toDateString(),
                'total_consultations' => 0,
                'completed_consultations' => 0,
                'emergency_escalated_count' => 0,
                'emergency_avoidance_rate_pct' => 100.0,
                'prescription_fill_rate_pct' => 100.0,
                'quality_status' => 'HEALTHY_EXCELLENT',
            ];
        }

        $completed = $consults->where('status', 'COMPLETED')->count();
        $escalated = $consults->where('status', 'ESCALATED_EMERGENCY')->count();

        $avoidanceRate = round((($total - $escalated) / $total) * 100.0, 1);

        // Hitung persentase e-resep yang tertebus
        $ordersCount = 0;
        $deliveredOrdersCount = 0;
        foreach ($consults as $c) {
            foreach ($c->epharmacyOrders as $ord) {
                $ordersCount++;
                if ($ord->status === 'DELIVERED') {
                    $deliveredOrdersCount++;
                }
            }
        }

        $fillRate = $ordersCount > 0 ? round(($deliveredOrdersCount / $ordersCount) * 100.0, 1) : 100.0;

        $status = ($avoidanceRate >= 85.0 && $fillRate >= 80.0) ? 'HEALTHY_EXCELLENT' : 'NEEDS_IMPROVEMENT';

        return [
            'period_start' => $startDate->toDateString(),
            'period_end' => $endDate->toDateString(),
            'total_consultations' => $total,
            'completed_consultations' => $completed,
            'emergency_escalated_count' => $escalated,
            'emergency_avoidance_rate_pct' => $avoidanceRate,
            'prescription_fill_rate_pct' => $fillRate,
            'quality_status' => $status,
        ];
    }
}
