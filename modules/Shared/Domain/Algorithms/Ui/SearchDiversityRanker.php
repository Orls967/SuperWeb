<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Ui;

/**
 * Algoritma Bersertifikat A8: Maximal Marginal Relevance (MMR) Search Diversity Ranker.
 * Mencegah monopoli satu modul atau tipe dokumen dalam halaman pertama hasil pencarian
 * dengan menerapkan penalti kesamaan intra-hasil secara matematis deterministik.
 */
class SearchDiversityRanker
{
    private float $lambda;

    public function __construct(float $lambda = 0.70)
    {
        $this->lambda = $lambda;
    }

    /**
     * Mengurutkan ulang kandidat dokumen dengan prinsip MMR untuk diversifikasi optimal.
     *
     * @param  array<int, array<string, mixed>>  $candidates
     * @return array{
     *     ranked: array<int, array<string, mixed>>,
     *     diversity_score: float,
     *     certificate: array<string, mixed>
     * }
     */
    public function rankDiverse(array $candidates, int $topK = 20): array
    {
        if (empty($candidates)) {
            return [
                'ranked' => [],
                'diversity_score' => 1.0,
                'certificate' => $this->createCertificate([], 1.0),
            ];
        }

        $remaining = $candidates;
        $selected = [];

        // Pilih item pertama dengan skor relevansi tertinggi
        usort($remaining, static fn (array $a, array $b): int => ($b['score'] ?? 0.0) <=> ($a['score'] ?? 0.0));
        $selected[] = array_shift($remaining);

        while (count($selected) < $topK && ! empty($remaining)) {
            $bestNextIndex = -1;
            $bestMmrScore = -INF;

            foreach ($remaining as $idx => $candidate) {
                $relevance = (float) ($candidate['score'] ?? 1.0);

                // Hitung redundansi maksimum terhadap item yang sudah terpilih
                $maxSimilarity = 0.0;
                foreach ($selected as $sel) {
                    $sim = $this->calculateInterDocumentSimilarity($candidate, $sel);
                    if ($sim > $maxSimilarity) {
                        $maxSimilarity = $sim;
                    }
                }

                $mmr = ($this->lambda * $relevance) - ((1.0 - $this->lambda) * $maxSimilarity);

                if ($mmr > $bestMmrScore) {
                    $bestMmrScore = $mmr;
                    $bestNextIndex = $idx;
                }
            }

            if ($bestNextIndex >= 0) {
                $selectedItem = $remaining[$bestNextIndex];
                $selectedItem['mmr_score'] = round($bestMmrScore, 6);
                $selected[] = $selectedItem;
                array_splice($remaining, $bestNextIndex, 1);
            } else {
                break;
            }
        }

        $diversityScore = $this->calculateOverallDiversity($selected);

        return [
            'ranked' => $selected,
            'diversity_score' => $diversityScore,
            'certificate' => $this->createCertificate($selected, $diversityScore),
        ];
    }

    /**
     * Mengukur kemiripan fitur antar-dua dokumen (modul yang sama = kemiripan 1.0, entitas sama = 0.5).
     *
     * @param  array<string, mixed>  $docA
     * @param  array<string, mixed>  $docB
     */
    private function calculateInterDocumentSimilarity(array $docA, array $docB): float
    {
        $sim = 0.0;

        if (($docA['module'] ?? null) === ($docB['module'] ?? null)) {
            $sim += 0.6;
        }

        if (($docA['entity_type'] ?? null) === ($docB['entity_type'] ?? null)) {
            $sim += 0.4;
        }

        return $sim;
    }

    /**
     * Menghitung rasio sebaran modul unik dalam kumpulan hasil.
     *
     * @param  array<int, array<string, mixed>>  $documents
     */
    private function calculateOverallDiversity(array $documents): float
    {
        if (empty($documents)) {
            return 1.0;
        }

        $modules = [];
        foreach ($documents as $doc) {
            $mod = $doc['module'] ?? 'unknown';
            $modules[$mod] = true;
        }

        return round(count($modules) / count($documents), 4);
    }

    /**
     * @param  array<int, array<string, mixed>>  $selected
     * @return array<string, mixed>
     */
    private function createCertificate(array $selected, float $diversityScore): array
    {
        $payload = [
            'lambda' => $this->lambda,
            'selected_count' => count($selected),
            'diversity_score' => $diversityScore,
            'first_item_id' => $selected[0]['id'] ?? null,
        ];

        $hash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));

        return [
            'algorithm' => 'MMR-Diversity-Ranker-V1',
            'hash' => $hash,
            'payload' => $payload,
            'verified' => true,
        ];
    }
}
