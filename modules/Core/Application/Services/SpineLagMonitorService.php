<?php

declare(strict_types=1);

namespace Modules\Core\Application\Services;

use Illuminate\Support\Facades\DB;
use Modules\Core\Domain\Algorithms\Events\NearestRankLagCalculator;
use Modules\Core\Domain\Models\SimEventSpine;

/**
 * Service SpineLagMonitorService (DR8-5)
 *
 * Menghitung selisih lag konsumsi pesan per consumer group dan topic,
 * serta menghitung nilai persentil p95 lag menggunakan NearestRankLagCalculator.
 */
class SpineLagMonitorService
{
    public function __construct(
        protected NearestRankLagCalculator $lagCalculator
    ) {}

    /**
     * Mengambil metrik lag untuk seluruh grup konsumen yang terdaftar.
     *
     * @return array<int, array{consumer_group: string, topic: string, last_offset: int, max_offset: int, current_lag: int, updated_at: string|null}>
     */
    public function getConsumerGroupMetrics(): array
    {
        $consumers = DB::table('sim_event_consumers')->get();
        $metrics = [];

        foreach ($consumers as $consumer) {
            $topic = (string) $consumer->topic;
            $group = (string) $consumer->consumer_group;
            $lastOffset = (int) $consumer->last_processed_offset;

            // Cari offset tertinggi pada sim_event_spine untuk topic ini
            $maxQuery = SimEventSpine::query();
            if ($topic !== '*') {
                if (str_ends_with($topic, '.*')) {
                    $prefix = substr($topic, 0, -2);
                    $maxQuery->where(function ($q) use ($prefix) {
                        $q->where('topic', $prefix)->orWhere('topic', 'like', "{$prefix}.%");
                    });
                } else {
                    $maxQuery->where('topic', $topic);
                }
            }

            $maxOffset = (int) ($maxQuery->max('id') ?? 0);
            $currentLag = max(0, $maxOffset - $lastOffset);

            $metrics[] = [
                'consumer_group' => $group,
                'topic' => $topic,
                'last_offset' => $lastOffset,
                'max_offset' => $maxOffset,
                'current_lag' => $currentLag,
                'updated_at' => $consumer->updated_at ? (string) $consumer->updated_at : null,
            ];
        }

        return $metrics;
    }

    /**
     * Menghitung nilai persentil P95 lag dari kumpulan grup konsumen aktif.
     */
    public function getOverallP95Lag(): int
    {
        $metrics = $this->getConsumerGroupMetrics();
        if (empty($metrics)) {
            return 0;
        }

        $lags = array_column($metrics, 'current_lag');

        return $this->lagCalculator->calculateP95($lags);
    }
}
