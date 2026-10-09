<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Str;
use Modules\Integration\Domain\Models\ApiClient;
use Modules\Integration\Domain\Models\EdiMessage;
use Modules\Integration\Domain\Models\WebhookDelivery;
use Modules\Integration\Domain\Models\WebhookSubscription;

class IntegrationService
{
    /**
     * 55.2 Mesin Webhook B2B & HMAC Signature
     */
    public function registerWebhook(
        string $partnerId,
        string $eventType,
        string $targetUrl,
        ?string $secretKey = null
    ): WebhookSubscription {
        return WebhookSubscription::create([
            'partner_id' => $partnerId,
            'event_type' => $eventType,
            'target_url' => $targetUrl,
            'secret_key' => $secretKey ?? Str::random(32),
            'is_active' => true,
        ]);
    }

    public function dispatchWebhook(WebhookSubscription $sub, string $eventId, array $payload): WebhookDelivery
    {
        $payloadJson = json_encode($payload);
        $signature = hash_hmac('sha256', $payloadJson, $sub->secret_key);

        return WebhookDelivery::create([
            'subscription_id' => $sub->id,
            'event_id' => $eventId,
            'payload' => $payloadJson,
            'signature' => $signature,
            'http_status' => 200,
            'delivery_status' => 'delivered',
            'attempt_count' => 1,
        ]);
    }

    public function verifyWebhookSignature(string $payloadJson, string $secretKey, string $signature): bool
    {
        $expected = hash_hmac('sha256', $payloadJson, $secretKey);

        return hash_equals($expected, $signature);
    }

    /**
     * 55.3 B2B Electronic Data Interchange (EDI 850/855/856/810)
     */
    public function processEdiMessage(
        string $standard,
        string $txSet,
        string $senderId,
        string $receiverId,
        string $rawMessage
    ): EdiMessage {
        $controlNumber = 'EDI-'.strtoupper(Str::random(10));

        return EdiMessage::create([
            'control_number' => $controlNumber,
            'edi_standard' => strtoupper($standard),
            'transaction_set' => $txSet,
            'sender_id' => $senderId,
            'receiver_id' => $receiverId,
            'raw_message' => $rawMessage,
            'functional_status' => 'accepted',
        ]);
    }

    /**
     * 55.5 Manajemen Klien API B2B & Tiered Quota
     */
    public function registerApiClient(
        string $clientName,
        string $tier = 'GOLD'
    ): array {
        $clientId = 'CLI-'.strtoupper(Str::random(8));
        $rawApiKey = 'sk_live_'.Str::random(40);
        $hash = hash('sha256', $rawApiKey);

        $rateLimit = match (strtoupper($tier)) {
            'PLATINUM' => 2000,
            'GOLD' => 600,
            default => 120, // SILVER
        };

        $client = ApiClient::create([
            'client_id' => $clientId,
            'client_name' => $clientName,
            'api_key_hash' => $hash,
            'tier' => strtoupper($tier),
            'rate_limit_per_minute' => $rateLimit,
            'is_active' => true,
        ]);

        return [
            'client' => $client,
            'raw_api_key' => $rawApiKey,
        ];
    }

    /**
     * 55.9 Audit Integrasi API & EDI
     */
    public function auditIntegration(): array
    {
        $webhooks = WebhookSubscription::count();
        $deliveries = WebhookDelivery::count();
        $ediMessages = EdiMessage::count();
        $clients = ApiClient::count();

        // Invariant: webhook delivery signature must match payload and secret
        $invalidDeliveries = 0;
        $sampleDeliveries = WebhookDelivery::with('subscription')->limit(50)->get();
        foreach ($sampleDeliveries as $del) {
            if ($del->subscription) {
                $expected = hash_hmac('sha256', $del->payload, $del->subscription->secret_key);
                if (! hash_equals($expected, $del->signature)) {
                    $invalidDeliveries++;
                }
            }
        }

        return [
            'status' => ($invalidDeliveries === 0) ? 'OK' : 'DISCREPANCY',
            'discrepancy_count' => $invalidDeliveries,
            'webhook_count' => $webhooks,
            'delivery_count' => $deliveries,
            'edi_message_count' => $ediMessages,
            'client_count' => $clients,
        ];
    }
}
