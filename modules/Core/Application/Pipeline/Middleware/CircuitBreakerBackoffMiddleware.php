<?php

declare(strict_types=1);

namespace Modules\Core\Application\Pipeline\Middleware;

use Carbon\Carbon;
use Closure;
use Modules\Core\Application\Pipeline\SpineMiddlewareInterface;
use Modules\Core\Domain\Models\SimEventSpine;
use RuntimeException;

/**
 * Middleware Circuit Breaker Deterministik
 *
 * Menghentikan pemrosesan sementara jika rasio kegagalan konsumen melampaui batas ambang (5 kegagalan berturut-turut),
 * mencegah banjir exception (thundering herd & cascade failure).
 */
class CircuitBreakerBackoffMiddleware implements SpineMiddlewareInterface
{
    public const FAILURE_THRESHOLD = 5;

    public const COOLDOWN_SECONDS = 60;

    /**
     * @var array<string, array{consecutive_failures: int, tripped_until: Carbon|null}>
     */
    protected static array $circuits = [];

    public function handle(SimEventSpine $event, string $consumerGroup, Closure $next): mixed
    {
        $circuit = self::$circuits[$consumerGroup] ?? [
            'consecutive_failures' => 0,
            'tripped_until' => null,
        ];

        // Periksa apakah circuit sedang terbuka (tripped)
        if ($circuit['tripped_until'] !== null && Carbon::now()->lt($circuit['tripped_until'])) {
            throw new RuntimeException("Circuit breaker terbuka untuk grup [{$consumerGroup}] hingga {$circuit['tripped_until']->toIso8601String()}.");
        }

        try {
            $result = $next($event, $consumerGroup);

            // Berhasil: reset kegagalan
            self::$circuits[$consumerGroup] = [
                'consecutive_failures' => 0,
                'tripped_until' => null,
            ];

            return $result;
        } catch (\Throwable $e) {
            $circuit['consecutive_failures']++;

            if ($circuit['consecutive_failures'] >= self::FAILURE_THRESHOLD) {
                $circuit['tripped_until'] = Carbon::now()->addSeconds(self::COOLDOWN_SECONDS);
            }

            self::$circuits[$consumerGroup] = $circuit;

            throw $e;
        }
    }

    public static function resetCircuit(string $consumerGroup): void
    {
        unset(self::$circuits[$consumerGroup]);
    }

    public static function isTripped(string $consumerGroup): bool
    {
        $circuit = self::$circuits[$consumerGroup] ?? null;
        if (! $circuit || ! $circuit['tripped_until']) {
            return false;
        }

        return Carbon::now()->lt($circuit['tripped_until']);
    }
}
