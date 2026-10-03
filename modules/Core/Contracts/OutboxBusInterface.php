<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

use Illuminate\Database\Eloquent\Model;

interface OutboxBusInterface
{
    /**
     * Record a domain event into the transactional outbox.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>|null  $headers
     */
    public function record(
        string $eventType,
        array $payload,
        ?string $idempotencyKey = null,
        ?Model $aggregate = null,
        ?array $headers = null
    ): object;

    /**
     * Dispatch all pending outbox entries.
     */
    public function dispatchPending(int $limit = 50): int;

    /**
     * Retry failed or due outbox entries.
     */
    public function retryDue(int $limit = 50): int;

    /**
     * Replay a specific dead-letter outbox entry.
     */
    public function replay(int|object $outbox): bool;
}
