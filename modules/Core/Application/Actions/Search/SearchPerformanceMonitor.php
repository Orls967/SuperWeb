<?php

declare(strict_types=1);

namespace Modules\Core\Application\Actions\Search;

use Modules\Core\Domain\Models\CoreSearchQueryLog;

/**
 * Service pemantau telemetri performa penelusuran (Search Performance Monitor & Alerting).
 */
class SearchPerformanceMonitor
{
    /**
     * Memeriksa apakah terjadi lonjakan latensi (latency spike) pada kueri terbaru.
     *
     * @return array{has_spike: bool, p95_latency_ms: float, slow_queries_count: int, alert_message: string|null}
     */
    public function detectLatencySpikes(int $lookbackMinutes = 30, float $p95ThresholdMs = 150.0): array
    {
        $since = now()->subMinutes($lookbackMinutes);
        $recentLogs = CoreSearchQueryLog::where('created_at', '>=', $since)->pluck('execution_time_ms')->toArray();

        if (empty($recentLogs)) {
            return [
                'has_spike' => false,
                'p95_latency_ms' => 0.0,
                'slow_queries_count' => 0,
                'alert_message' => null,
            ];
        }

        sort($recentLogs);
        $count = count($recentLogs);
        $p95Index = (int) floor($count * 0.95);
        $p95Val = (float) $recentLogs[$p95Index];

        $slowCount = 0;
        foreach ($recentLogs as $lat) {
            if ($lat > $p95ThresholdMs) {
                $slowCount++;
            }
        }

        $hasSpike = ($p95Val > $p95ThresholdMs);
        $alert = $hasSpike ? sprintf('Peringatan: Latensi pencarian P95 mencapai %sms (ambang batas: %sms). Ditemukan %d kueri lambat.', $p95Val, $p95ThresholdMs, $slowCount) : null;

        return [
            'has_spike' => $hasSpike,
            'p95_latency_ms' => $p95Val,
            'slow_queries_count' => $slowCount,
            'alert_message' => $alert,
        ];
    }
}
