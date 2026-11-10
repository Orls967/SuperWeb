<?php

declare(strict_types=1);

namespace Modules\Core\Application\Actions\Search;

use App\Models\User;
use Modules\Core\Domain\Models\CoreSearchIndex;
use Modules\Core\Domain\Models\CoreSearchQueryLog;
use Modules\Core\Domain\Models\CoreSearchTerm;
use Modules\Shared\Application\SearchRegistry;
use Modules\Shared\Domain\Algorithms\Ui\Bm25Scorer;
use Modules\Shared\Domain\Algorithms\Ui\PolicyFirstRanker;
use Modules\Shared\Domain\Algorithms\Ui\TrigramJaccardMatcher;

/**
 * Service orkestrasi pencarian global enterprise.
 * Menggabungkan BM25 ranking, fuzzy spelling suggestions, Policy-First zero-leak RBAC,
 * dan adaptor lintas modul dengan audit telemetri kepatuhan anti-IDOR.
 */
class SearchOrchestratorService
{
    public function __construct(
        protected Bm25Scorer $bm25Scorer,
        protected TrigramJaccardMatcher $jaccardMatcher,
        protected PolicyFirstRanker $policyRanker,
        protected TokenExtractor $tokenExtractor,
        protected PiiRedactorService $piiRedactor,
        protected ?SearchRegistry $searchRegistry = null,
    ) {}

    protected function getSearchRegistry(): SearchRegistry
    {
        return $this->searchRegistry ?? app(SearchRegistry::class);
    }

    /**
     * Melakukan kueri pencarian global lintas modul dengan peringkat BM25 dan penyaringan RBAC ketat.
     *
     * @return array{
     *     query: string,
     *     suggestion: string|null,
     *     total: int,
     *     data: list<array<string, mixed>>,
     *     certificate: array<string, mixed>,
     *     execution_time_ms: float
     * }
     */
    public function search(string $query, ?User $user = null): array
    {
        $startTime = microtime(true);
        $cleanQuery = trim($query);

        if (mb_strlen($cleanQuery) < 2) {
            return [
                'query' => $cleanQuery,
                'suggestion' => null,
                'total' => 0,
                'data' => [],
                'certificate' => [],
                'execution_time_ms' => 0.0,
            ];
        }

        $queryTokens = $this->tokenExtractor->extractTokens($cleanQuery);
        $candidates = [];
        $suggestion = null;

        if (! empty($queryTokens)) {
            $candidates = $this->queryInvertedIndex($queryTokens);
        }

        // Jika hasil kosong, cari saran koreksi ejaan dengan Trigram Jaccard
        if (empty($candidates)) {
            $suggestion = $this->findSpellingSuggestion(array_keys($queryTokens));
        }

        // Terapkan Policy-First RBAC Ranking & Filter
        $ranked = $this->policyRanker->rankAndFilter($candidates, $user);
        $results = $ranked['results'];

        // Integrasikan hasil dari SearchRegistry lintas modul (deduplikasi dengan hasil indeks yang sudah ada)
        try {
            $existingUrls = [];
            foreach ($results as $r) {
                if (! empty($r['metadata']['url'])) {
                    $existingUrls[$r['metadata']['url']] = true;
                }
            }

            $legacyResults = $this->getSearchRegistry()->search($cleanQuery, $user);
            foreach ($legacyResults as $item) {
                $itemUrl = $item['url'] ?? '';
                if (! empty($itemUrl) && isset($existingUrls[$itemUrl])) {
                    continue;
                }

                $results[] = [
                    'id' => 'legacy-'.md5($item['title'].$itemUrl),
                    'module' => $item['category'] ?? 'General',
                    'entity_type' => 'LegacyEntity',
                    'entity_id' => $itemUrl,
                    'title' => $item['title'] ?? '',
                    'content' => $item['subtitle'] ?? '',
                    'metadata' => [
                        'url' => $itemUrl ?: '#',
                        'badge' => $item['badge'] ?? '',
                        'badge_color' => $item['badge_color'] ?? '',
                    ],
                    'score' => 1.0,
                    'final_score' => 1.0,
                ];
            }
        } catch (\Throwable) {
            // Tangani toleran kegagalan registry lama
        }

        // Potong batas hasil teratas (maksimal 20 item)
        $pagedResults = array_slice($results, 0, 20);
        $executionTimeMs = round((microtime(true) - $startTime) * 1000, 2);

        // Catat telemetri kepatuhan
        $this->logTelemetry($cleanQuery, $user, count($pagedResults), $executionTimeMs);

        return [
            'query' => $cleanQuery,
            'suggestion' => $suggestion,
            'total' => count($pagedResults),
            'data' => array_values($pagedResults),
            'certificate' => $ranked['certificate'],
            'execution_time_ms' => $executionTimeMs,
        ];
    }

