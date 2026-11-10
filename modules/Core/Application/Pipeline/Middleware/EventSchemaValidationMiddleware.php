<?php

declare(strict_types=1);

namespace Modules\Core\Application\Pipeline\Middleware;

use Closure;
use Modules\Core\Application\Pipeline\SpineMiddlewareInterface;
use Modules\Core\Application\Services\EventSchemaRegistry;
use Modules\Core\Domain\Models\SimEventSpine;

/**
 * Middleware Validasi Skema Event Dinamis
 *
 * Memverifikasi kesesuaian payload event terhadap skema aktif yang terdaftar
 * di Schema Registry sebelum pesan diteruskan ke handler konsumen.
 */
class EventSchemaValidationMiddleware implements SpineMiddlewareInterface
{
    public function __construct(
        protected EventSchemaRegistry $schemaRegistry
    ) {}

    public function handle(SimEventSpine $event, string $consumerGroup, Closure $next): mixed
    {
        $payload = (array) $event->payload;
        $version = (int) ($event->version ?? 1);

        // Validasi payload terhadap skema aktif
        $this->schemaRegistry->validate($event->event_name, $version, $payload);

        return $next($event, $consumerGroup);
    }
}
