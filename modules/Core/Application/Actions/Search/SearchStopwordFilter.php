<?php

declare(strict_types=1);

namespace Modules\Core\Application\Actions\Search;

/**
 * Filter kata henti (Dynamic Stopword Filter) yang menggabungkan kamus baku
 * dan istilah korporat non-diskriminatif untuk efisiensi pengindeksan posting term.
 */
class SearchStopwordFilter
{
    /**
     * Stopwords bawaan dwibahasa Bahasa Indonesia dan Bahasa Inggris.
     *
     * @var array<string, bool>
     */
    private array $customStopwords = [];

    public function __construct(array $additionalStopwords = [])
    {
        foreach ($additionalStopwords as $word) {
            $this->customStopwords[mb_strtolower(trim($word))] = true;
        }
    }

    /**
     * Memeriksa apakah suatu kata termasuk dalam daftar kata henti.
     */
    public function isStopword(string $term): bool
    {
        $normalized = mb_strtolower(trim($term));

        if (mb_strlen($normalized) < 2) {
            return true;
        }

        if (isset($this->customStopwords[$normalized])) {
            return true;
        }

        return false;
    }

    /**
     * Menyaring array token kata untuk menghilangkan stopwords.
     *
     * @param  string[]  $tokens
     * @return string[]
     */
    public function filterTokens(array $tokens): array
    {
        return array_values(array_filter($tokens, fn ($t) => ! $this->isStopword($t)));
    }
}
