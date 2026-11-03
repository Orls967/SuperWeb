<?php

declare(strict_types=1);

namespace Tests\Perilaku\R2;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Modules\Core\Application\Services\OutboxBusService;
use Modules\Core\Domain\Models\OutboxDispatch;
use Modules\Core\Domain\Models\OutboxSubscription;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('perilaku')]
class R2_5Test extends TestCase
{
    use RefreshDatabase;

    private OutboxBusService $bus;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bus = app(OutboxBusService::class);
        OutboxBusService::resetHandlers();
    }

    protected function tearDown(): void
    {
        OutboxBusService::resetHandlers();
        parent::tearDown();
    }

    public function test_klausul_1_subscriber_a_sukses_dan_b_gagal_maka_retry_hanya_ke_b(): void
    {
        $callsA = 0;
        $callsB = 0;
        $subBShouldSucceed = false;

        Http::fake([
            'https://partner-a.example.com/webhook' => function () use (&$callsA) {
                $callsA++;

                return Http::response(['status' => 'ok'], 200);
            },
            'https://partner-b.example.com/webhook' => function () use (&$callsB, &$subBShouldSucceed) {
                $callsB++;

                return $subBShouldSucceed
                    ? Http::response(['status' => 'recovered'], 200)
                    : Http::response('Server Down', 503);
            },
        ]);

        $subA = OutboxSubscription::create([
            'name' => 'Subscriber A (Reliable)',
            'target_type' => 'webhook',
            'target' => 'https://partner-a.example.com/webhook',
            'events' => ['order.placed'],
            'is_active' => true,
        ]);

        $subB = OutboxSubscription::create([
            'name' => 'Subscriber B (Flaky)',
            'target_type' => 'webhook',
            'target' => 'https://partner-b.example.com/webhook',
            'events' => ['order.placed'],
            'is_active' => true,
        ]);

        $message = $this->bus->record(
            eventType: 'order.placed',
            payload: ['order_id' => 888],
            idempotencyKey: 'outbox_r25_test_888'
        );

        $this->bus->dispatchPending();

        $this->assertSame(1, $callsA);
        $this->assertSame(1, $callsB);

        $dispatchA = OutboxDispatch::where('outbox_id', $message->id)->where('subscription_id', $subA->id)->first();
        $dispatchB = OutboxDispatch::where('outbox_id', $message->id)->where('subscription_id', $subB->id)->first();
        $this->assertSame('success', $dispatchA->status);
        $this->assertSame('failed', $dispatchB->status);

        // Putaran retry: Subscriber B kini pulih
        $subBShouldSucceed = true;
        $message->update(['next_retry_at' => now()->subMinute()]);

        $this->bus->retryDue();

        // Klausul 1 Terbukti: Subscriber A tidak dipanggil ulang (tetap 1), Subscriber B dipanggil ulang (jadi 2)
        $this->assertSame(1, $callsA, 'Klausul 1: Subscriber A yang sudah sukses tidak boleh di-retry ulang');
        $this->assertSame(2, $callsB, 'Klausul 1: Retry hanya dikirim ke Subscriber B yang gagal');
    }

    public function test_klausul_2_dua_worker_mengklaim_pesan_atomik_tanpa_duplikasi(): void
    {
        $dispatchCount = 0;

        Http::fake([
            'https://partner.example.com/webhook' => function () use (&$dispatchCount) {
                $dispatchCount++;

                return Http::response(['status' => 'ok'], 200);
            },
        ]);

        OutboxSubscription::create([
            'name' => 'Webhook Worker Test',
            'target_type' => 'webhook',
            'target' => 'https://partner.example.com/webhook',
            'events' => ['payment.received'],
            'is_active' => true,
        ]);

        $this->bus->record(
            eventType: 'payment.received',
            payload: ['payment_id' => 999],
            idempotencyKey: 'outbox_atomic_test_999'
        );

        // Worker 1 mengklaim dan memproses:
        $worker1Dispatched = $this->bus->dispatchPending();

        // Worker 2 mencoba mengklaim pesan yang sama:
        $worker2Dispatched = $this->bus->dispatchPending();

        // Klausul 2 Terbukti: Satu pesan hanya diklaim dan dikirim tepat satu kali
        $this->assertSame(1, $worker1Dispatched);
        $this->assertSame(0, $worker2Dispatched, 'Klausul 2: Worker kedua tidak boleh memproses pesan yang sudah diklaim');
        $this->assertSame(1, $dispatchCount, 'Klausul 2: Pesan hanya boleh dikirim tepat satu kali');
    }

    public function test_klausul_3_target_non_webhook_tanpa_handler_gagal_bukan_sukses(): void
    {
        $subscription = OutboxSubscription::create([
            'name' => 'Internal Kafka Queue',
            'target_type' => 'kafka',
            'target' => 'topic.customer.registered',
            'events' => ['customer.registered'],
            'is_active' => true,
        ]);

        $message = $this->bus->record(
            eventType: 'customer.registered',
            payload: ['customer_id' => 1234],
            idempotencyKey: 'outbox_kafka_test'
        );

        $this->bus->dispatchPending();

        $message->refresh();
        $this->assertSame('failed', $message->status);

        $dispatch = OutboxDispatch::where('outbox_id', $message->id)->first();
        $this->assertNotNull($dispatch);
        $this->assertSame('failed', $dispatch->status, 'Klausul 3: Target non-webhook tanpa handler wajib gagal');
        $this->assertStringContainsString('tidak terdaftar', (string) $message->last_error);
    }

    public function test_klausul_4_url_ip_privat_dan_metadata_cloud_ditolak_anti_ssrf(): void
    {
        // 1. Validasi method isSafeWebhookUrl
        $this->assertFalse(OutboxBusService::isSafeWebhookUrl('http://169.254.169.254/latest/meta-data/'));
        $this->assertFalse(OutboxBusService::isSafeWebhookUrl('https://169.254.169.254/'));
        $this->assertFalse(OutboxBusService::isSafeWebhookUrl('http://127.0.0.1:8000/internal-api'));
        $this->assertFalse(OutboxBusService::isSafeWebhookUrl('http://localhost/admin'));
        $this->assertFalse(OutboxBusService::isSafeWebhookUrl('http://10.0.0.1/api'));
        $this->assertFalse(OutboxBusService::isSafeWebhookUrl('http://192.168.1.1/status'));
        $this->assertTrue(OutboxBusService::isSafeWebhookUrl('https://api.partner.example.com/webhook'));

        // 2. Dispatch pesan ke URL metadata berbahaya
        OutboxSubscription::create([
            'name' => 'Malicious Metadata Webhook',
            'target_type' => 'webhook',
            'target' => 'http://169.254.169.254/latest/meta-data',
            'events' => ['security.alert'],
            'is_active' => true,
        ]);

        $message = $this->bus->record(
            eventType: 'security.alert',
            payload: ['alert' => 'ssrf_attempt'],
            idempotencyKey: 'outbox_ssrf_test'
        );

        $this->bus->dispatchPending();

        $message->refresh();
        $this->assertSame('failed', $message->status);
        $this->assertStringContainsString('Anti-SSRF', (string) $message->last_error);

        $dispatch = OutboxDispatch::where('outbox_id', $message->id)->first();
        $this->assertNotNull($dispatch);
        $this->assertSame('failed', $dispatch->status);
        $this->assertStringContainsString('Anti-SSRF', (string) $dispatch->response_body);
    }
}
