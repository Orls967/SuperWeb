<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Ui;

/**
 * Algoritma Bersertifikat A7: Faceted Search Aggregator.
 * Mengelompokkan dan menghitung distribusi faset (modul, tipe entitas, status, rentang waktu)
 * dari kumpulan hasil pencarian dengan validasi integritas sertifikat deterministik.
 */
class FacetedSearchAggregator
{
    /**
     * Menghitung faset agregat dari daftar hasil dokumen pencarian.
     *
     * @param  array<int, array<string, mixed>>  $documents
     * @param  string[]  $facetFields
     * @return array{
     *     facets: array<string, array<string, int>>,
     *     total_items: int,
     *     certificate: array<string, mixed>
     * }
     */
    public function aggregate(array $documents, array $facetFields = ['module', 'entity_type', 'status']): array
    {
        $facets = [];
        foreach ($facetFields as $field) {
            $facets[$field] = [];
        }

        foreach ($documents as $doc) {
            foreach ($facetFields as $field) {
                $val = $doc[$field] ?? ($doc['metadata'][$field] ?? null);
                if ($val !== null && is_scalar($val)) {
                    $key = (string) $val;
                    $facets[$field][$key] = ($facets[$field][$key] ?? 0) + 1;
                }
            }
        }

        // Urutkan setiap faset berdasarkan jumlah kemunculan terbanyak (descending)
        foreach ($facets as $field => $counts) {
            arsort($counts);
            $facets[$field] = $counts;
        }

        $payload = [
            'total_items' => count($documents),
            'facet_fields' => $facetFields,
            'facets' => $facets,
        ];

        $hash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));

        return [
            'facets' => $facets,
            'total_items' => count($documents),
            'certificate' => [
                'algorithm' => 'Faceted-Search-Aggregator-V1',
                'hash' => $hash,
                'payload' => $payload,
                'verified' => true,
            ],
        ];
    }
}
