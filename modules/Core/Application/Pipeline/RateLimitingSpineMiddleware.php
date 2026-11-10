<?php

declare(strict_types=1);

namespace Modules\Core\Application\Pipeline;

use Closure;
use Modules\Core\Domain\Models\SimEventSpine;
use RuntimeException;

/**
 * Middleware Pembatas Laju Pesan (RateLimitingSpineMiddleware)
 *
 * Menerapkan algoritma token-bucket sederhana berbasis sliding window per consumer group
 * untuk mencegah beban berlebih (overload) pada sistem hilir tanpa menggunakan fungsi tidur (sleep).
 */
class RateLimitingSpineMiddleware implements SpineMiddlewareInterface
{
    /**
     * @var array<string, array{tokens: int, last_refill: int}>
     */
    protected static array $buckets = [];

    public function __construct(
        protected int $maxTokensPerMinute = 1200,
        protected int $refillIntervalSeconds = 60
    ) {}

    /**
     * Menjalankan interceptor rate limiting.
     *
     * @param  Closure(SimEventSpine, string): mixed  $next
     */
    public function handle(SimEventSpine $event, string $consumerGroup, Closure $next): mixed
    {
        $now = time();
        $this->refillTokens($consumerGroup, $now);

        if (self::$buckets[$consumerGroup]['tokens'] <= 0) {
            throw new RuntimeException(
                "Consumer group '{$consumerGroup}' telah melampaui batas laju pemrosesan ({$this->maxTokensPerMinute} pesan/menit). Harap coba kembali pada interval berikutnya."
            );
        }

        self::$buckets[$consumerGroup]['tokens']--;

        return $next($event, $consumerGroup);
    }

    /**
     * Mengisi ulang token secara berkala.
     */
    protected function refillTokens(string $consumerGroup, int $currentTime): void
    {
        if (! isset(self::$buckets[$consumerGroup])) {
            self::$buckets[$consumerGroup] = [
                'tokens' => $this->maxTokensPerMinute,
                'last_refill' => $currentTime,
            ];

            return;
        }

        $elapsed = $currentTime - self::$buckets[$consumerGroup]['last_refill'];
        if ($elapsed >= $this->refillIntervalSeconds) {
            self::$buckets[$consumerGroup]['tokens'] = $this->maxTokensPerMinute;
            self::$buckets[$consumerGroup]['last_refill'] = $currentTime;
        }
    }

    /**
     * Mereset seluruh bucket token (berguna untuk lingkungan pengujian).
     */
    public static function resetBuckets(): void
    {
        self::$buckets = [];
    }
}

