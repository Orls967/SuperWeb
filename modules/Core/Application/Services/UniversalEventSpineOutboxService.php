<?php

declare(strict_types=1);

namespace Modules\Core\Application\Services;

use Modules\Core\Domain\Models\SimEventSpine;

class UniversalEventSpineOutboxService
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function publishEvent(
        string $topic,
        string $eventName,
        array $payload,
        string $idempotencyKey
    ): SimEventSpine {
        $existing = SimEventSpine::query()->where('idempotency_key', $idempotencyKey)->first();
        if ($existing) {
            return $existing;
        }

        $eventId = 'evt_'.hash('sha256', $idempotencyKey.':'.$eventName);

        return SimEventSpine::query()->create([
            'event_id' => $eventId,
            'topic' => $topic,
            'event_name' => $eventName,
            'version' => 1,
            'payload' => $payload,
            'idempotency_key' => $idempotencyKey,
            'occurred_at' => now(),
        ]);
    }

    /**
     * @return array{status: string, event_name: string, idempotency_key: string, result?: mixed}
     */
    public function consumeEvent(int $eventId, string $consumerGroup, callable $handler): array
    {
        $event = SimEventSpine::query()->findOrFail($eventId);

        // Check offset / processed state via consumer offset tracking
        $offsetKey = "processed:{$consumerGroup}:{$event->id}";
        $isAlreadyProcessed = cache()->has($offsetKey);

        if ($isAlreadyProcessed) {
            return [
                'status' => 'ALREADY_PROCESSED',
                'event_name' => $event->event_name,
                'idempotency_key' => $event->idempotency_key,
            ];
        }

        $result = $handler($event->payload);
        cache()->put($offsetKey, true, now()->addDays(7));

        return [
            'status' => 'PROCESSED',
            'event_name' => $event->event_name,
            'idempotency_key' => $event->idempotency_key,
            'result' => $result,
        ];
    }
}