    /**
     * Mengambil kandidat dokumen dari inverted index dan menghitung skor BM25.
     *
     * @param  array<string, int>  $queryTokens
     * @return array<int, array<string, mixed>>
     */
    protected function queryInvertedIndex(array $queryTokens): array
    {
        $termStrings = array_keys($queryTokens);

        // Cari search index IDs yang memiliki term yang cocok
        $matchingIndexIds = CoreSearchTerm::query()
            ->whereIn('term', $termStrings)
            ->pluck('search_index_id')
            ->unique()
            ->toArray();

        // Cari juga secara langsung pada title atau content
        $directIndices = CoreSearchIndex::query()
            ->where(function ($q) use ($termStrings) {
                foreach ($termStrings as $t) {
                    $q->orWhere('title', 'LIKE', "%{$t}%")
                        ->orWhere('content', 'LIKE', "%{$t}%");
                }
            })
            ->pluck('id')
            ->toArray();

        $allIndexIds = array_unique(array_merge($matchingIndexIds, $directIndices));

        if (empty($allIndexIds)) {
            return [];
        }

        $docs = CoreSearchIndex::query()
            ->whereIn('id', $allIndexIds)
            ->with('terms')
            ->get();

        $totalDocs = CoreSearchIndex::count();
        $avgDocLength = (float) (CoreSearchIndex::avg('document_length') ?: 20.0);

        // Hitung IDF untuk setiap term kueri
        $idfs = [];
        foreach ($termStrings as $term) {
            $docFreq = CoreSearchTerm::query()->where('term', $term)->distinct('search_index_id')->count();
            $idfs[$term] = $this->bm25Scorer->calculateIdf($totalDocs, max(1, $docFreq));
        }

        $scoredCandidates = [];
        foreach ($docs as $doc) {
            $docTerms = [];
            foreach ($doc->terms as $st) {
                $docTerms[$st->term] = (int) $st->frequency;
            }

            // Fallback: Jika terms belum diekstraksi ke relasi, ekstrak on-the-fly
            if (empty($docTerms)) {
                $docTerms = $this->tokenExtractor->extractTokens($doc->title.' '.$doc->content);
            }

            $scoreResult = $this->bm25Scorer->compute(
                documentId: (string) $doc->id,
                queryTerms: $queryTokens,
                docTerms: $docTerms,
                idfs: $idfs,
                docLength: (int) ($doc->document_length ?: count($docTerms)),
                avgDocLength: $avgDocLength
            );

            $scoredCandidates[] = [
                'id' => (string) $doc->id,
                'module' => $doc->module,
                'entity_type' => $doc->entity_type,
                'entity_id' => $doc->entity_id,
                'title' => $doc->title,
                'content' => $doc->content,
                'metadata' => $doc->metadata ?? [],
                'required_permission' => $doc->required_permission,
                'scope_type' => $doc->scope_type,
                'scope_id' => $doc->scope_id,
                'bm25_score' => $scoreResult['score'],
                'score' => $scoreResult['score'],
                'indexed_at' => $doc->indexed_at?->toIso8601String(),
            ];
        }

        return $scoredCandidates;
    }

    /**
     * Mencari saran ejaan dari kamus term menggunakan Trigram Jaccard Matcher.
     *
     * @param  string[]  $terms
     */
    protected function findSpellingSuggestion(array $terms): ?string
    {
        $corpus = CoreSearchTerm::query()
            ->select('term')
            ->distinct()
            ->limit(500)
            ->pluck('term')
            ->toArray();

        if (empty($corpus)) {
            return null;
        }

        $suggestions = [];
        $changed = false;

        foreach ($terms as $term) {
            $match = $this->jaccardMatcher->findBestMatch($term, $corpus, 0.40);
            if ($match !== null && $match['match'] !== $term) {
                $suggestions[] = $match['match'];
                $changed = true;
            } else {
                $suggestions[] = $term;
            }
        }

        return $changed ? implode(' ', $suggestions) : null;
    }

    /**
     * Mencatat log telemetri pencarian dengan proteksi PII dan hashing kueri.
     */
    protected function logTelemetry(string $rawQuery, ?User $user, int $resultsCount, float $execTimeMs): void
    {
        try {
            $redacted = $this->piiRedactor->redact($rawQuery);
            $queryHash = $this->piiRedactor->hashQuery($rawQuery);

            CoreSearchQueryLog::create([
                'user_id' => $user?->id,
                'user_role' => $user?->role ?? 'guest',
                'raw_query_hash' => $queryHash,
                'redacted_query' => $redacted,
                'results_count' => $resultsCount,
                'execution_time_ms' => $execTimeMs,
            ]);
        } catch (\Throwable) {
            // Hindari memblokir alur utama jika pencatatan log terkendala
        }
    }
}
