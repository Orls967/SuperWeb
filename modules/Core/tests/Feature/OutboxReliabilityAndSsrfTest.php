<?php

declare(strict_types=1);

namespace Modules\Core\tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Modules\Core\Application\Services\OutboxBusService;
use Modules\Core\Domain\Models\OutboxDispatch;
use Modules\Core\Domain\Models\OutboxSubscription;
use Tests\TestCase;

class OutboxReliabilityAndSsrfTest extends TestCase
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

    public function test_subscriber_a_sukses_dan_subscriber_b_gagal_pada_dispatch_pertama_maka_retry_hanya_mengirim_ulang_ke_subscriber_b(): void
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
            payload: ['order_id' => 888, 'total' => 250000],
            idempotencyKey: 'outbox_retry_test_888'
        );

        // 1. Dispatch pertama: Sub A sukses (200), Sub B gagal (503)
        $this->bus->dispatchPending();

        $message->refresh();
        $this->assertSame('failed', $message->status);
        $this->assertSame(1, $message->attempts);
        $this->assertSame(1, $callsA, 'Subscriber A harus dipanggil 1 kali pada percobaan pertama');
        $this->assertSame(1, $callsB, 'Subscriber B harus dipanggil 1 kali pada percobaan pertama');

        $dispatchA = OutboxDispatch::where('outbox_id', $message->id)->where('subscription_id', $subA->id)->first();
        $dispatchB = OutboxDispatch::where('outbox_id', $message->id)->where('subscription_id', $subB->id)->first();
        $this->assertSame('success', $dispatchA->status);
        $this->assertSame('failed', $dispatchB->status);

        // 2. Retry: Subscriber B kini pulih
        $subBShouldSucceed = true;
        $message->update(['next_retry_at' => now()->subMinute()]);

        $this->bus->retryDue();

        $message->refresh();
        $this->assertSame('dispatched', $message->status, 'Setelah seluruh subscriber sukses status wajib dispatched');
        $this->assertSame(1, $callsA, 'Subscriber A TIDAK boleh dipanggil ulang karena sudah sukses');
        $this->assertSame(2, $callsB, 'Subscriber B wajib dipanggil ulang pada retry');

        $successDispatchesB = OutboxDispatch::where('outbox_id', $message->id)
            ->where('subscription_id', $subB->id)
            ->where('status', 'success')
            ->count();
        $this->assertSame(1, $successDispatchesB);
    }

    public function test_dua_worker_outbox_paralel_mengklaim_pesan_secara_atomik_tanpa_duplikasi_pengiriman(): void
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

        $message = $this->bus->record(
            eventType: 'payment.received',
            payload: ['payment_id' => 999],
            idempotencyKey: 'outbox_atomic_test_999'
        );

        // Simulasi 2 worker yang berjalan paralel
        // Worker 1 mengklaim dan memproses:
        $worker1Dispatched = $this->bus->dispatchPending();

        // Worker 2 mencoba mengklaim pesan yang sama secara konkuren
        $worker2Dispatched = $this->bus->dispatchPending();

        $this->assertSame(1, $worker1Dispatched);
        $this->assertSame(0, $worker2Dispatched, 'Worker kedua tidak boleh memproses pesan yang sudah diklaim');
        $this->assertSame(1, $dispatchCount, 'Pesan hanya boleh dikirim tepat satu kali');
    }

    public function test_url_webhook_privat_localhost_dan_cloud_metadata_169_254_169_254_ditolak_oleh_guard_anti_ssrf(): void
    {
        // 1. Validasi method isSafeWebhookUrl
        $this->assertFalse(OutboxBusService::isSafeWebhookUrl('http://169.254.169.254/latest/meta-data/'));
        $this->assertFalse(OutboxBusService::isSafeWebhookUrl('https://169.254.169.254/'));
        $this->assertFalse(OutboxBusService::isSafeWebhookUrl('http://127.0.0.1:8000/internal-api'));
        $this->assertFalse(OutboxBusService::isSafeWebhookUrl('http://localhost/admin'));
        $this->assertFalse(OutboxBusService::isSafeWebhookUrl('http://10.0.0.1/api'));
        $this->assertFalse(OutboxBusService::isSafeWebhookUrl('http://192.168.1.1/status'));
        $this->assertFalse(OutboxBusService::isSafeWebhookUrl('ftp://example.com/file'));
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
        $this->assertStringContainsString('Anti-SSRF', $message->last_error);

        $dispatch = OutboxDispatch::where('outbox_id', $message->id)->first();
        $this->assertSame('failed', $dispatch->status);
        $this->assertStringContainsString('Anti-SSRF', $dispatch->response_body);
    }

    public function test_target_subscriber_non_webhook_dieksekusi_lewat_handler_terdaftar_dan_gagal_jika_handler_tidak_ditemukan(): void
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

        // 1. Tanpa handler terdaftar: dispatch harus gagal (bukan ditandai sukses palsu!)
        $this->bus->dispatchPending();

        $message->refresh();
        $this->assertSame('failed', $message->status);
        $this->assertStringContainsString('tidak terdaftar', $message->last_error);

        $dispatch = OutboxDispatch::where('outbox_id', $message->id)->first();
        $this->assertSame('failed', $dispatch->status);

        // 2. Daftarkan handler nyata
        $executed = false;
        OutboxBusService::registerHandler('kafka', function ($msg, $sub, $disp) use (&$executed) {
            $executed = true;

            return true;
        });

        // Replay
        $success = $this->bus->replay($message);
        $this->assertTrue($success);
        $this->assertTrue($executed, 'Handler kafka terdaftar wajib dieksekusi');

        $message->refresh();
        $this->assertSame('dispatched', $message->status);

        $latestDispatch = OutboxDispatch::where('outbox_id', $message->id)
            ->orderByDesc('id')
            ->first();
        $this->assertSame('success', $latestDispatch->status);
    }
}
