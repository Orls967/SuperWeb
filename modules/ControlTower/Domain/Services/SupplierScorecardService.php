<?php

declare(strict_types=1);

namespace Modules\ControlTower\Domain\Services;

use Carbon\CarbonImmutable;
use Modules\Procurement\Contracts\SupplierLeadTimeStatistics;

/**
 * Evaluasi kinerja pemasok multi-kriteria (X53-2).
 *
 * Bobot komposit (0..1000, aritmatika integer):
 * - Ketepatan waktu (OTD on_time_permil): 45%
 * - Konsistensi lead time (mean_days vs sigma_days): 30%
 * - Kelengkapan volume sampel & keandalan pasokan (samples): 25%
 */
final class SupplierScorecardService
{
    public const TIER_STRATEGIC = 'STRATEGIC';

    public const TIER_PREFERRED = 'PREFERRED';

    public const TIER_WATCHLIST = 'WATCHLIST';

    public const TIER_AT_RISK = 'AT_RISK';

    private const WEIGHT_OTD = 45;

    private const WEIGHT_RELIABILITY = 30;

    private const WEIGHT_VOLUME = 25;

    private const FULL_VOLUME_SAMPLES = 10;

    public function __construct(
        private readonly SupplierLeadTimeStatistics $leadTimeStats,
    ) {}

    /**
     * Evaluasi seluruh pemasok yang tercatat pada statistik lead-time Procurement.
     *
     * @return list<array{
     *   supplier_ref: string,
     *   supplier_name: string,
     *   period: string,
     *   composite_score: int,
     *   otd_score: int,
     *   reliability_score: int,
     *   volume_score: int,
     *   on_time_permil: int,
     *   mean_lead_time_days: int,
     *   sigma_lead_time_days: int,
     *   samples_count: int,
     *   tier: string,
     *   trend_direction: string,
     *   monthly_history: list<array{period: string, mean_days: int, on_time_permil: int, samples: int}>,
     *   action_recommendation: string
     * }>
     */
    public function evaluateAll(string $asOfDate = '', int $historyMonths = 6): array
    {
        $effectiveDate = $this->resolveAsOfDate($asOfDate);
        $period = substr($effectiveDate, 0, 7);
        $rows = $this->leadTimeStats->allStatistics();

        if ($rows === []) {
            return [];
        }

        /** @var array<string, list<array{supplier_ref:string, supplier_name:string, item_code:string, mean_days:int, sigma_days:int, samples:int, on_time_permil:int}>> $grouped */
        $grouped = [];
        foreach ($rows as $row) {
            $ref = (string) $row['supplier_ref'];
            $grouped[$ref][] = $row;
        }

        $scorecards = [];
        foreach ($grouped as $supplierRef => $supplierRows) {
            $aggregated = $this->aggregateRows($supplierRows);
            $history = $this->leadTimeStats->monthlyHistory($supplierRef, max(1, $historyMonths), $effectiveDate);

            $scorecards[] = $this->scoreFromMetrics(
                supplierRef: $supplierRef,
                supplierName: $aggregated['supplier_name'],
                meanDays: $aggregated['mean_days'],
                sigmaDays: $aggregated['sigma_days'],
                samples: $aggregated['samples'],
                onTimePermil: $aggregated['on_time_permil'],
                monthlyHistory: $history,
                period: $period,
            );
        }

        return $scorecards;
    }

    /**
     * Evaluasi kartu skor untuk satu pemasok (agregasi lintas item atau 0 sampel bila belum ada data).
     *
     * @return array{
     *   supplier_ref: string,
     *   supplier_name: string,
     *   period: string,
     *   composite_score: int,
     *   otd_score: int,
     *   reliability_score: int,
     *   volume_score: int,
     *   on_time_permil: int,
     *   mean_lead_time_days: int,
     *   sigma_lead_time_days: int,
     *   samples_count: int,
     *   tier: string,
     *   trend_direction: string,
     *   monthly_history: list<array{period: string, mean_days: int, on_time_permil: int, samples: int}>,
     *   action_recommendation: string
     * }
     */
    public function evaluateSupplier(
        string $supplierRef,
        string $supplierName = '',
        string $asOfDate = '',
        int $historyMonths = 6,
    ): array {
        $effectiveDate = $this->resolveAsOfDate($asOfDate);
        $period = substr($effectiveDate, 0, 7);

        $rows = array_values(array_filter(
            $this->leadTimeStats->allStatistics(),
            static fn (array $row): bool => (string) $row['supplier_ref'] === $supplierRef,
        ));

        $history = $this->leadTimeStats->monthlyHistory($supplierRef, max(1, $historyMonths), $effectiveDate);

        if ($rows === []) {
            return $this->scoreFromMetrics(
                supplierRef: $supplierRef,
                supplierName: $supplierName !== '' ? $supplierName : $supplierRef,
                meanDays: 0,
                sigmaDays: 0,
                samples: 0,
                onTimePermil: 0,
                monthlyHistory: $history,
                period: $period,
            );
        }

        $aggregated = $this->aggregateRows($rows);

        return $this->scoreFromMetrics(
            supplierRef: $supplierRef,
            supplierName: $supplierName !== '' ? $supplierName : $aggregated['supplier_name'],
            meanDays: $aggregated['mean_days'],
            sigmaDays: $aggregated['sigma_days'],
            samples: $aggregated['samples'],
            onTimePermil: $aggregated['on_time_permil'],
            monthlyHistory: $history,
            period: $period,
        );
    }

