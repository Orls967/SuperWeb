<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Ui;

/**
 * Algoritma Bersertifikat A5: Trigram Jaccard Fuzzy Matching & Spelling Suggestion.
 * Menghitung koefisien kesamaan Jaccard dari himpunan n-gram untuk toleransi saltik
 * dan saran ejaan kata kunci pencarian tanpa mesin pencari eksternal.
 */
class TrigramJaccardMatcher
{
    /**
     * Memecah string menjadi himpunan n-gram (bigram dan trigram dengan pembatas boundary).
     *
     * @return array<string, int> Himpunan n-gram unik sebagai keys
     */
    public function extractTrigrams(string $text): array
    {
        $normalized = mb_strtolower(trim($text));
        $len = mb_strlen($normalized);

        if ($len === 0) {
            return [];
        }

        $padded = '^'.$normalized.'$';
        $pLen = mb_strlen($padded);
        $ngrams = [];

        // Trigram
        for ($i = 0; $i <= $pLen - 3; $i++) {
            $tri = mb_substr($padded, $i, 3);
            $ngrams[$tri] = 1;
        }

        // Bigram untuk ketahanan saltik kata pendek
        for ($i = 0; $i <= $pLen - 2; $i++) {
            $bi = mb_substr($padded, $i, 2);
            $ngrams[$bi] = 1;
        }

        return $ngrams;
    }

    /**
     * Menghitung koefisien Jaccard antara dua himpunan n-gram.
     *
     * @param  array<string, int>  $setA
     * @param  array<string, int>  $setB
     */
    public function calculateJaccardSimilarity(array $setA, array $setB): float
    {
        if (empty($setA) && empty($setB)) {
            return 1.0;
        }

        if (empty($setA) || empty($setB)) {
            return 0.0;
        }

        $intersection = 0;
        foreach ($setA as $key => $_) {
            if (isset($setB[$key])) {
                $intersection++;
            }
        }

        $union = count($setA) + count($setB) - $intersection;

        if ($union <= 0) {
            return 0.0;
        }

        return round($intersection / $union, 6);
    }

    /**
     * Menemukan kandidat kata terbaik dari daftar kamus/corpus berdasarkan kemiripan tertinggi di atas ambang batas.
     *
     * @param  string[]  $corpus
     * @return array{match: string, similarity: float, certificate: array<string, mixed>}|null
     */
    public function findBestMatch(string $queryWord, array $corpus, float $threshold = 0.35): ?array
    {
        $queryTrigrams = $this->extractTrigrams($queryWord);
        $bestMatch = null;
        $highestSim = -1.0;

        foreach ($corpus as $candidate) {
            $candidateTrigrams = $this->extractTrigrams($candidate);
            $sim = $this->calculateJaccardSimilarity($queryTrigrams, $candidateTrigrams);

            if ($sim > $highestSim && $sim >= $threshold) {
                $highestSim = $sim;
                $bestMatch = $candidate;
            }
        }

        if ($bestMatch === null) {
            return null;
        }

        $payload = [
            'query_word' => $queryWord,
            'best_match' => $bestMatch,
            'similarity' => $highestSim,
            'threshold' => $threshold,
        ];

        $hash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));

        return [
            'match' => $bestMatch,
            'similarity' => $highestSim,
            'certificate' => [
                'algorithm' => 'Trigram-Jaccard-V1',
                'hash' => $hash,
                'payload' => $payload,
                'verified' => true,
            ],
        ];
    }
}
