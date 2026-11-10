<?php

declare(strict_types=1);

namespace Modules\Core\Application\Pipeline\Middleware;

use Closure;
use Modules\Core\Application\Pipeline\SpineMiddlewareInterface;
use Modules\Core\Contracts\AuditTrailInterface;
use Modules\Core\Domain\Models\SimEventSpine;

/**
 * Middleware Pencatatan Jejak Audit
 *
 * Merekam jejak audit penerimaan dan penyelesaian eksekusi event
 * ke dalam repositori audit permanen aplikasi.
 */
class AuditTrailLoggingMiddleware implements SpineMiddlewareInterface
{
    public function __construct(
        protected ?AuditTrailInterface $auditTrail = null
    ) {}

    public function handle(SimEventSpine $event, string $consumerGroup, Closure $next): mixed
    {
        $result = $next($event, $consumerGroup);

        if ($this->auditTrail) {
            $this->auditTrail->record(
                entityType: 'sim_event_spine',
                entityId: $event->event_id,
                action: "consume:{$consumerGroup}",
                payload: [
                    'topic' => $event->topic,
                    'event_name' => $event->event_name,
                    'version' => $event->version,
                ]
            );
        }

        return $result;
    }
}
