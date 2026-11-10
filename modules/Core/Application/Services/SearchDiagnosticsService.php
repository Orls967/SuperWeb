<?php

declare(strict_types=1);

namespace Modules\Core\Application\Services;

use Modules\Core\Application\Actions\Search\SearchHealthScorer;
use Modules\Core\Application\Actions\Search\SearchIndexQualityAuditor;

/**
 * Service diagnostik komprehensif sistem pencarian enterprise.
 */
class SearchDiagnosticsService
{
    public function __construct(
        protected SearchHealthScorer $healthScorer,
        protected SearchIndexQualityAuditor $qualityAuditor,
    ) {}

    /**
     * Menjalankan audit menyeluruh dan menyusun laporan diagnostik.
     *
     * @return array{
     *     timestamp: string,
     *     health: array<string, mixed>,
     *     quality: array<string, mixed>,
     *     action_items: string[]
     * }
     */
    public function runDiagnostics(): array
    {
        $health = $this->healthScorer->calculateHealth();
        $quality = $this->qualityAuditor->scanQuality();

        $actionItems = [];
        if ($health['dimensions']['latency_score'] < 70.0) {
            $actionItems[] = 'Latensi kueri rata-rata tinggi. Pertimbangkan untuk menjalankan komparasi dan vacuum inverted index.';
        }
        if ($health['dimensions']['zero_result_score'] < 70.0) {
            $actionItems[] = 'Tingkat kueri tanpa hasil di atas ambang batas wajar. Tinjau log kueri 0-results dan tambahkan padanan sinonim.';
        }
        if ($quality['anomalies_found'] > 0) {
            $actionItems[] = sprintf('Ditemukan %d anomali konten pada dokumen terindeks. Jalankan sinkronisasi data dari modul sumber.', $quality['anomalies_found']);
        }
        if (empty($actionItems)) {
            $actionItems[] = 'Sistem beroperasi optimal. Semua parameter kesehatan berada dalam koridor hijau.';
        }

        return [
            'timestamp' => now()->toIso8601String(),
            'health' => $health,
            'quality' => $quality,
            'action_items' => $actionItems,
        ];
    }
}
