<?php

declare(strict_types=1);

namespace Modules\Core\Console\Commands;

use Illuminate\Console\Command;
use Modules\Core\Application\Routing\DynamicConsumerGroupScaler;

/**
 * Perintah Rekomendasi Penskalaan Worker Konsumen (SpineScaleRecommendCommand)
 *
 * Menghitung kebutuhan worker per grup konsumen berdasarkan metrik lag real-time.
 */
class SpineScaleRecommendCommand extends Command
{
    protected $signature = 'spine:scale-recommend
                            {--json : Tampilkan keluaran dalam format JSON murni}';

    protected $description = 'Menganalisis lag antrean dan merekomendasikan penskalaan worker per consumer group';

    public function handle(DynamicConsumerGroupScaler $scaler): int
    {
        $asJson = (bool) $this->option('json');

        $this->info('Mengevaluasi kebutuhan skala worker konsumen...');

        $recommendations = $scaler->evaluateScaling();

        if ($asJson) {
            $this->line((string) json_encode($recommendations, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $rows = [];
        foreach ($recommendations as $r) {
            $rows[] = [
                $r['consumer_group'],
                $r['current_workers'],
                number_format($r['current_lag']),
                $r['recommended_workers'],
                $r['scaling_action'],
                $r['reason'],
            ];
        }

        $this->table(
            ['Consumer Group', 'Worker Saat Ini', 'Lag Antrean', 'Worker Rekomendasi', 'Aksi Skala', 'Alasan / Analisis'],
            $rows
        );

        return self::SUCCESS;
    }
}

