<?php

declare(strict_types=1);

namespace Tests\Keamanan;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Modules\Logistics\Application\Actions\DispatchWebhookAction;
use Modules\Logistics\Domain\Models\WebhookDelivery;
use Modules\Logistics\Domain\Models\WebhookEndpoint;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('keamanan')]
class SsrfProtectionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * S-07: SSRF protection must reject webhook delivery to private IPs / AWS metadata service.
     */
    public function test_logistics_webhook_delivery_blocks_private_ip_and_metadata_service(): void
    {
        Http::fake();

        $endpoint = WebhookEndpoint::create([
            'name' => 'Internal Metadata Target',
            'url' => 'http://169.254.169.254/latest/meta-data/',
            'secret' => 'supersecret',
            'events' => ['shipment.created'],
            'is_active' => true,
        ]);

        $delivery = WebhookDelivery::create([
            'endpoint_id' => $endpoint->id,
            'event_type' => 'shipment.created',
            'payload' => ['id' => '123'],
            'idempotency_key' => 'logistics:webhook:test',
            'status' => 'pending',
            'attempt' => 0,
        ]);

        $action = new DispatchWebhookAction;
        $action->attemptDelivery($delivery, $endpoint);

        // Http client must NOT have sent any request to the metadata IP
        Http::assertNothingSent();
    }
}
