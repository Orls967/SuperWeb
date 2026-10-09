<?php

declare(strict_types=1);

namespace Modules\Integration\tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Integration\Application\Services\IntegrationService;
use Tests\TestCase;

class IntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected IntegrationService $integrationService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->integrationService = app(IntegrationService::class);
    }

    public function test_55_2_webhook_subscription_and_hmac_dispatch(): void
    {
        $sub = $this->integrationService->registerWebhook(
            partnerId: 'PTN-BOSCH-01',
            eventType: 'shipment.delivered',
            targetUrl: 'https://api.bosch.test/webhooks/shipping'
        );

        $this->assertDatabaseHas('intg_webhook_subscriptions', [
            'id' => $sub->id,
            'partner_id' => 'PTN-BOSCH-01',
            'event_type' => 'shipment.delivered',
            'is_active' => 1,
        ]);

        $payload = [
            'shipment_code' => 'SHP-99201',
            'status' => 'DELIVERED',
            'delivered_at' => '2026-10-06 12:00:00',
        ];

        $delivery = $this->integrationService->dispatchWebhook(
            sub: $sub,
            eventId: 'EVT-001',
            payload: $payload
        );

        $this->assertDatabaseHas('intg_webhook_deliveries', [
            'id' => $delivery->id,
            'subscription_id' => $sub->id,
            'delivery_status' => 'delivered',
            'http_status' => 200,
        ]);

        // Verify HMAC SHA256 signature
        $isValid = $this->integrationService->verifyWebhookSignature(
            payloadJson: $delivery->payload,
            secretKey: $sub->secret_key,
            signature: $delivery->signature
        );
        $this->assertTrue($isValid);
    }

    public function test_55_3_edi_message_processing(): void
    {
        $rawX12 = 'ISA*00*          *00*          *ZZ*SENDER         *ZZ*RECEIVER       *261006*1200*U*00401*000000001*0*P*>~GS*PO*SENDER*RECEIVER*20261006*1200*1*X*004010~ST*850*0001~BEG*00*SA*PO-99120**20261006~SE*4*0001~GE*1*1~IEA*1*000000001~';

        $edi = $this->integrationService->processEdiMessage(
            standard: 'X12',
            txSet: '850',
            senderId: 'SENDER_CORP',
            receiverId: 'RECEIVER_CORP',
            rawMessage: $rawX12
        );

        $this->assertDatabaseHas('intg_edi_messages', [
            'id' => $edi->id,
            'edi_standard' => 'X12',
            'transaction_set' => '850',
            'functional_status' => 'accepted',
        ]);
    }

    public function test_55_5_b2b_api_client_registration_and_tiered_quota(): void
    {
        // Platinum tier client
        $platinum = $this->integrationService->registerApiClient('Astra Component Division', 'PLATINUM');
        $this->assertEquals(2000, $platinum['client']->rate_limit_per_minute);
        $this->assertNotEmpty($platinum['raw_api_key']);

        // Gold tier client
        $gold = $this->integrationService->registerApiClient('Denso Indonesia', 'GOLD');
        $this->assertEquals(600, $gold['client']->rate_limit_per_minute);

        $this->assertDatabaseHas('intg_api_clients', [
            'client_name' => 'Astra Component Division',
            'tier' => 'PLATINUM',
            'rate_limit_per_minute' => 2000,
        ]);
    }

    public function test_55_9_integration_audit_and_command(): void
    {
        $sub = $this->integrationService->registerWebhook(
            partnerId: 'PTN-TEST-01',
            eventType: 'po.issued',
            targetUrl: 'https://test.vendor.com/hook'
        );

        $this->integrationService->dispatchWebhook($sub, 'EVT-999', ['test' => true]);

        $audit = $this->integrationService->auditIntegration();
        $this->assertEquals('OK', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);
        $this->assertGreaterThanOrEqual(1, $audit['delivery_count']);

        $this->artisan('api:audit')
            ->expectsOutputToContain('API & EDI Integration audit PASSED with 0 discrepancy.')
            ->assertExitCode(0);
    }

    public function test_55_10_http_endpoints(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('integration.index'))
            ->assertOk()
            ->assertSee('B2B Integrasi API v2');

        $this->actingAs($user)
            ->get(route('integration.webhooks'))
            ->assertOk()
            ->assertSee('Langganan Webhook B2B');
    }
}
