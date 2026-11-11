<?php

declare(strict_types=1);

namespace App\Quality\Audit\Fixtures;

use App\Quality\Audit\CorruptionFixture;
use Illuminate\Support\Facades\Crypt;
use Modules\Core\Domain\Models\CoreApiClient;
use Modules\Core\Domain\Models\CoreEdiInterchange;
use Modules\Core\Domain\Models\CoreTenant;
use Modules\Core\Domain\Models\CoreWebhook;
use Modules\Core\Domain\Models\CoreWebhookDispatch;
use Modules\Integration\Domain\Models\WebhookDelivery;
use Modules\Integration\Domain\Models\WebhookSubscription;

final class ApiAuditCorruptionFixture implements CorruptionFixture
{
    public function command(): string
    {
        return 'api:audit';
    }

    public function corrupt(): string
    {
        return $this->corruptScenario('legacy_webhook');
    }

    /**
     * @return array<string, string>
     */
    public function allScenarios(): array
    {
        return [
            'legacy_webhook' => 'WebhookDelivery ID',
            'core_webhook' => 'Corrupted CoreWebhookDispatch ID',
            'core_edi' => 'Corrupted CoreEdiInterchange ID',
            'core_client' => 'Corrupted CoreApiClient ID',
        ];
    }

    public function corruptScenario(string $scenario): string
    {
        return match ($scenario) {
            'legacy_webhook' => $this->corruptLegacyWebhook(),
            'core_webhook' => $this->corruptCoreWebhook(),
            'core_edi' => $this->corruptCoreEdi(),
            'core_client' => $this->corruptCoreClient(),
            default => $this->corruptLegacyWebhook(),
        };
    }

    private function corruptLegacyWebhook(): string
    {
        $sub = WebhookSubscription::first() ?? WebhookSubscription::create([
            'partner_id' => 'PARTNER-CORRUPT',
            'event_type' => 'test.event',
            'target_url' => 'https://example.com/webhook',
            'secret_key' => 'correct-secret-key-1234567890123456',
            'is_active' => true,
        ]);

        WebhookDelivery::create([
            'subscription_id' => $sub->id,
            'event_id' => 'EVT-CORRUPT',
            'payload' => json_encode(['data' => 'corrupted']),
            'signature' => 'invalid-hmac-signature-that-fails-verification',
            'http_status' => 200,
            'delivery_status' => 'delivered',
            'attempt_count' => 1,
        ]);

        return 'WebhookDelivery ID';
    }

    public function corruptCoreWebhook(): string
    {
        $tenant = CoreTenant::firstOrCreate(
            ['id' => 'tenant-corrupt'],
            ['code' => 'CORRUPT', 'name' => 'Corrupt Tenant', 'is_active' => true]
        );

        $wh = CoreWebhook::create([
            'tenant_id' => $tenant->id,
            'code' => 'WH-CORRUPT-'.uniqid(),
            'name' => 'Corrupted Webhook',
            'target_url' => 'https://example.com/corrupt',
            'event_topics' => ['corrupt.event'],
            'secret_encrypted' => Crypt::encryptString('whsec_correct_secret_key_1234567890'),
            'is_active' => true,
        ]);

        $dispatch = CoreWebhookDispatch::create([
            'tenant_id' => $tenant->id,
            'webhook_id' => $wh->id,
            'outbound_event_ref' => 'EVT-CORRUPT-'.uniqid(),
            'event_type' => 'corrupt.event',
            'timestamp_unix' => time(),
            'payload_json' => json_encode(['corrupt' => true]),
            'signature_header' => 't='.time().',v1=badbadbadbadbadbadbadbadbadbadbadbadbadbadbadbadbadbadbadbadbadb',
            'http_status' => 200,
            'status' => 'delivered',
            'attempt_count' => 1,
            'idempotency_key' => 'idemp-wh-corrupt-'.uniqid(),
        ]);

        return "Corrupted CoreWebhookDispatch ID {$dispatch->id}";
    }

    public function corruptCoreEdi(): string
    {
        $tenant = CoreTenant::firstOrCreate(
            ['id' => 'tenant-corrupt'],
            ['code' => 'CORRUPT', 'name' => 'Corrupt Tenant', 'is_active' => true]
        );

        $interchange = CoreEdiInterchange::create([
            'tenant_id' => $tenant->id,
            'control_ref' => 'CTRL-CORRUPT',
            'standard' => 'EDIFACT',
            'message_type' => 'ORDERS',
            'sender_ref' => 'CORRUPT',
            'receiver_ref' => 'SUPERWEB',
            'document_number' => 'DOC-CORRUPT',
            'status' => 'posted',
            'raw_payload' => 'UNB+UNOA:1...',
            'contrl_payload' => '',
            'handler_module' => 'Distribution',
            'target_reference' => '',
            'idempotency_key' => 'idemp-edi-corrupt-'.uniqid(),
        ]);

        return "Corrupted CoreEdiInterchange ID {$interchange->id}";
    }

    public function corruptCoreClient(): string
    {
        $tenant = CoreTenant::firstOrCreate(
            ['id' => 'tenant-corrupt'],
            ['code' => 'CORRUPT', 'name' => 'Corrupt Tenant', 'is_active' => true]
        );

        $client = CoreApiClient::create([
            'tenant_id' => $tenant->id,
            'code' => 'CLI-NO-KEYS-'.uniqid(),
            'name' => 'Client without active keys',
            'legal_entity_name' => 'PT No Keys',
            'is_active' => true,
        ]);

        return "Corrupted CoreApiClient ID {$client->id}";
    }
}
