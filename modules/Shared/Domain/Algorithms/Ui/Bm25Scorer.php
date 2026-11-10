<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Ui;

/**
 * Algoritma Bersertifikat A4: Okapi BM25 Information Retrieval Scoring.
 * Menghitung bobot relevansi dokumen terhadap kueri pencarian berdasarkan frekuensi term,
 * ukuran dokumen, dan Inverse Document Frequency (IDF) dengan verifikasi hash deterministik.
 */
class Bm25Scorer
{
    private float $k1;

    private float $b;

    public function __construct(float $k1 = 1.2, float $b = 0.75)
    {
        $this->k1 = $k1;
        $this->b = $b;
    }

    /**
     * Menghitung nilai Inverse Document Frequency (IDF).
     */
    public function calculateIdf(int $totalDocuments, int $docFrequency): float
    {
        if ($totalDocuments <= 0 || $docFrequency <= 0) {
            return 0.0;
        }

        $numerator = $totalDocuments - $docFrequency + 0.5;
        $denominator = $docFrequency + 0.5;

        return log(($numerator / $denominator) + 1.0);
    }

    /**
     * Menghitung skor BM25 untuk sebuah term pada dokumen tertentu.
     */
    public function scoreTerm(
        int $termFrequency,
        int $docLength,
        float $avgDocLength,
        float $idf,
        float $fieldWeight = 1.0
    ): float {
        if ($termFrequency <= 0 || $avgDocLength <= 0.0) {
            return 0.0;
        }

        $effectiveTf = (float) $termFrequency * $fieldWeight;
        $lengthNorm = 1.0 - $this->b + ($this->b * ($docLength / $avgDocLength));
        $denominator = $effectiveTf + ($this->k1 * $lengthNorm);

        if ($denominator <= 0.0) {
            return 0.0;
        }

        $numerator = $effectiveTf * ($this->k1 + 1.0);

        return $idf * ($numerator / $denominator);
    }

    /**
     * Menghitung total skor relevansi dokumen terhadap sekumpulan term kueri dan menerbitkan sertifikat verifikasi.
     *
     * @param  array<string, int>  $queryTerms  [term => term_frequency_in_query]
     * @param  array<string, int>  $docTerms  [term => term_frequency_in_doc]
     * @param  array<string, float>  $idfs  [term => idf_value]
     * @param  array<string, float>  $fieldWeights  [term => weight]
     * @return array{score: float, details: array<string, float>, certificate: array<string, mixed>}
     */
    public function compute(
        string $documentId,
        array $queryTerms,
        array $docTerms,
        array $idfs,
        int $docLength,
        float $avgDocLength,
        array $fieldWeights = []
    ): array {
        $totalScore = 0.0;
        $details = [];

        foreach ($queryTerms as $term => $qFreq) {
            $dFreq = $docTerms[$term] ?? 0;
            $idf = $idfs[$term] ?? 0.0;
            $weight = $fieldWeights[$term] ?? 1.0;

            $termScore = $this->scoreTerm($dFreq, $docLength, $avgDocLength, $idf, $weight);
            $details[$term] = round($termScore, 6);
            $totalScore += $termScore;
        }

        $finalScore = round($totalScore, 6);

        $payload = [
            'document_id' => $documentId,
            'k1' => $this->k1,
            'b' => $this->b,
            'doc_length' => $docLength,
            'avg_doc_length' => round($avgDocLength, 4),
            'total_score' => $finalScore,
            'details' => $details,
        ];

        $hash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));

        return [
            'score' => $finalScore,
            'details' => $details,
            'certificate' => [
                'algorithm' => 'BM25-Okapi-V1',
                'hash' => $hash,
                'payload' => $payload,
                'verified' => true,
            ],
        ];
    }
}
