<?php

declare(strict_types=1);

namespace Modules\Core\Console\Commands;

use Illuminate\Console\Command;
use Modules\Core\Application\Partitioning\PartitionHealthMonitor;

/**
 * Perintah Status dan Kesehatan Partisi Virtual Event Spine (SpinePartitionStatusCommand)
 *
 * Menampilkan ringkasan statistik pembebanan partisi virtual, skew coefficient,
 * dan deteksi partisi terpanas/terdingin untuk panduan tuning skalabilitas.
 */
class SpinePartitionStatusCommand extends Command
{
    protected $signature = 'spine:partition-status
                            {--partitions=16 : Jumlah partisi virtual yang dianalisis}
                            {--json : Tampilkan data dalam format JSON murni}';

    protected $description = 'Menampilkan metrik distribusi beban dan kesehatan partisi virtual event spine';

    public function handle(PartitionHealthMonitor $monitor): int
    {
        $partitions = max(2, (int) $this->option('partitions'));
        $asJson = (bool) $this->option('json');

        $this->info("Menganalisis distribusi {$partitions} partisi virtual...");

        $health = $monitor->inspect($partitions);

        if ($asJson) {
            $this->line((string) json_encode($health, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->table(
            ['Parameter Metrik', 'Nilai'],
            [
                ['Jumlah Partisi Virtual', $health['total_partitions']],
                ['Total Sampel Event', number_format($health['total_events'])],
                ['Rata-rata Muatan Per Partisi', $health['average_load']],
                ['Standar Deviasi (σ)', $health['standard_deviation']],
                ['Koefisien Variasi (CV)', $health['coefficient_of_variation']],
                ['Partisi Terpanas (Hottest)', "#{$health['hottest_partition']['partition_index']} ({$health['hottest_partition']['event_count']} event, {$health['hottest_partition']['load_percentage']}%)"],
                ['Partisi Terdingin (Coldest)', "#{$health['coldest_partition']['partition_index']} ({$health['coldest_partition']['event_count']} event, {$health['coldest_partition']['load_percentage']}%)"],
                ['Status Beban (Skewed)', $health['is_skewed'] ? 'YA (BEBAN TIMPANG)' : 'TIDAK (DISTRIBUSI SERAGAM)'],
                ['Rekomendasi Rebalancing', $health['rebalance_recommended'] ? 'DIPERLUKAN' : 'TIDAK DIPERLUKAN'],
            ]
        );

        $this->newLine();
        $this->info('Rincian Distribusi Partisi:');

        $rows = [];
        foreach ($health['partition_distribution'] as $p) {
            $rows[] = [
                "Partisi #{$p['partition_index']}",
                number_format($p['event_count']),
                "{$p['percentage']}%",
                $p['status'],
            ];
        }

        $this->table(['Partisi', 'Jumlah Event', 'Persentase', 'Status'], $rows);

        if ($health['is_skewed']) {
            $this->warn('PERINGATAN: Terdeteksi ketimpangan distribusi muatan partisi. Pertimbangkan untuk merevisi partition key.');
        } else {
            $this->info('Distribusi partisi berada dalam ambang toleransi sehat.');
        }

        return self::SUCCESS;
    }
}
