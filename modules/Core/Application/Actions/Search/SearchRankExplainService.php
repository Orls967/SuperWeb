<?php

declare(strict_types=1);

namespace Modules\Core\Application\Actions\Search;

use App\Models\User;
use Modules\Core\Domain\Models\CoreSearchIndex;
use Modules\Shared\Domain\Algorithms\Ui\Bm25Scorer;

/**
 * Service transparansi dan penjelasan kalkulasi skor relevansi (Search Ranking Explainability Engine).
 * Membantu auditor dan administrator memahami mengapa dokumen tertentu menempati posisi teratas.
 */
class SearchRankExplainService
{
    public function __construct(
        protected Bm25Scorer $bm25Scorer,
        protected TokenExtractor $tokenExtractor,
    ) {}

    /**
     * Menghasilkan uraian matematis pembentukan skor dokumen terhadap kueri pencarian.
     *
     * @return array{
     *     document_id: string,
     *     query: string,
     *     bm25_raw_score: float,
     *     term_breakdown: array<string, array{tf: int, idf: float, term_score: float}>,
     *     policy_multiplier: float,
     *     recency_bonus: float,
     *     final_score: float,
     *     explanation: string
     * }
     */
    public function explain(string $documentId, string $query, ?User $user = null): array
    {
        $doc = CoreSearchIndex::with('terms')->findOrFail($documentId);
        $queryTokens = $this->tokenExtractor->extractTokens($query);

        $docTerms = [];
        foreach ($doc->terms as $t) {
            $docTerms[$t->term] = (int) $t->frequency;
        }

        $totalDocs = max(1, CoreSearchIndex::count());
        $avgDocLen = (float) (CoreSearchIndex::avg('document_length') ?: 20.0);

        $termBreakdown = [];
        $rawBm25 = 0.0;

        foreach ($queryTokens as $term => $qFreq) {
            $dFreq = $docTerms[$term] ?? 0;
            $idf = $this->bm25Scorer->calculateIdf($totalDocs, max(1, $dFreq));
            $termScore = $this->bm25Scorer->scoreTerm($dFreq, (int) $doc->document_length, $avgDocLen, $idf);

            $termBreakdown[$term] = [
                'tf' => $dFreq,
                'idf' => round($idf, 4),
                'term_score' => round($termScore, 4),
            ];
            $rawBm25 += $termScore;
        }

        $policyMultiplier = ($user?->role === 'admin') ? 1.05 : 1.0;
        $recencyBonus = ($doc->indexed_at && $doc->indexed_at->diffInDays(now()) < 7) ? 0.15 : 0.0;
        $finalScore = round(($rawBm25 * $policyMultiplier) + $recencyBonus, 4);

        $explanation = sprintf(
            'Skor dasar BM25: %s. Multiplier hak akses (role: %s): %sx. Bonus kebaruan: +%s. Total skor akhir: %s.',
            round($rawBm25, 4),
            $user?->role ?? 'guest',
            $policyMultiplier,
            $recencyBonus,
            $finalScore
        );

        return [
            'document_id' => $documentId,
            'query' => $query,
            'bm25_raw_score' => round($rawBm25, 4),
            'term_breakdown' => $termBreakdown,
            'policy_multiplier' => $policyMultiplier,
            'recency_bonus' => $recencyBonus,
            'final_score' => $finalScore,
            'explanation' => $explanation,
        ];
    }
}