    /**
     * Evaluasi kartu skor pemasok untuk satu item tertentu.
     *
     * @return array{
     *   supplier_ref: string,
     *   supplier_name: string,
     *   period: string,
     *   composite_score: int,
     *   otd_score: int,
     *   reliability_score: int,
     *   volume_score: int,
     *   on_time_permil: int,
     *   mean_lead_time_days: int,
     *   sigma_lead_time_days: int,
     *   samples_count: int,
     *   tier: string,
     *   trend_direction: string,
     *   monthly_history: list<array{period: string, mean_days: int, on_time_permil: int, samples: int}>,
     *   action_recommendation: string
     * }
     */
    public function evaluateSupplierItem(
        string $supplierRef,
        string $itemCode,
        string $supplierName = '',
        string $asOfDate = '',
        int $historyMonths = 6,
    ): array {
        $effectiveDate = $this->resolveAsOfDate($asOfDate);
        $period = substr($effectiveDate, 0, 7);
        $stat = $this->leadTimeStats->forSupplierAndItem($supplierRef, $itemCode);
        $history = $this->leadTimeStats->monthlyHistory($supplierRef, max(1, $historyMonths), $effectiveDate);

        return $this->scoreFromMetrics(
            supplierRef: $supplierRef,
            supplierName: $supplierName !== '' ? $supplierName : $supplierRef,
            meanDays: (int) $stat['mean_days'],
            sigmaDays: (int) $stat['sigma_days'],
            samples: (int) $stat['samples'],
            onTimePermil: (int) $stat['on_time_permil'],
            monthlyHistory: $history,
            period: $period,
        );
    }

    /**
     * Hitung skor komposit, tier, arah tren bulanan, dan rekomendasi tindakan secara deterministik (integer-only).
     *
     * @param  list<array{period: string, mean_days: int, on_time_permil: int, samples: int}>  $monthlyHistory
     * @return array{
     *   supplier_ref: string,
     *   supplier_name: string,
     *   period: string,
     *   composite_score: int,
     *   otd_score: int,
     *   reliability_score: int,
     *   volume_score: int,
     *   on_time_permil: int,
     *   mean_lead_time_days: int,
     *   sigma_lead_time_days: int,
     *   samples_count: int,
     *   tier: string,
     *   trend_direction: string,
     *   monthly_history: list<array{period: string, mean_days: int, on_time_permil: int, samples: int}>,
     *   action_recommendation: string
     * }
     */
    public function scoreFromMetrics(
        string $supplierRef,
        string $supplierName,
        int $meanDays,
        int $sigmaDays,
        int $samples,
        int $onTimePermil,
        array $monthlyHistory = [],
        string $period = '',
    ): array {
        $samples = max(0, $samples);
        $meanDays = max(0, $meanDays);
        $sigmaDays = max(0, $sigmaDays);
        $onTimePermil = max(0, min(1000, $onTimePermil));

        if ($samples === 0) {
            $otdScore = 0;
            $reliabilityScore = 0;
            $volumeScore = 0;
            $compositeScore = 0;
        } else {
            $otdScore = $onTimePermil;
            $reliabilityScore = $this->computeReliabilityScore($meanDays, $sigmaDays);
            $volumeScore = min(1000, intdiv($samples * 1000, self::FULL_VOLUME_SAMPLES));

            $compositeScore = intdiv(
                ($otdScore * self::WEIGHT_OTD)
                + ($reliabilityScore * self::WEIGHT_RELIABILITY)
                + ($volumeScore * self::WEIGHT_VOLUME),
                100,
            );
            $compositeScore = max(0, min(1000, $compositeScore));
        }

        $tier = $this->classifyTier($compositeScore);
        $normalizedHistory = $this->normalizeHistory($monthlyHistory);
        $trendDirection = $this->detectTrendDirection($normalizedHistory);

        return [
            'supplier_ref' => $supplierRef,
            'supplier_name' => $supplierName !== '' ? $supplierName : $supplierRef,
            'period' => $period !== '' ? $period : CarbonImmutable::now()->format('Y-m'),
            'composite_score' => $compositeScore,
            'otd_score' => $otdScore,
            'reliability_score' => $reliabilityScore,
            'volume_score' => $volumeScore,
            'on_time_permil' => $onTimePermil,
            'mean_lead_time_days' => $meanDays,
            'sigma_lead_time_days' => $sigmaDays,
            'samples_count' => $samples,
            'tier' => $tier,
            'trend_direction' => $trendDirection,
            'monthly_history' => $normalizedHistory,
            'action_recommendation' => $this->recommendAction($tier, $samples, $trendDirection),
        ];
    }

