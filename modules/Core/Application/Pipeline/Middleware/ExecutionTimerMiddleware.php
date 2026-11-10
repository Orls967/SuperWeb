<?php

declare(strict_types=1);

namespace Modules\Core\Application\Pipeline\Middleware;

use Closure;
use Modules\Core\Application\Pipeline\SpineMiddlewareInterface;
use Modules\Core\Domain\Models\SimEventSpine;

/**
 * Middleware Pengukur Waktu Eksekusi (Timing Metrics)
 *
 * Mengukur latensi pemrosesan event oleh handler konsumen
 * dan mencatat durasi eksekusi dalam milidetik.
 */
class ExecutionTimerMiddleware implements SpineMiddlewareInterface
{
    /**
     * @var array<string, array<string, int>>
     */
    protected static array $lastExecutionDurations = [];

    public function handle(SimEventSpine $event, string $consumerGroup, Closure $next): mixed
    {
        $start = microtime(true);

        try {
            return $next($event, $consumerGroup);
        } finally {
            $durationMs = (int) round((microtime(true) - $start) * 1000);
            self::$lastExecutionDurations[$consumerGroup][$event->event_id] = $durationMs;
        }
    }

    public static function getLastDurationMs(string $consumerGroup, string $eventId): int
    {
        return self::$lastExecutionDurations[$consumerGroup][$eventId] ?? 0;
    }

    public static function clearTimings(): void
    {
        self::$lastExecutionDurations = [];
    }
}
