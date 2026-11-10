<?php

declare(strict_types=1);

namespace Modules\Core\Application\Pipeline;

use Closure;
use Modules\Core\Domain\Models\SimEventSpine;

/**
 * Middleware Pengumpul Metrik Real-Time (MetricsCollectorMiddleware)
 *
 * Mengumpulkan data telemetry performa eksekusi handler event:
 * - Durasi waktu eksekusi dalam milidetik
 * - Ukuran payload dalam byte
 * - Rasio keberhasilan vs kegagalan per topik dan per grup konsumen
 */
class MetricsCollectorMiddleware implements SpineMiddlewareInterface
{
    /**
     * @var array<string, array{
     *     total_dispatched: int,
     *     total_errors: int,
     *     total_duration_ms: float,
     *     max_duration_ms: float,
     *     total_bytes: int
     * }>
     */
    protected static array $metrics = [];

    /**
     * Menjalankan interceptor pengumpul metrik eksekusi.
     *
     * @param  Closure(SimEventSpine, string): mixed  $next
     */
    public function handle(SimEventSpine $event, string $consumerGroup, Closure $next): mixed
    {
        $key = "{$consumerGroup}:{$event->topic}";
        $this->ensureInitialized($key);

        $payloadBytes = strlen((string) json_encode($event->payload));
        self::$metrics[$key]['total_dispatched']++;
        self::$metrics[$key]['total_bytes'] += $payloadBytes;

        $startTime = microtime(true);

        try {
            $result = $next($event, $consumerGroup);

            $duration = (microtime(true) - $startTime) * 1000.0;
            self::$metrics[$key]['total_duration_ms'] += $duration;
            if ($duration > self::$metrics[$key]['max_duration_ms']) {
                self::$metrics[$key]['max_duration_ms'] = $duration;
            }

            return $result;
        } catch (\Throwable $e) {
            self::$metrics[$key]['total_errors']++;
            $duration = (microtime(true) - $startTime) * 1000.0;
            self::$metrics[$key]['total_duration_ms'] += $duration;

            throw $e;
        }
    }

    /**
     * Mengambil snapshot seluruh metrik yang terkumpul.
     *
     * @return array<string, array{
     *     total_dispatched: int,
     *     total_errors: int,
     *     total_duration_ms: float,
     *     average_duration_ms: float,
     *     max_duration_ms: float,
     *     total_bytes: int,
     *     error_rate_percent: float
     * }>
     */
    public static function getMetricsSnapshot(): array
    {
        $snapshot = [];

        foreach (self::$metrics as $key => $data) {
            $dispatched = max(1, $data['total_dispatched']);
            $snapshot[$key] = [
                'total_dispatched' => $data['total_dispatched'],
                'total_errors' => $data['total_errors'],
                'total_duration_ms' => round($data['total_duration_ms'], 2),
                'average_duration_ms' => round($data['total_duration_ms'] / $dispatched, 2),
                'max_duration_ms' => round($data['max_duration_ms'], 2),
                'total_bytes' => $data['total_bytes'],
                'error_rate_percent' => round(($data['total_errors'] / $dispatched) * 100, 2),
            ];
        }

        return $snapshot;
    }

    /**
     * Mereset seluruh metrik yang tersimpan.
     */
    public static function resetMetrics(): void
    {
        self::$metrics = [];
    }

    protected function ensureInitialized(string $key): void
    {
        if (! isset(self::$metrics[$key])) {
            self::$metrics[$key] = [
                'total_dispatched' => 0,
                'total_errors' => 0,
                'total_duration_ms' => 0.0,
                'max_duration_ms' => 0.0,
                'total_bytes' => 0,
            ];
        }
    }
}

