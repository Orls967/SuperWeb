<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Application\Services;

/**
 * Mesin Benchmark Modal Kerja & Estimasi Pembebasan Kas (Working Capital Benchmark) (54.5).
 * Membandingkan kinerja Cash Conversion Cycle entitas anak terhadap tolok ukur industri
 * serta menghitung nilai likuiditas kas yang dapat dibebaskan bila CCC dipercepat.
 */
class WorkingCapitalBenchmarkEngine
{
    /**
     * Standar benchmark CCC per industri (dalam hari).
     */
    private const INDUSTRY_BENCHMARKS = [
        'LOGISTICS' => ['dso' => 45, 'dio' => 15, 'dpo' => 50, 'target_ccc' => 10],
        'RETAIL' => ['dso' => 10, 'dio' => 40, 'dpo' => 60, 'target_ccc' => -10],
        'MANUFACTURING' => ['dso' => 50, 'dio' => 45, 'dpo' => 60, 'target_ccc' => 35],
        'TECHNOLOGY' => ['dso' => 30, 'dio' => 5, 'dpo' => 45, 'target_ccc' => -10],
        'DEFAULT' => ['dso' => 45, 'dio' => 30, 'dpo' => 60, 'target_ccc' => 15],
    ];

    /**
     * Menganalisis efisiensi CCC entitas dan menghitung potensi pembebasan kas.
     *
     * @return array{
     *     entity_code: string,
     *     industry: string,
     *     actual_ccc_days: int,
     *     target_ccc_days: int,
     *     ccc_gap_days: int,
     *     daily_revenue_minor: int,
     *     potential_cash_unlock_minor: int,
     *     optimization_priority: string
     * }
     */
    public function benchmarkEntity(
        string $entityCode,
        string $industry,
        int $revenueMinor,
        int $actualCccDays
    ): array {
        $indKey = strtoupper(trim($industry));
        $bench = self::INDUSTRY_BENCHMARKS[$indKey] ?? self::INDUSTRY_BENCHMARKS['DEFAULT'];

        $targetCcc = $bench['target_ccc'];
        $gapDays = max(0, $actualCccDays - $targetCcc);

        $dailyRevenue = ($revenueMinor > 0) ? (int) intdiv($revenueMinor, 365) : 0;
        $cashUnlock = $dailyRevenue * $gapDays;

        $priority = ($gapDays >= 30) ? 'HIGH_PRIORITY' : (($gapDays >= 10) ? 'MEDIUM_PRIORITY' : 'OPTIMAL');

        return [
            'entity_code' => $entityCode,
            'industry' => $indKey,
            'actual_ccc_days' => $actualCccDays,
            'target_ccc_days' => $targetCcc,
            'ccc_gap_days' => $gapDays,
            'daily_revenue_minor' => $dailyRevenue,
            'potential_cash_unlock_minor' => $cashUnlock,
            'optimization_priority' => $priority,
        ];
    }
}
