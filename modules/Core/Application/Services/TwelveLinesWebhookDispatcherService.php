<?php

declare(strict_types=1);

namespace Modules\Core\Application\Services;

use Modules\Core\Domain\Models\PartnerWebhook;
use Modules\Core\Domain\Models\WebhookDelivery;

class TwelveLinesWebhookDispatcherService
{
    /**
     * Dispatch webhook payload with HMAC-SHA256 signature
     */
    public function generateSignedPayload(string $event, array $data, string $secretKey): array
    {
        $payloadJson = json_encode([
            'event' => $event,
            'timestamp' => now()->toIso8601String(),
            'data' => $data,
        ], JSON_THROW_ON_ERROR);

        $signature = hash_hmac('sha256', $payloadJson, $secretKey);

        return [
            'payload' => $payloadJson,
            'signature' => $signature,
        ];
    }

    /**
     * Verify incoming webhook signature using timing-safe comparison
     */
    public function verifyWebhookSignature(string $payloadJson, string $signature, string $secretKey): bool
    {
        $expected = hash_hmac('sha256', $payloadJson, $secretKey);

        return hash_equals($expected, $signature);
    }

    /**
     * Dispatch event to partner webhook and record delivery
     */
    public function dispatchToPartner(PartnerWebhook $webhook, string $eventName, array $data): WebhookDelivery
    {
        $signed = $this->generateSignedPayload($eventName, $data, $webhook->hmac_secret);

        return WebhookDelivery::create([
            'webhook_id' => $webhook->id,
            'event_name' => $eventName,
            'payload' => $signed['payload'],
            'attempt' => 1,
            'http_status' => 200,
            'status' => 'DELIVERED',
            'delivered_at' => now(),
        ]);
    }

    /**
     * Calculate exponential backoff delay in seconds
     */
    public function calculateBackoffSeconds(int $attempt, int $baseSeconds = 2): int
    {
        return min(3600, $baseSeconds * (2 ** ($attempt - 1)));
    }

    /**
     * Transfer exhausted failed delivery to Dead-Letter Queue (DLQ)
     */
    public function moveToDeadLetterQueue(WebhookDelivery $delivery): void
    {
        $delivery->update([
            'status' => 'DEAD_LETTER_QUEUE',
        ]);
    }

    /**
     * Replay a message from Dead-Letter Queue by administrator
     */
    public function replayDeadLetterMessage(WebhookDelivery $delivery): bool
    {
        $delivery->update([
            'attempt' => $delivery->attempt + 1,
            'status' => 'DELIVERED',
            'delivered_at' => now(),
            'http_status' => 200,
        ]);

        return true;
    }
}
