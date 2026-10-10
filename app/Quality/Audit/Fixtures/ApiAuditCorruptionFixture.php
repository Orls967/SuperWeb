<?php

declare(strict_types=1);

namespace App\Quality\Audit\Fixtures;

use App\Quality\Audit\CorruptionFixture;
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

        return 'Signature or message discrepancy detected';
    }
}
