<?php

declare(strict_types=1);

namespace Modules\Core\Application\Actions\Search;

use Modules\Core\Domain\Models\CoreSearchIndex;

/**
 * Auditor kualitas konten dokumen terindeks (Index Quality & Anomaly Scanner).
 */
class SearchIndexQualityAuditor
{
    /**
     * Memindai seluruh dokumen indeks dan mendeteksi anomali kualitas data.
     *
     * @return array{
     *     total_scanned: int,
     *     anomalies_found: int,
     *     empty_content_count: int,
     *     short_title_count: int,
     *     missing_url_count: int,
     *     anomalies: array<int, array{id: string, module: string, issue: string}>
     * }
     */
    public function scanQuality(): array
    {
        $indices = CoreSearchIndex::all();
        $emptyContentCount = 0;
        $shortTitleCount = 0;
        $missingUrlCount = 0;
        $anomalies = [];

        foreach ($indices as $index) {
            $hasIssue = false;
            $issueMsg = [];

            // 1. Cek isi konten
            if (mb_strlen(trim((string) $index->content)) < 5) {
                $emptyContentCount++;
                $issueMsg[] = 'Konten dokumen terlalu pendek atau kosong (< 5 karakter).';
                $hasIssue = true;
            }

            // 2. Cek panjang judul
            if (mb_strlen(trim((string) $index->title)) < 3) {
                $shortTitleCount++;
                $issueMsg[] = 'Judul dokumen terlalu pendek (< 3 karakter).';
                $hasIssue = true;
            }

            // 3. Cek tautan URL tujuan
            $url = $index->metadata['url'] ?? null;
            if (empty($url) || $url === '#') {
                $missingUrlCount++;
                $issueMsg[] = 'Tautan target navigasi (metadata.url) belum didefinisikan.';
                $hasIssue = true;
            }

            if ($hasIssue) {
                $anomalies[] = [
                    'id' => (string) $index->id,
                    'module' => $index->module,
                    'issue' => implode(' | ', $issueMsg),
                ];
            }
        }

        return [
            'total_scanned' => $indices->count(),
            'anomalies_found' => count($anomalies),
            'empty_content_count' => $emptyContentCount,
            'short_title_count' => $shortTitleCount,
            'missing_url_count' => $missingUrlCount,
            'anomalies' => array_slice($anomalies, 0, 50),
        ];
    }
}
