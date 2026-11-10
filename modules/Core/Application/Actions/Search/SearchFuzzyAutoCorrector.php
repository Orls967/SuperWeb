<?php

declare(strict_types=1);

namespace Modules\Core\Application\Actions\Search;

use Modules\Core\Domain\Models\CoreSearchTerm;
use Modules\Shared\Domain\Algorithms\Ui\TrigramJaccardMatcher;

/**
 * Service koreksi saltik multi-token dan frasa otomatis (Multi-Token Fuzzy Auto-Corrector).
 */
class SearchFuzzyAutoCorrector
{
    public function __construct(
        protected TokenExtractor $tokenExtractor,
        protected TrigramJaccardMatcher $jaccardMatcher,
    ) {}

    /**
     * Memperbaiki susunan kata kueri pengguna berdasarkan kamus leksikon indeks yang tersedia.
     *
     * @return array{
     *     original_query: string,
     *     corrected_query: string,
     *     is_corrected: bool,
     *     replacements: array<string, string>,
     *     confidence_score: float
     * }
     */
    public function autoCorrect(string $query, float $threshold = 0.45): array
    {
        $tokens = $this->tokenExtractor->extractTokens($query);
        if (empty($tokens)) {
            return [
                'original_query' => $query,
                'corrected_query' => $query,
                'is_corrected' => false,
                'replacements' => [],
                'confidence_score' => 1.0,
            ];
        }

        $corpus = CoreSearchTerm::query()->select('term')->distinct()->limit(500)->pluck('term')->toArray();
        if (empty($corpus)) {
            return [
                'original_query' => $query,
                'corrected_query' => $query,
                'is_corrected' => false,
                'replacements' => [],
                'confidence_score' => 1.0,
            ];
        }

        $replacements = [];
        $totalSim = 0.0;
        $checkedWords = 0;

        foreach (array_keys($tokens) as $word) {
            // Jika kata sudah ada di corpus eksak, tidak perlu diperbaiki
            if (in_array($word, $corpus, true)) {
                $totalSim += 1.0;
                $checkedWords++;

                continue;
            }

            $match = $this->jaccardMatcher->findBestMatch($word, $corpus, $threshold);
            if ($match !== null && $match['match'] !== $word) {
                $replacements[$word] = $match['match'];
                $totalSim += $match['similarity'];
            } else {
                $totalSim += 0.5;
            }
            $checkedWords++;
        }

        $isCorrected = ! empty($replacements);
        $correctedQuery = $query;

        if ($isCorrected) {
            foreach ($replacements as $orig => $rep) {
                $correctedQuery = (string) preg_replace('/\b'.preg_quote($orig, '/').'\b/i', $rep, $correctedQuery);
            }
        }

        $avgConfidence = $checkedWords > 0 ? round($totalSim / $checkedWords, 4) : 1.0;

        return [
            'original_query' => $query,
            'corrected_query' => $correctedQuery,
            'is_corrected' => $isCorrected,
            'replacements' => $replacements,
            'confidence_score' => $avgConfidence,
        ];
    }
}
