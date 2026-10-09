<?php

declare(strict_types=1);

namespace Modules\Shared\Application;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\AuditTrailInterface;
use Modules\Core\Contracts\OutboxBusInterface;

abstract class BaseAction
{
    /**
     * Run a callback inside a DB transaction with retry logic.
     */
    protected function transaction(callable $callback, int $attempts = 3): mixed
    {
        return DB::transaction($callback, $attempts);
    }

    /**
     * Record an audit log for impactful actions (financial, state, ownership, security).
     */
    protected function audit(
        string $action,
        ?object $auditable = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        array $context = [],
        ?string $correlationId = null,
        ?string $impactType = null,
        ?object $user = null
    ): mixed {
        if (app()->bound(AuditTrailInterface::class)) {
            return app(AuditTrailInterface::class)->record(
                action: $action,
                auditable: $auditable instanceof Model ? $auditable : null,
                oldValues: $oldValues,
                newValues: $newValues,
                context: $context,
                correlationId: $correlationId,
                impactType: $impactType,
                user: $user instanceof User ? $user : null
            );
        }

        return null;
    }

    /**
     * Record a domain event into the transactional outbox.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>|null  $headers
     */
    protected function outbox(
        string $eventType,
        array $payload,
        ?string $idempotencyKey = null,
        ?object $aggregate = null,
        ?array $headers = null
    ): mixed {
        if (app()->bound(OutboxBusInterface::class)) {
            return app(OutboxBusInterface::class)->record(
                eventType: $eventType,
                payload: $payload,
                idempotencyKey: $idempotencyKey,
                aggregate: $aggregate instanceof Model ? $aggregate : null,
                headers: $headers
            );
        }

        return null;
    }
}
