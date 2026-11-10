<?php

declare(strict_types=1);

namespace Modules\Core\Application\Routing;

use Modules\Core\Application\Services\SpineLagMonitorService;

/**
 * Penasihat Penskalaan Dinamis Grup Konsumen (DynamicConsumerGroupScaler)
 *
 * Menganalisis metrik lag, laju inflow event, dan waktu pemrosesan konsumen
 * untuk merekomendasikan penambahan atau pengurangan jumlah proses worker (*elastic autoscaling*).
 */
class DynamicConsumerGroupScaler
{
    public function __construct(
        protected SpineLagMonitorService $lagMonitorService
    ) {}

    /**
     * Menganalisis grup konsumen dan menghasilkan rekomendasi jumlah worker.
     *
     * @param  array<string, mixed>  $currentWorkers
     * @return array<string, array{
     *     consumer_group: string,
     *     current_workers: int,
     *     current_lag: int,
     *     recommended_workers: int,
     *     scaling_action: 'SCALE_UP'|'SCALE_DOWN'|'MAINTAIN',
     *     reason: string
     * }>
     */
    public function evaluateScaling(array $currentWorkers = []): array
    {
        $groupMetrics = $this->lagMonitorService->getConsumerGroupMetrics();

        $recommendations = [];

        foreach ($groupMetrics as $metrics) {
            $group = (string) ($metrics['consumer_group'] ?? $metrics['group'] ?? 'unknown');
            $lag = (int) ($metrics['current_lag'] ?? $metrics['lag'] ?? 0);
            $workers = max(1, (int) ($currentWorkers[$group] ?? 1));

            $recommendedWorkers = $workers;
            $action = 'MAINTAIN';
            $reason = 'Lag berada dalam ambang normal.';

            if ($lag > 5000) {
                // Lonjakan besar: scale up agresif (maksimal 8 worker per node)
                $recommendedWorkers = min(8, $workers + 3);
                $action = 'SCALE_UP';
                $reason = "Lag kritis ({$lag} pesan) melampaui ambang batas 5000. Memerlukan penambahan worker segera.";
            } elseif ($lag > 1000) {
                // Beban sedang: scale up proporsional
                $recommendedWorkers = min(6, $workers + 1);
                $action = 'SCALE_UP';
                $reason = "Lag meningkat ({$lag} pesan). Disarankan menambah 1 worker pendukung.";
            } elseif ($lag < 50 && $workers > 1) {
                // Antrean hampir kosong dan worker berlebih: scale down
                $recommendedWorkers = max(1, $workers - 1);
                $action = 'SCALE_DOWN';
                $reason = "Lag rendah ({$lag} pesan). Konsumsi efisien dapat ditangani dengan {$recommendedWorkers} worker.";
            }

            $recommendations[$group] = [
                'consumer_group' => $group,
                'current_workers' => $workers,
                'current_lag' => $lag,
                'recommended_workers' => $recommendedWorkers,
                'scaling_action' => $action,
                'reason' => $reason,
            ];
        }

        return $recommendations;
    }
}

