<?php

declare(strict_types=1);

namespace Modules\Core\Application\Actions\Search;

use Modules\Core\Domain\Models\CoreSearchIndex;
use Modules\Core\Domain\Models\CoreSearchTerm;

/**
 * Service pelengkap kueri otomatis instan (Instant Typeahead Auto-Completer).
 */
class SearchQueryAutoCompleter
{
    /**
     * Menghasilkan daftar saran kelanjutan kueri (prefix completion).
     *
     * @return string[]
     */
    public function getCompletions(string $prefix, int $limit = 5): array
    {
        $clean = mb_strtolower(trim($prefix));
        if (mb_strlen($clean) < 2) {
            return [];
        }

        // Cari dari terms
        $termSuggestions = CoreSearchTerm::query()
            ->where('term', 'LIKE', "{$clean}%")
            ->select('term')
            ->distinct()
            ->limit($limit)
            ->pluck('term')
            ->toArray();

        // Cari juga dari prefix judul dokumen
        $titleSuggestions = CoreSearchIndex::query()
            ->where('title', 'LIKE', "{$clean}%")
            ->select('title')
            ->limit($limit)
            ->pluck('title')
            ->toArray();

        $combined = array_unique(array_merge($termSuggestions, $titleSuggestions));

        return array_slice($combined, 0, $limit);
    }
}
