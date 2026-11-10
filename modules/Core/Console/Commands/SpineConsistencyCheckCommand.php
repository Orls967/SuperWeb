<?php

declare(strict_types=1);

namespace Modules\Core\Console\Commands;

use Illuminate\Console\Command;
use Modules\Core\Application\Services\Audit\CrossModuleEventConsistencyChecker;

/**
 * Perintah Audit Konsistensi Aliran Event Antar-Modul (SpineConsistencyCheckCommand)
 *
 * Menjalankan rekonsiliasi dual-source antara tabel outbox/spine dan consumer inbox
 * untuk mengaudit keutuhan pengiriman pesan ke seluruh lini bisnis.
 */
class SpineConsistencyCheckCommand extends Command
{
    protected $signature = 'spine:consistency-check
                            {--topic=* : Filter topik event spesifik untuk diaudit}
                            {--json : Tampilkan keluaran dalam format JSON murni}';

    protected $description = 'Menjalankan audit konsistensi aliran event spine vs inbox lintas modul';

    public function handle(CrossModuleEventConsistencyChecker $checker): int
    {
        $topics = (array) $this->option('topic');
        $asJson = (bool) $this->option('json');

        $this->info('Memulai audit konsistensi aliran event spine lintas modul...');

        $report = $checker->audit($topics);

        if ($asJson) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return $report['is_consistent'] ? self::SUCCESS : self::FAILURE;
        }

        $this->table(
            ['Metrik', 'Nilai'],
            [
                ['Waktu Audit', $report['timestamp']],
                ['Total Event Spine', number_format($report['total_spine_events'])],
                ['Total Rekaman Inbox', number_format($report['total_inbox_records'])],
                ['Total Deliveries', number_format($report['total_deliveries'])],
                ['Tingkat Rekonsiliasi (%)', $report['reconciliation_rate_percent'].'%'],
                ['Event Tanpa Konsumen (Orphan)', number_format($report['orphan_event_count'])],
                ['Status Konsistensi', $report['is_consistent'] ? 'KONSISTEN' : 'INKONSISTEN'],
            ]
        );

        if (! empty($report['topic_breakdown'])) {
            $this->newLine();
            $this->info('Distribusi Pengiriman Per Topik:');

            $rows = [];
            foreach ($report['topic_breakdown'] as $topic => $stats) {
                $rows[] = [
                    $topic,
                    number_format($stats['published']),
                    number_format($stats['delivered']),
                    number_format($stats['dead_lettered']),
                    number_format($stats['pending']),
                    $stats['loss_rate_percent'].'%',
                ];
            }

            $this->table(
                ['Topik', 'Diterbitkan', 'Terkirim', 'DLQ', 'Tertunda', 'Loss %'],
                $rows
            );
        }

        if (! empty($report['anomalies'])) {
            $this->newLine();
            $this->error('Ditemukan '.count($report['anomalies']).' Anomali:');
            foreach ($report['anomalies'] as $idx => $anomaly) {
                $this->warn('  ['.($idx + 1).'] '.$anomaly);
            }
        }

        if (! $report['is_consistent']) {
            $this->error('Audit konsistensi spine GAGAL: Terdeteksi ketidakcocokan data.');

            return self::FAILURE;
        }

        $this->info('Audit konsistensi spine BERHASIL: Seluruh data aliran konsisten.');

        return self::SUCCESS;
    }
}
