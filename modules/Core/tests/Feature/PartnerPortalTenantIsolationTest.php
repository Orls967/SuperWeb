<?php

declare(strict_types=1);

namespace Modules\Core\tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Application\Services\TwelveLinesWebhookDispatcherService;
use Modules\Core\Domain\Models\PartnerWebhook;
use Modules\Core\Domain\Models\WebhookDelivery;
use Tests\TestCase;

class PartnerPortalTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private TwelveLinesWebhookDispatcherService $dispatcher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dispatcher = app(TwelveLinesWebhookDispatcherService::class);
    }

    public function test_partner_webhook_registration_and_tenant_isolation(): void
    {
        // 1. Create webhook for Tenant Alpha
        $responseAlpha = $this->withHeaders([
            'X-Tenant-ID' => 'tenant-alpha',
        ])->postJson('/api/v3/partner/webhooks', [
            'partner_name' => 'Alpha InsurTech',
            'endpoint_url' => 'https://alpha-insurtech.com/hook',
            'retry_limit' => 3,
        ]);

        $responseAlpha->assertStatus(201);
        $this->assertSame('tenant-alpha', $responseAlpha->json('data.tenant_id'));

        // 2. Create webhook for Tenant Beta
        $responseBeta = $this->withHeaders([
            'X-Tenant-ID' => 'tenant-beta',
        ])->postJson('/api/v3/partner/webhooks', [
            'partner_name' => 'Beta Logistics',
            'endpoint_url' => 'https://beta-logistics.com/hook',
            'retry_limit' => 5,
        ]);

        $responseBeta->assertStatus(201);

        // 3. Query as Tenant Alpha -> only Alpha's webhooks returned
        $listAlpha = $this->withHeaders([
            'X-Tenant-ID' => 'tenant-alpha',
        ])->getJson('/api/v3/partner/webhooks');

        $listAlpha->assertStatus(200);
        $listAlpha->assertJsonCount(1, 'data');
        $this->assertSame('Alpha InsurTech', $listAlpha->json('data.0.partner_name'));

        // 4. Query as Tenant Beta -> only Beta's webhooks returned
        $listBeta = $this->withHeaders([
            'X-Tenant-ID' => 'tenant-beta',
        ])->getJson('/api/v3/partner/webhooks');

        $listBeta->assertStatus(200);
        $listBeta->assertJsonCount(1, 'data');
        $this->assertSame('Beta Logistics', $listBeta->json('data.0.partner_name'));
    }

    public function test_dead_letter_queue_and_replay_action(): void
    {
        /** @var PartnerWebhook $webhook */
        $webhook = PartnerWebhook::create([
            'tenant_id' => 'tenant-gamma',
            'partner_name' => 'Gamma Telehealth',
            'endpoint_url' => 'https://gamma.org/events',
            'hmac_secret' => 'gamma_secret_key_123',
            'retry_limit' => 3,
            'timeout_seconds' => 15,
            'is_active' => true,
        ]);

        /** @var WebhookDelivery $delivery */
        $delivery = WebhookDelivery::create([
            'webhook_id' => $webhook->id,
            'event_name' => 'teleconsult.completed',
            'payload' => json_encode(['session_id' => 'ses-991']),
            'attempt' => 3,
            'http_status' => 503,
            'status' => 'PENDING',
        ]);

        // Move to DLQ
        $this->dispatcher->moveToDeadLetterQueue($delivery);
        $this->assertSame('DEAD_LETTER_QUEUE', $delivery->fresh()->status);

        // Administrator replays the DLQ delivery via API
        $admin = User::factory()->create(['role' => 'admin']);
        $replayResponse = $this->actingAs($admin)->postJson("/api/v3/partner/webhooks/{$delivery->id}/replay");

        $replayResponse->assertStatus(200);
        $replayResponse->assertJson([
            'status' => 'success',
            'data' => [
                'status' => 'DELIVERED',
                'attempt' => 4,
            ],
        ]);
    }

    public function test_exponential_backoff_calculation(): void
    {
        $this->assertSame(2, $this->dispatcher->calculateBackoffSeconds(1));
        $this->assertSame(4, $this->dispatcher->calculateBackoffSeconds(2));
        $this->assertSame(8, $this->dispatcher->calculateBackoffSeconds(3));
        $this->assertSame(16, $this->dispatcher->calculateBackoffSeconds(4));
        $this->assertSame(3600, $this->dispatcher->calculateBackoffSeconds(20)); // Capped at 3600s
    }
}
