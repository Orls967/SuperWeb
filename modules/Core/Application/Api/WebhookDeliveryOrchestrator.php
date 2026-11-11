<?php

declare(strict_types=1);

namespace Modules\Core\Application\Api;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Modules\Core\Domain\Algorithms\Api\WebhookSignatureVerifier;
use Modules\Core\Domain\Models\CoreWebhook;
use Modules\Core\Domain\Models\CoreWebhookDispatch;

class WebhookDeliveryOrchestrator
{
    public function __construct(
        private readonly WebhookSubscriptionService $subscriptionService,
        private readonly WebhookSignatureVerifier $signatureVerifier,
    ) {}

    /**
     * Dispatch an outbound event to a subscribed webhook.
     *
     * @param  array<string, mixed>  $payload
     */
    public function dispatch(
        CoreWebhook $webhook,
        string $eventType,
        string $outboundRef,
        array $payload,
        ?int $eventDeliveryId = null,
    ): CoreWebhookDispatch {
        $timestamp = time();
        $payloadJson = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $activeSecret = $this->subscriptionService->getActiveSecret($webhook);
        $prevSecret = $this->subscriptionService->getPreviousSecret($webhook);

        $additionalSecrets = $prevSecret !== null ? [$prevSecret] : [];
        $signatureHeader = $this->signatureVerifier->sign($payloadJson, $activeSecret, $timestamp, $additionalSecrets);

        $idempotencyKey = sprintf('wh_disp:%d:%s:%s', $webhook->id, $outboundRef, hash('sha256', $payloadJson));

        $dispatch = CoreWebhookDispatch::create([
            'tenant_id' => $webhook->tenant_id,
            'webhook_id' => $webhook->id,
            'event_delivery_id' => $eventDeliveryId,
            'outbound_event_ref' => $outboundRef,
            'event_type' => $eventType,
            'timestamp_unix' => $timestamp,
            'payload_json' => $payloadJson,
            'signature_header' => $signatureHeader,
            'http_status' => null,
            'response_excerpt' => null,
            'status' => 'pending',
            'attempt_count' => 1,
            'idempotency_key' => $idempotencyKey,
        ]);

        if (! $this->subscriptionService->canDispatch($webhook)) {
            $dispatch->update([
                'status' => 'circuit_broken',
                'response_excerpt' => 'Circuit breaker is open due to consecutive failures',
            ]);

            return $dispatch;
        }

        try {
            $response = Http::timeout(5)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-Signature' => $signatureHeader,
                    'X-Event-Type' => $eventType,
                    'X-Delivery-Id' => (string) $dispatch->id,
                ])
                ->post($webhook->target_url, $payload);

            $status = $response->status();
            $bodyExcerpt = Str::limit($response->body(), 255);

            if ($response->successful()) {
                $this->subscriptionService->recordSuccess($webhook);
                $dispatch->update([
                    'status' => 'delivered',
                    'http_status' => $status,
                    'response_excerpt' => $bodyExcerpt,
                ]);
            } else {
                $this->subscriptionService->recordFailure($webhook);
                $dispatch->update([
                    'status' => 'failed',
                    'http_status' => $status,
                    'response_excerpt' => $bodyExcerpt,
                ]);
                $this->recordDeliveryFailure($webhook, $eventType, $outboundRef, $payloadJson, 'HTTP '.$status);
            }
        } catch (\Throwable $e) {
            $this->subscriptionService->recordFailure($webhook);
            $dispatch->update([
                'status' => 'failed',
                'http_status' => 0,
                'response_excerpt' => Str::limit($e->getMessage(), 255),
            ]);
            $this->recordDeliveryFailure($webhook, $eventType, $outboundRef, $payloadJson, $e->getMessage());
        }

        return $dispatch;
    }

    private function recordDeliveryFailure(
        CoreWebhook $webhook,
        string $eventType,
        string $outboundRef,
        string $payloadJson,
        string $error
    ): void {
        try {
            DB::table('core_event_deliveries')->insert([
                'message_id' => $outboundRef,
                'consumer_group' => 'webhook:'.$webhook->id,
                'topic' => $eventType,
                'status' => 'dead_lettered',
                'attempts' => $webhook->consecutive_failures,
                'last_error' => Str::limit($error, 255),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable) {
        }
    }
}
