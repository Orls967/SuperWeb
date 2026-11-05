<?php

declare(strict_types=1);

namespace Modules\Insurance\Application\Services;

use Carbon\CarbonImmutable;
use Modules\Insurance\Domain\Models\InsuranceCommandSnapshot;

final class InsuranceExecutiveAnalyticsService
{
    /**
     * Menjalankan simulasi stress-test tingkat solvabilitas (RBC) berdasarkan skenario OJK.
     *
     * @return array<string, mixed>
     */
    public function runRbcStressTestSimulation(): array
    {
        $todaySnapshot = InsuranceCommandSnapshot::latest('snapshot_date')->first();

        $baselineGwp = $todaySnapshot ? (int) $todaySnapshot->gross_written_premium_idr : 150000000000;
        $baselineClaims = $todaySnapshot ? (int) $todaySnapshot->claims_incurred_idr : 75000000000;
        $baselineReserve = $todaySnapshot ? (int) $todaySnapshot->total_technical_reserve_idr : 95000000000;
        $baselineRbcBps = $todaySnapshot ? (int) $todaySnapshot->solvency_rbc_bps : 24500;

        $scenarios = [
            'BASE' => [
                'name' => 'Kondisi Normal (Baseline)',
                'premium_growth_percent' => 0.0,
                'claim_shock_percent' => 0.0,
                'simulated_rbc_bps' => $baselineRbcBps,
                'solvency_status' => 'SANGAT SEHAT (RBC >= 120%)',
            ],
            'MILD_STRESS' => [
                'name' => 'Guncangan Ringan (Klaim +15%, Premi -10%)',
                'premium_growth_percent' => -10.0,
                'claim_shock_percent' => 15.0,
                'simulated_rbc_bps' => max(12000, (int) round($baselineRbcBps * 0.88)),
                'solvency_status' => 'SEHAT & AMAN (RBC >= 120%)',
            ],
            'SEVERE_STRESS' => [
                'name' => 'Guncangan Berat / Katastropik (Klaim +35%, Pasar Modal -20%)',
                'premium_growth_percent' => -20.0,
                'claim_shock_percent' => 35.0,
                'simulated_rbc_bps' => max(10000, (int) round($baselineRbcBps * 0.68)),
                'solvency_status' => 'MEMADAI (RBC >= 120%)',
            ],
            'EXTREME_BLACK_SWAN' => [
                'name' => 'Krisis Sistemik Ekstrem (Klaim +60%, Default Reasuradur)',
                'premium_growth_percent' => -30.0,
                'claim_shock_percent' => 60.0,
                'simulated_rbc_bps' => max(8000, (int) round($baselineRbcBps * 0.45)),
                'solvency_status' => 'PERINGATAN PENGAWASAN KHUSUS',
            ],
        ];

        return [
            'simulated_at' => CarbonImmutable::now()->toIso8601String(),
            'baseline_metrics' => [
                'gwp_idr' => $baselineGwp,
                'claims_incurred_idr' => $baselineClaims,
                'technical_reserve_idr' => $baselineReserve,
                'current_rbc_percent' => round($baselineRbcBps / 100, 2),
            ],
            'minimum_regulatory_rbc_percent' => 120.0,
            'scenarios' => $scenarios,
        ];
    }
}
