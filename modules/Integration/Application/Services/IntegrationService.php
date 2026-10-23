<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Modules\Core\Application\Services\OutboxBusService;
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

        if (! OutboxBusService::isSafeWebhookUrl($sub->target_url)) {
            return WebhookDelivery::create([
                'subscription_id' => $sub->id,
                'event_id' => $eventId,
                'payload' => $payloadJson,
                'signature' => $signature,
                'http_status' => 400,
                'delivery_status' => 'failed_ssrf_blocked',
                'attempt_count' => 1,
            ]);
        }

        $httpStatus = 500;
        $deliveryStatus = 'failed';

        try {
            $response = Http::timeout(2)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-Event-ID' => $eventId,
                    'X-Signature-SHA256' => $signature,
                ])
                ->withBody($payloadJson, 'application/json')
                ->post($sub->target_url);

            $httpStatus = $response->status();
            $deliveryStatus = $response->successful() ? 'delivered' : 'failed';
        } catch (\Throwable) {
            $httpStatus = 500;
            $deliveryStatus = 'failed';
        }

        return WebhookDelivery::create([
            'subscription_id' => $sub->id,
            'event_id' => $eventId,
            'payload' => $payloadJson,
            'signature' => $signature,
            'http_status' => $httpStatus,
            'delivery_status' => $deliveryStatus,
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
        $std = strtoupper(trim($standard));
        $tx = trim($txSet);
        $raw = trim($rawMessage);

        $isValid = false;

        if (! empty($raw)) {
            if ($std === 'X12') {
                // ANSI X12 requires transaction set header ST*{txSet} and trailer SE with delimiters
                $hasSegmentDelimiter = str_contains($raw, '~') || str_contains($raw, "\n");
                $hasElementDelimiter = str_contains($raw, '*');
                $hasSt = preg_match('/ST\*'.preg_quote($tx, '/').'\*/', $raw) === 1;
                $hasSe = str_contains($raw, 'SE*') || str_contains($raw, '~SE') || str_contains($raw, "\nSE");

                $isValid = $hasSegmentDelimiter && $hasElementDelimiter && $hasSt && $hasSe;
            } elseif ($std === 'EDIFACT') {
                $hasUnh = str_contains($raw, 'UNH+') || str_contains($raw, 'UNH:');
                $hasUnt = str_contains($raw, 'UNT+') || str_contains($raw, 'UNT:');
                $isValid = $hasUnh && $hasUnt;
            }
        }

        $status = $isValid ? 'accepted' : 'rejected';

        return EdiMessage::create([
            'control_number' => $controlNumber,
            'edi_standard' => $std,
            'transaction_set' => $tx,
            'sender_id' => $senderId,
            'receiver_id' => $receiverId,
            'raw_message' => $rawMessage,
            'functional_status' => $status,
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
        $invalidRecords = [];
        $sampleDeliveries = WebhookDelivery::with('subscription')
            ->orderBy('id', 'desc')
            ->limit(50)
            ->get();
        foreach ($sampleDeliveries as $del) {
            if ($del->subscription) {
                $expected = hash_hmac('sha256', $del->payload, $del->subscription->secret_key);
                if (! hash_equals($expected, $del->signature)) {
                    $invalidDeliveries++;
                    $invalidRecords[] = "WebhookDelivery ID {$del->id} (event: {$del->event_id})";
                }
            }
        }

        return [
            'status' => ($invalidDeliveries === 0) ? 'OK' : 'DISCREPANCY',
            'discrepancy_count' => $invalidDeliveries,
            'invalid_records' => $invalidRecords,
            'webhook_count' => $webhooks,
            'delivery_count' => $deliveries,
            'edi_message_count' => $ediMessages,
            'client_count' => $clients,
        ];
    }
}
