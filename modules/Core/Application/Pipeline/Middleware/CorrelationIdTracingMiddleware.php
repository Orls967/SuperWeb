<?php

declare(strict_types=1);

namespace Modules\Core\Application\Pipeline\Middleware;

use Closure;
use Illuminate\Support\Facades\Log;
use Modules\Core\Application\Pipeline\SpineMiddlewareInterface;
use Modules\Core\Domain\Models\SimEventSpine;

/**
 * Middleware Propagasi Correlation ID (Kepatuhan W8-P1)
 *
 * Menginspeksi payload dan atribut event untuk meneruskan correlation_id
 * ke dalam konteks logging terstruktur aplikasi.
 */
class CorrelationIdTracingMiddleware implements SpineMiddlewareInterface
{
    public function handle(SimEventSpine $event, string $consumerGroup, Closure $next): mixed
    {
        $payload = (array) $event->payload;
        $correlationId = $payload['correlation_id']
            ?? $payload['headers']['correlation_id']
            ?? "corr-{$event->event_id}";

        Log::withContext([
            'correlation_id' => $correlationId,
            'event_id' => $event->event_id,
            'consumer_group' => $consumerGroup,
            'event_name' => $event->event_name,
        ]);

        return $next($event, $consumerGroup);
    }
}
