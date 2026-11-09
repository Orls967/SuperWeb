<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Services;

use Carbon\Carbon;
use Modules\Hospital\Domain\Models\ChronicSubscription;
use Modules\Hospital\Domain\Models\EPharmacyOrder;

/**
 * Chronic Medication Adherence Analyzer & MPR Calculator.
 * Mengukur kepatuhan terapi jangka panjang pasien penyakit tidak menular (PTM):
 * 1. Kalkulasi Medication Possession Ratio (MPR) dan Proportion of Days Covered (PDC).
 * 2. Standar adherensi klinis: Adherensi Optimal (MPR >= 80%), Sub-optimal (50-79%), Buruk (< 50%).
 * 3. Deteksi kesenjangan konsumsi obat (therapeutic gap days) yang berisiko memicu krisis klinis.
 * 4. Pemicuan notifikasi edukasi kepatuhan untuk pencegahan komplikasi mikrovaskular/makrovaskular.
 */
class ChronicMedicationAdherenceAnalyzer
{
    private const ADHERENCE_OPTIMAL_THRESHOLD_PCT = 80.0;

    /**
     * Menghitung nilai Medication Possession Ratio (MPR) untuk satu langganan obat kronis.
     *
     * @return array{
     *     mpr_percentage: float,
     *     adherence_tier: string, // OPTIMAL, SUBOPTIMAL, POOR
     *     total_days_supplied: int,
     *     observation_days: int,
     *     therapeutic_gap_days: int,
     *     requires_clinical_outreach: bool
     * }
     */
    public function analyzeSubscriptionAdherence(ChronicSubscription $subscription, int $observationPeriodDays = 90): array
    {
        $startDate = Carbon::now()->subDays($observationPeriodDays);

        // Ambil riwayat order yang telah terkirim selama periode observasi
        $orders = EPharmacyOrder::where('patient_id', $subscription->patient_id)
            ->where('drug_code', $subscription->drug_code)
            ->where('status', 'DELIVERED')
            ->where('created_at', '>=', $startDate)
            ->get();

        $totalDaysSupplied = 0;
        foreach ($orders as $order) {
            // Asumsi 30 hari pasokan per penebusan bulanan
            $totalDaysSupplied += 30;
        }

        $mpr = $observationPeriodDays > 0 ? ($totalDaysSupplied / $observationPeriodDays) * 100.0 : 0.0;
        $mprClamped = round(min(120.0, max(0.0, $mpr)), 1);

        $gapDays = max(0, $observationPeriodDays - $totalDaysSupplied);

        $tier = match (true) {
            $mprClamped >= self::ADHERENCE_OPTIMAL_THRESHOLD_PCT => 'OPTIMAL',
            $mprClamped >= 50.0 => 'SUBOPTIMAL',
            default => 'POOR',
        };

        $needsOutreach = $tier !== 'OPTIMAL' || $gapDays > 14;

        return [
            'mpr_percentage' => $mprClamped,
            'adherence_tier' => $tier,
            'total_days_supplied' => $totalDaysSupplied,
            'observation_days' => $observationPeriodDays,
            'therapeutic_gap_days' => $gapDays,
            'requires_clinical_outreach' => $needsOutreach,
        ];
    }
}
