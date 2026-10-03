<?php

declare(strict_types=1);

namespace Modules\Core\Application\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Modules\Core\Contracts\OutboxBusInterface;
use Modules\Core\Domain\Models\OutboxDispatch;
use Modules\Core\Domain\Models\OutboxMessage;
use Modules\Core\Domain\Models\OutboxSubscription;
use Throwable;

class OutboxBusService implements OutboxBusInterface
{
    /**
     * Record a domain event into the transactional outbox. Idempotent by idempotency_key.
     */
    public function record(
        string $eventType,
        array $payload,
        ?string $idempotencyKey = null,
        ?Model $aggregate = null,
        ?array $headers = null
    ): OutboxMessage {
        $key = $idempotencyKey ?? (string) Str::uuid();

        return OutboxMessage::firstOrCreate(
            ['idempotency_key' => $key],
            [
                'event_type' => $eventType,
                'aggregate_type' => $aggregate ? get_class($aggregate) : null,
                'aggregate_id' => $aggregate ? (string) $aggregate->getKey() : null,
                'payload' => $payload,
                'headers' => $headers ?? [],
                'status' => 'pending',
                'attempts' => 0,
            ]
        );
    }

    /**
     * Dispatch all pending outbox entries.
     */
    public function dispatchPending(int $limit = 50): int
    {
        $messages = OutboxMessage::where('status', 'pending')
            ->orderBy('id', 'asc')
            ->limit($limit)
            ->get();

        $dispatchedCount = 0;
        foreach ($messages as $message) {
            $this->processMessage($message);
            $dispatchedCount++;
        }

        return $dispatchedCount;
    }

    /**
     * Retry failed or due outbox entries.
     */
    public function retryDue(int $limit = 50): int
    {
        $messages = OutboxMessage::where('status', 'failed')
            ->whereNotNull('next_retry_at')
            ->where('next_retry_at', '<=', now())
            ->orderBy('id', 'asc')
            ->limit($limit)
            ->get();

        $retriedCount = 0;
        foreach ($messages as $message) {
            $this->processMessage($message);
            $retriedCount++;
        }

        return $retriedCount;
    }

    /**
     * Replay a specific dead-letter or failed outbox entry.
     */
    public function replay(int|object $outbox): bool
    {
        /** @var OutboxMessage|null $message */
        $message = $outbox instanceof OutboxMessage ? $outbox : OutboxMessage::find($outbox);
        if (! $message) {
            return false;
        }

        $message->update([
            'status' => 'pending',
            'attempts' => 0,
            'last_error' => null,
            'next_retry_at' => null,
        ]);

        $this->processMessage($message);

        return true;
    }

    /**
     * Process an individual outbox message against active subscriptions.
     */
    public function processMessage(OutboxMessage $message): void
    {
        $message->increment('attempts');

        $subscriptions = OutboxSubscription::where('is_active', true)->get()
            ->filter(function (OutboxSubscription $sub) use ($message) {
                $events = $sub->events ?? [];

                return in_array('*', $events, true) || in_array($message->event_type, $events, true);
            });

        if ($subscriptions->isEmpty()) {
            // No listeners configured — message is considered dispatched
            $message->update([
                'status' => 'dispatched',
                'dispatched_at' => now(),
            ]);

            return;
        }

        $allSuccessful = true;
        $lastError = null;

        foreach ($subscriptions as $subscription) {
            $dispatch = OutboxDispatch::create([
                'outbox_id' => $message->id,
                'subscription_id' => $subscription->id,
                'status' => 'pending',
                'attempt' => $message->attempts,
            ]);

            try {
                $success = $this->deliverToSubscription($message, $subscription, $dispatch);
                if (! $success) {
                    $allSuccessful = false;
                }
            } catch (Throwable $e) {
                $allSuccessful = false;
                $lastError = mb_substr($e->getMessage(), 0, 1000);
                $dispatch->update([
                    'status' => 'failed',
                    'response_body' => $lastError,
                ]);
            }
        }

        if ($allSuccessful) {
            $message->update([
                'status' => 'dispatched',
                'dispatched_at' => now(),
                'last_error' => null,
                'next_retry_at' => null,
            ]);
        } else {
            $isDead = $message->isDeadLetter();
            $message->update([
                'status' => $isDead ? 'dead_letter' : 'failed',
                'last_error' => $lastError ?? 'One or more subscriptions failed to receive event',
                'next_retry_at' => $isDead ? null : now()->addSeconds($message->calculateNextRetryDelay()),
            ]);
        }
    }

    /**
     * Deliver to target (webhook, listener, etc.).
     */
    protected function deliverToSubscription(
        OutboxMessage $message,
        OutboxSubscription $subscription,
        OutboxDispatch $dispatch
    ): bool {
        if ($subscription->target_type === 'webhook') {
            $payloadJson = json_encode([
                'event_type' => $message->event_type,
                'payload' => $message->payload,
                'aggregate_type' => $message->aggregate_type,
                'aggregate_id' => $message->aggregate_id,
                'idempotency_key' => $message->idempotency_key,
                'timestamp' => now()->toIso8601String(),
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            $signature = $subscription->secret ? hash_hmac('sha256', $payloadJson, $subscription->secret) : '';

            $headers = array_merge($message->headers ?? [], [
                'Content-Type' => 'application/json',
                'X-Event-Type' => $message->event_type,
                'X-Idempotency-Key' => $message->idempotency_key,
            ]);

            if ($signature !== '') {
                $headers['X-Signature-SHA256'] = $signature;
                $headers['X-Webhook-Signature'] = $signature;
            }

            $response = Http::timeout($subscription->timeout_seconds ?: 10)
                ->withHeaders($headers)
                ->withBody($payloadJson, 'application/json')
                ->post($subscription->target);

            $isSuccess = $response->successful();

            $dispatch->update([
                'status' => $isSuccess ? 'success' : 'failed',
                'response_code' => $response->status(),
                'response_body' => mb_substr($response->body(), 0, 2000),
                'dispatched_at' => now(),
            ]);

            return $isSuccess;
        }

        // For other types, mark success
        $dispatch->update([
            'status' => 'success',
            'dispatched_at' => now(),
        ]);

        return true;
    }
}
