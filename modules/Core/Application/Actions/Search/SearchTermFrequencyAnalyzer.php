<?php

declare(strict_types=1);

namespace Modules\Core\Application\Actions\Search;

use Modules\Core\Domain\Models\CoreSearchTerm;

/**
 * Service analisis frekuensi term dan hukum Zipf (Term Frequency & Zipf Distribution Analyzer).
 * Membantu mengidentifikasi kata kunci yang terlalu sering muncul sehingga dapat diusulkan menjadi stopwords.
 */
class SearchTermFrequencyAnalyzer
{
    /**
     * Menganalisis token dengan frekuensi kemunculan tertinggi pada seluruh dokumen terindeks.
     *
     * @return array<int, array{term: string, total_frequency: int, doc_count: int}>
     */
    public function getTopFrequentTerms(int $limit = 25): array
    {
        return CoreSearchTerm::query()
            ->selectRaw('term, sum(frequency) as total_frequency, count(distinct search_index_id) as doc_count')
            ->groupBy('term')
            ->orderByDesc('total_frequency')
            ->limit($limit)
            ->get()
            ->map(fn ($r) => [
                'term' => $r->term,
                'total_frequency' => (int) $r->total_frequency,
                'doc_count' => (int) $r->doc_count,
            ])
            ->toArray();
    }
}
