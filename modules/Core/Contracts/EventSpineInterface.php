<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

use Illuminate\Support\Collection;
use Modules\Core\Domain\Models\SimEventSpine;

interface EventSpineInterface
{
    /**
     * Publish an event to the topic spine.
     * Enforces idempotency via idempotency_key.
     */
    public function publish(
        string $topic,
        string $eventName,
        array $payload,
        string $idempotencyKey,
        int $version = 1
    ): SimEventSpine;

    /**
     * Consume events from a given offset for a specific topic.
     *
     * @return Collection<int, SimEventSpine>
     */
    public function consume(string $topic, int $fromOffset = 0, int $limit = 100): Collection;

    /**
     * Commit consumer group offset for a topic.
     */
    public function commitOffset(string $consumerGroup, string $topic, int $offset): void;

    /**
     * Get consumer group offset.
     */
    public function getOffset(string $consumerGroup, string $topic): int;
}
