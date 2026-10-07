<?php

declare(strict_types=1);

namespace Modules\Core\Application\Services;

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
     * Verify incoming webhook signature
     */
    public function verifyWebhookSignature(string $payloadJson, string $signature, string $secretKey): bool
    {
        $expected = hash_hmac('sha256', $payloadJson, $secretKey);

        return hash_equals($expected, $signature);
    }
}
