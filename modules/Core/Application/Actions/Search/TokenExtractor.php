<?php

declare(strict_types=1);

namespace Modules\Core\Application\Actions\Search;

/**
 * Service ekstraksi dan normalisasi token pencarian teks untuk inverted index.
 */
class TokenExtractor
{
    /**
     * Stopwords umum Bahasa Indonesia dan Bahasa Inggris yang tidak diskriminatif.
     */
    private const STOPWORDS = [
        'dan', 'atau', 'yang', 'di', 'ke', 'dari', 'untuk', 'pada', 'dengan', 'adalah',
        'ini', 'itu', 'juga', 'akan', 'bisa', 'ada', 'tidak', 'dalam', 'oleh',
        'the', 'and', 'or', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'is', 'are', 'a', 'an',
    ];

    /**
     * Mengekstrak token kata beserta frekuensinya dari sebuah teks.
     *
     * @return array<string, int> [token => frequency]
     */
    public function extractTokens(string $text): array
    {
        $normalized = mb_strtolower(trim($text));
        // Ganti karakter non-alfanumerik kecuali dash
        $cleaned = preg_replace('/[^\p{L}\p{N}\-]+/u', ' ', $normalized);

        if (empty($cleaned)) {
            return [];
        }

        $words = preg_split('/\s+/', (string) $cleaned, -1, PREG_SPLIT_NO_EMPTY);
        if (! $words) {
            return [];
        }

        $frequencies = [];
        $stopwordMap = array_flip(self::STOPWORDS);

        foreach ($words as $word) {
            $word = trim($word, '-');
            if (mb_strlen($word) < 2) {
                continue;
            }

            if (isset($stopwordMap[$word])) {
                continue;
            }

            $frequencies[$word] = ($frequencies[$word] ?? 0) + 1;
        }

        return $frequencies;
    }

    /**
     * Menghitung total kata unik atau panjang dokumen.
     */
    public function calculateDocumentLength(string $text): int
    {
        $tokens = $this->extractTokens($text);

        return array_sum($tokens);
    }
}
