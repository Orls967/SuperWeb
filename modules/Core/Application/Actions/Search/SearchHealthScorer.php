<?php

declare(strict_types=1);

namespace Modules\Core\Application\Actions\Search;

use Modules\Core\Domain\Models\CoreSearchIndex;
use Modules\Core\Domain\Models\CoreSearchQueryLog;
use Modules\Core\Domain\Models\CoreSearchTerm;

/**
 * Service penghitung skor kesehatan indeks pencarian global (Search Health & Quality Scorer).
 * Menghasilkan indeks komposit kesehatan sistem berdasarkan 5 dimensi:
 * 1. Coverage Ratio (Cakupan entitas terindeks)
 * 2. Average Latency Score (Kecepatan respon kueri)
 * 3. Zero-Result Penalty (Rasio kueri tanpa hasil)
 * 4. Index Defragmentation Score (Kepadatan posting list)
 * 5. Freshness Score (Kebaruan mutasi indeks).
 */
class SearchHealthScorer
{
    /**
     * Menghitung nilai kesehatan komposit (skala 0 - 100).
     *
     * @return array{
     *     overall_score: float,
     *     status: string,
     *     dimensions: array<string, float>,
     *     metrics: array<string, mixed>
     * }
     */
    public function calculateHealth(): array
    {
        $totalDocs = CoreSearchIndex::count();
        $totalTerms = CoreSearchTerm::count();

        // 1. Dimensi Latensi (Target: < 50ms = 100 poin)
        $avgLatency = (float) (CoreSearchQueryLog::where('created_at', '>=', now()->subDays(7))->avg('execution_time_ms') ?: 10.0);
        $latencyScore = max(0.0, min(100.0, 100.0 - (($avgLatency - 15.0) * 1.5)));

        // 2. Dimensi Zero-Result (Target: < 5% = 100 poin)
        $recentQueriesCount = CoreSearchQueryLog::where('created_at', '>=', now()->subDays(7))->count();
        $zeroResultsCount = CoreSearchQueryLog::where('created_at', '>=', now()->subDays(7))->where('results_count', 0)->count();
        $zeroResultRatio = $recentQueriesCount > 0 ? ($zeroResultsCount / $recentQueriesCount) : 0.0;
        $zeroResultScore = max(0.0, min(100.0, 100.0 - ($zeroResultRatio * 200.0)));

        // 3. Dimensi Kepadatan Term (Rata-rata 5-30 term per dokumen)
        $termsPerDoc = $totalDocs > 0 ? ($totalTerms / $totalDocs) : 0.0;
        $densityScore = ($termsPerDoc >= 5.0 && $termsPerDoc <= 50.0) ? 100.0 : max(40.0, 100.0 - abs($termsPerDoc - 20.0) * 2.0);

        // 4. Dimensi Kebaruan (Freshness)
        $staleDocsCount = CoreSearchIndex::where('indexed_at', '<', now()->subDays(30))->count();
        $freshnessScore = $totalDocs > 0 ? max(0.0, round((1.0 - ($staleDocsCount / $totalDocs)) * 100.0, 2)) : 100.0;

        // 5. Dimensi Integritas Struktur
        $danglingTerms = CoreSearchTerm::whereNotIn('search_index_id', CoreSearchIndex::query()->select('id'))->count();
        $integrityScore = ($danglingTerms === 0) ? 100.0 : max(0.0, 100.0 - ($danglingTerms * 5.0));

        // Skor Komposit Berbobot
        $composite = (
            ($latencyScore * 0.25) +
            ($zeroResultScore * 0.25) +
            ($densityScore * 0.15) +
            ($freshnessScore * 0.15) +
            ($integrityScore * 0.20)
        );

        $overall = round($composite, 2);

        $status = 'EXCELLENT';
        if ($overall < 60.0) {
            $status = 'CRITICAL';
        } elseif ($overall < 80.0) {
            $status = 'WARNING';
        } elseif ($overall < 90.0) {
            $status = 'GOOD';
        }

        return [
            'overall_score' => $overall,
            'status' => $status,
            'dimensions' => [
                'latency_score' => round($latencyScore, 2),
                'zero_result_score' => round($zeroResultScore, 2),
                'density_score' => round($densityScore, 2),
                'freshness_score' => round($freshnessScore, 2),
                'integrity_score' => round($integrityScore, 2),
            ],
            'metrics' => [
                'total_documents' => $totalDocs,
                'total_terms' => $totalTerms,
                'avg_latency_ms' => round($avgLatency, 2),
                'zero_result_ratio_pct' => round($zeroResultRatio * 100.0, 2),
                'dangling_terms' => $danglingTerms,
            ],
        ];
    }
}
