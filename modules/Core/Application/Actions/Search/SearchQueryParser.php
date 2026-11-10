<?php

declare(strict_types=1);

namespace Modules\Core\Application\Actions\Search;

/**
 * Parser kueri pencarian tingkat lanjut (Structured Search Query Parser).
 * Mendukung ekstraksi operator:
 * - Filter Modul: `module:Asset` atau `mod:Contract`
 * - Filter Status: `status:active` atau `status:pending`
 * - Frasa Eksak: `"heavy machinery overhaul"`
 * - Negasi / Exclude Term: `-broken`
 * - Filter Entitas: `entity:WorkOrder`
 */
class SearchQueryParser
{
    /**
     * Mem-parsing string kueri mentah menjadi struktur filter dan kata kunci bersih.
     *
     * @return array{
     *     raw_query: string,
     *     clean_keyword: string,
     *     exact_phrases: string[],
     *     excluded_terms: string[],
     *     filters: array<string, string>
     * }
     */
    public function parse(string $query): array
    {
        $raw = trim($query);
        $filters = [];
        $exactPhrases = [];
        $excludedTerms = [];

        // 1. Ekstraksi frasa eksak dalam tanda petik ganda "..."
        $cleaned = preg_replace_callback('/"([^"]+)"/', function ($matches) use (&$exactPhrases) {
            $phrase = trim($matches[1]);
            if (! empty($phrase)) {
                $exactPhrases[] = mb_strtolower($phrase);
            }

            return ' ';
        }, $raw);

        // 2. Ekstraksi operator terstruktur seperti module:xyz, status:xyz, entity:xyz
        $tokens = preg_split('/\s+/', (string) $cleaned, -1, PREG_SPLIT_NO_EMPTY);
        $cleanTokens = [];

        if ($tokens) {
            foreach ($tokens as $token) {
                // Negasi: -kata
                if (str_starts_with($token, '-') && strlen($token) > 1) {
                    $excludedTerms[] = mb_strtolower(substr($token, 1));

                    continue;
                }

                // Operator key:val
                if (str_contains($token, ':')) {
                    $parts = explode(':', $token, 2);
                    $key = mb_strtolower(trim($parts[0]));
                    $val = trim($parts[1]);

                    if (in_array($key, ['mod', 'module', 'status', 'entity', 'scope', 'tag'], true)) {
                        $canonicalKey = ($key === 'mod') ? 'module' : $key;
                        $filters[$canonicalKey] = $val;

                        continue;
                    }
                }

                $cleanTokens[] = $token;
            }
        }

        $cleanKeyword = trim(implode(' ', $cleanTokens));

        return [
            'raw_query' => $raw,
            'clean_keyword' => $cleanKeyword,
            'exact_phrases' => $exactPhrases,
            'excluded_terms' => $excludedTerms,
            'filters' => $filters,
        ];
    }
}
