<?php

declare(strict_types=1);

namespace Modules\Logistics\Application\Actions;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Modules\Core\Contracts\OutboxBusInterface;
use Modules\Logistics\Domain\Models\WebhookDelivery;
use Modules\Logistics\Domain\Models\WebhookEndpoint;

/**
 * Dispatch webhook events to registered endpoints.
 * Uses HMAC-SHA256 signature and exponential backoff retries.
 */
class DispatchWebhookAction
{
    /**
     * Queue a webhook delivery for all matching endpoints.
     *
     * @param  array<string, mixed>  $payload
     * @return array<WebhookDelivery>
     */
    public function execute(string $eventType, array $payload): array
    {
        // Record into generic transactional outbox bus if available
        if (app()->bound(OutboxBusInterface::class)) {
            try {
                app(OutboxBusInterface::class)->record(
                    eventType: $eventType,
                    payload: $payload,
                    headers: ['source' => 'logistics']
                );
            } catch (\Throwable) {
                // Non-blocking fallback
            }
        }

        $endpoints = WebhookEndpoint::where('is_active', true)->get();
        $deliveries = [];

        foreach ($endpoints as $endpoint) {
            $subscribedEvents = $endpoint->events ?? [];
            if (! empty($subscribedEvents) && ! in_array($eventType, $subscribedEvents, true) && ! in_array('*', $subscribedEvents, true)) {
                continue;
            }

            $idempotencyKey = Str::uuid()->toString();

            $delivery = WebhookDelivery::create([
                'endpoint_id' => $endpoint->id,
                'event_type' => $eventType,
                'payload' => $payload,
                'idempotency_key' => $idempotencyKey,
                'status' => 'pending',
                'attempt' => 0,
            ]);

            $this->attemptDelivery($delivery, $endpoint);
            $deliveries[] = $delivery->fresh();
        }

        return $deliveries;
    }

    /**
     * Attempt to deliver a pending/retrying webhook.
     */
    public function attemptDelivery(WebhookDelivery $delivery, ?WebhookEndpoint $endpoint = null): void
    {
        $endpoint ??= $delivery->endpoint;
        if (! $endpoint) {
            return;
        }

        $delivery->increment('attempt');
        $payloadJson = json_encode($delivery->payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $signature = hash_hmac('sha256', $payloadJson, $endpoint->secret);

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'X-Webhook-Signature' => $signature,
                    'X-Webhook-Event' => $delivery->event_type,
                    'X-Idempotency-Key' => $delivery->idempotency_key,
                ])
                ->withBody($payloadJson, 'application/json')
                ->post($endpoint->url);

            $delivery->update([
                'response_code' => $response->status(),
                'response_body' => mb_substr($response->body(), 0, 2000),
                'status' => $response->successful() ? 'delivered' : 'failed',
                'delivered_at' => $response->successful() ? now() : null,
                'next_retry_at' => $response->successful() ? null : ($delivery->isDeadLetter() ? null : now()->addSeconds($delivery->calculateNextRetryDelay())),
            ]);

            if (! $response->successful() && $delivery->isDeadLetter()) {
                $delivery->update(['status' => 'dead_letter']);
            }
        } catch (\Throwable $e) {
            $delivery->update([
                'response_code' => null,
                'response_body' => mb_substr($e->getMessage(), 0, 2000),
                'status' => $delivery->isDeadLetter() ? 'dead_letter' : 'failed',
                'next_retry_at' => $delivery->isDeadLetter() ? null : now()->addSeconds($delivery->calculateNextRetryDelay()),
            ]);
        }
    }

    /**
     * Retry all failed deliveries that are due.
     */
    public function retryPending(): int
    {
        $deliveries = WebhookDelivery::where('status', 'failed')
            ->whereNotNull('next_retry_at')
            ->where('next_retry_at', '<=', now())
            ->get();

        foreach ($deliveries as $delivery) {
            $this->attemptDelivery($delivery);
        }

        return $deliveries->count();
    }

    /**
     * Manual replay of a dead-letter delivery.
     */
    public function replay(WebhookDelivery $delivery): void
    {
        $delivery->update([
            'attempt' => 0,
            'status' => 'pending',
            'next_retry_at' => null,
        ]);

        $this->attemptDelivery($delivery);
    }
}