    public function classifyTier(int $compositeScore): string
    {
        return match (true) {
            $compositeScore >= 850 => self::TIER_STRATEGIC,
            $compositeScore >= 700 => self::TIER_PREFERRED,
            $compositeScore >= 500 => self::TIER_WATCHLIST,
            default => self::TIER_AT_RISK,
        };
    }

    private function computeReliabilityScore(int $meanDays, int $sigmaDays): int
    {
        if ($sigmaDays <= 0) {
            return 1000;
        }

        if ($meanDays <= 0) {
            return max(0, 1000 - ($sigmaDays * 100));
        }

        $cvPermil = intdiv($sigmaDays * 1000, $meanDays);

        return max(0, min(1000, 1000 - $cvPermil));
    }

    /**
     * @param  non-empty-list<array{supplier_ref:string, supplier_name:string, item_code:string, mean_days:int, sigma_days:int, samples:int, on_time_permil:int}>  $rows
     * @return array{supplier_name: string, mean_days: int, sigma_days: int, samples: int, on_time_permil: int}
     */
    private function aggregateRows(array $rows): array
    {
        $supplierName = (string) $rows[0]['supplier_name'];
        $totalSamples = 0;
        $weightedMean = 0;
        $weightedSigma = 0;
        $weightedOnTime = 0;

        foreach ($rows as $row) {
            $s = max(0, (int) $row['samples']);
            $totalSamples += $s;
            $weightedMean += max(0, (int) $row['mean_days']) * $s;
            $weightedSigma += max(0, (int) $row['sigma_days']) * $s;
            $weightedOnTime += max(0, min(1000, (int) $row['on_time_permil'])) * $s;
        }

        if ($totalSamples === 0) {
            return [
                'supplier_name' => $supplierName,
                'mean_days' => 0,
                'sigma_days' => 0,
                'samples' => 0,
                'on_time_permil' => 0,
            ];
        }

        return [
            'supplier_name' => $supplierName,
            'mean_days' => intdiv($weightedMean, $totalSamples),
            'sigma_days' => intdiv($weightedSigma, $totalSamples),
            'samples' => $totalSamples,
            'on_time_permil' => intdiv($weightedOnTime, $totalSamples),
        ];
    }

    /**
     * @param  list<array{period: string, mean_days: int, on_time_permil: int, samples: int}>  $history
     * @return list<array{period: string, mean_days: int, on_time_permil: int, samples: int}>
     */
    private function normalizeHistory(array $history): array
    {
        $normalized = [];
        foreach ($history as $point) {
            $normalized[] = [
                'period' => (string) $point['period'],
                'mean_days' => (int) $point['mean_days'],
                'on_time_permil' => (int) $point['on_time_permil'],
                'samples' => (int) $point['samples'],
            ];
        }

        return $normalized;
    }

    /**
     * @param  list<array{period: string, mean_days: int, on_time_permil: int, samples: int}>  $history
     */
    private function detectTrendDirection(array $history): string
    {
        $withSamples = array_values(array_filter(
            $history,
            static fn (array $item): bool => $item['samples'] > 0,
        ));

        if (count($withSamples) < 2) {
            return 'NO_DATA';
        }

        $first = $withSamples[0]['on_time_permil'];
        $last = $withSamples[count($withSamples) - 1]['on_time_permil'];
        $delta = $last - $first;

        return match (true) {
            $delta >= 50 => 'IMPROVING',
            $delta <= -50 => 'DECLINING',
            default => 'STABLE',
        };
    }

    private function recommendAction(string $tier, int $samples, string $trendDirection): string
    {
        if ($samples === 0) {
            return 'Belum ada sampel penerimaan; lakukan kualifikasi atau uji coba pengiriman terlebih dahulu.';
        }

        return match ($tier) {
            self::TIER_STRATEGIC => $trendDirection === 'DECLINING'
                ? 'Pertahankan kemitraan strategis namun jadwalkan review kuartalan atas penurunan tren OTD.'
                : 'Pertahankan kemitraan strategis, perluas kontrak jangka panjang / skema VMI.',
            self::TIER_PREFERRED => 'Pemasok andal; optimalkan alokasi pesanan dan evaluasi lead time secara berkala.',
            self::TIER_WATCHLIST => 'Pantau ketat deviasi lead time, tingkatkan safety stock penyangga, dan siapkan pemasok cadangan.',
            default => 'Risiko tinggi: aktifkan CAPA pemasok, batasi alokasi baru, dan alihkan volume ke pemasok cadangan.',
        };
    }

    private function resolveAsOfDate(string $asOfDate): string
    {
        return $asOfDate !== '' ? $asOfDate : CarbonImmutable::now()->toDateString();
    }
}
