<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Contracts\OutboxBusInterface;
use Modules\Logistics\Application\Actions\DispatchWebhookAction;
use Modules\Logistics\Domain\Models\WebhookDelivery;
use Modules\Logistics\Domain\Models\WebhookEndpoint;
use Tests\TestCase;

uses(TestCase::class);

covers(DispatchWebhookAction::class);

beforeEach(function () {
    static $schemaCreated = false;
    if (! $schemaCreated) {
        if (! Schema::hasTable('lgx_webhook_endpoints')) {
            Schema::create('lgx_webhook_endpoints', function ($table) {
                $table->id();
                $table->string('url');
                $table->text('secret');
                $table->json('events')->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedBigInteger('shipper_id')->nullable();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('lgx_webhook_deliveries')) {
            Schema::create('lgx_webhook_deliveries', function ($table) {
                $table->id();
                $table->unsignedBigInteger('endpoint_id');
                $table->string('event_type');
                $table->json('payload');
                $table->string('idempotency_key')->unique();
                $table->integer('attempt')->default(0);
                $table->integer('response_code')->nullable();
                $table->text('response_body')->nullable();
                $table->string('status')->default('pending');
                $table->timestamp('next_retry_at')->nullable();
                $table->timestamp('delivered_at')->nullable();
                $table->timestamps();
            });
        }
        $schemaCreated = true;
    }

    WebhookDelivery::query()->delete();
    WebhookEndpoint::query()->delete();
});

it('queues webhook delivery for active matching endpoints and ignores non matching', function () {
    $matching = WebhookEndpoint::create([
        'url' => 'https://api.external-partner.com/webhook',
        'secret' => 'matching-secret',
        'events' => ['shipment.created', 'shipment.delivered'],
        'is_active' => true,
    ]);

    $wildcard = WebhookEndpoint::create([
        'url' => 'https://wildcard-partner.com/webhook',
        'secret' => 'wildcard-secret',
        'events' => ['*'],
        'is_active' => true,
    ]);

    $nonMatching = WebhookEndpoint::create([
        'url' => 'https://other-partner.com/webhook',
        'secret' => 'other-secret',
        'events' => ['order.placed'],
        'is_active' => true,
    ]);

    $inactive = WebhookEndpoint::create([
        'url' => 'https://inactive-partner.com/webhook',
        'secret' => 'inactive-secret',
        'events' => ['shipment.created'],
        'is_active' => false,
    ]);

    Http::fake([
        'https://api.external-partner.com/*' => Http::response(['status' => 'received'], 200),
        'https://wildcard-partner.com/*' => Http::response(['status' => 'received'], 200),
    ]);

    $action = app(DispatchWebhookAction::class);
    $deliveries = $action->execute('shipment.created', [
        'shipment_id' => 'SHP-001',
        'status' => 'in_transit',
    ]);

    expect($deliveries)->toHaveCount(2);

    $endpointIds = array_map(fn ($d) => $d->endpoint_id, $deliveries);
    expect($endpointIds)->toContain($matching->id);
    expect($endpointIds)->toContain($wildcard->id);
    expect($endpointIds)->not->toContain($nonMatching->id);
    expect($endpointIds)->not->toContain($inactive->id);
});

it('records event into OutboxBusInterface when container bound', function () {
    $recorded = false;
    $mockOutbox = Mockery::mock(OutboxBusInterface::class);
    $mockOutbox->shouldReceive('record')
        ->once()
        ->withArgs(function ($eventType, $payload, $idempotencyKey = null, $aggregate = null, $headers = null) use (&$recorded) {
            $recorded = ($eventType === 'shipment.created' && ($headers['source'] ?? '') === 'logistics');

            return true;
        })
        ->andReturn(new stdClass);

    app()->instance(OutboxBusInterface::class, $mockOutbox);

    $action = app(DispatchWebhookAction::class);
    $action->execute('shipment.created', ['shipment_id' => 'SHP-RECORD']);

    expect($recorded)->toBeTrue();
});

it('successfully delivers webhook and computes valid HMAC signature', function () {
    $endpoint = WebhookEndpoint::create([
        'url' => 'https://webhook.receiver.com/hook',
        'secret' => 'test-secret-key-123',
        'events' => ['*'],
        'is_active' => true,
    ]);

    $receivedHeaders = null;
    Http::fake([
        'https://webhook.receiver.com/*' => function ($request) use (&$receivedHeaders) {
            $receivedHeaders = $request->headers();

            return Http::response(['ok' => true], 200);
        },
    ]);

    $action = app(DispatchWebhookAction::class);
    $deliveries = $action->execute('shipment.updated', ['shipment_id' => 'SHP-999']);

    expect($deliveries)->toHaveCount(1);
    $delivery = $deliveries[0];

    expect($delivery->status)->toBe('delivered');
    expect($delivery->response_code)->toBe(200);
    expect($delivery->delivered_at)->not->toBeNull();
    expect($delivery->next_retry_at)->toBeNull();

    $expectedPayload = json_encode(['shipment_id' => 'SHP-999'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $expectedSignature = hash_hmac('sha256', $expectedPayload, 'test-secret-key-123');

    expect($receivedHeaders['X-Webhook-Signature'][0])->toBe($expectedSignature);
    expect($receivedHeaders['X-Webhook-Event'][0])->toBe('shipment.updated');
    expect($receivedHeaders['X-Idempotency-Key'][0])->toBe($delivery->idempotency_key);
});

it('blocks SSRF attempts to private IP and cloud metadata', function () {
    $endpoint = WebhookEndpoint::create([
        'url' => 'http://169.254.169.254/latest/meta-data/',
        'secret' => 'ssrf-secret',
        'events' => ['*'],
        'is_active' => true,
    ]);

    Http::fake();

    $action = app(DispatchWebhookAction::class);
    $deliveries = $action->execute('shipment.compromised', ['shipment_id' => 'SHP-SSRF']);

    expect($deliveries)->toHaveCount(1);
    $delivery = $deliveries[0];

    expect($delivery->status)->toBe('dead_letter');
    expect($delivery->response_code)->toBe(400);
    expect($delivery->response_body)->toContain('SSRF Blocked');
    Http::assertNothingSent();
});

it('handles http failures and calculates exponential backoff delay', function () {
    $endpoint = WebhookEndpoint::create([
        'url' => 'https://flaky-partner.com/webhook',
        'secret' => 'flaky-secret',
        'events' => ['*'],
        'is_active' => true,
    ]);

    Http::fake([
        'https://flaky-partner.com/*' => Http::response('Service Unavailable', 503),
    ]);

    $action = app(DispatchWebhookAction::class);
    $deliveries = $action->execute('shipment.failed_event', ['shipment_id' => 'SHP-FAIL']);

    expect($deliveries)->toHaveCount(1);
    $delivery = $deliveries[0];

    expect($delivery->status)->toBe('failed');
    expect($delivery->response_code)->toBe(503);
    expect($delivery->attempt)->toBe(1);
    expect($delivery->next_retry_at)->not->toBeNull();
    expect($delivery->calculateNextRetryDelay())->toBe(30);
});

it('marks delivery as dead letter when maximum retry attempts reached', function () {
    $endpoint = WebhookEndpoint::create([
        'url' => 'https://broken-partner.com/webhook',
        'secret' => 'broken-secret',
        'events' => ['*'],
        'is_active' => true,
    ]);

    $delivery = WebhookDelivery::create([
        'endpoint_id' => $endpoint->id,
        'event_type' => 'shipment.retry',
        'payload' => ['id' => 1],
        'idempotency_key' => 'key:1',
        'status' => 'failed',
        'attempt' => WebhookDelivery::MAX_RETRIES - 1, // 7
    ]);

    Http::fake([
        'https://broken-partner.com/*' => Http::response('Fatal Error', 500),
    ]);

    $action = app(DispatchWebhookAction::class);
    $action->attemptDelivery($delivery, $endpoint);

    $refreshed = $delivery->fresh();
    expect($refreshed->attempt)->toBe(WebhookDelivery::MAX_RETRIES);
    expect($refreshed->status)->toBe('dead_letter');
    expect($refreshed->next_retry_at)->toBeNull();
});

it('retries due pending deliveries and replays dead letter deliveries', function () {
    $endpoint = WebhookEndpoint::create([
        'url' => 'https://recover-partner.com/webhook',
        'secret' => 'recover-secret',
        'events' => ['*'],
        'is_active' => true,
    ]);

    $dueDelivery = WebhookDelivery::create([
        'endpoint_id' => $endpoint->id,
        'event_type' => 'shipment.due',
        'payload' => ['id' => 10],
        'idempotency_key' => 'key:due',
        'status' => 'failed',
        'attempt' => 1,
        'next_retry_at' => now()->subMinute(),
    ]);

    $futureDelivery = WebhookDelivery::create([
        'endpoint_id' => $endpoint->id,
        'event_type' => 'shipment.future',
        'payload' => ['id' => 11],
        'idempotency_key' => 'key:future',
        'status' => 'failed',
        'attempt' => 1,
        'next_retry_at' => now()->addMinutes(10),
    ]);

    Http::fake([
        'https://recover-partner.com/*' => Http::response(['status' => 'ok'], 200),
    ]);

    $action = app(DispatchWebhookAction::class);
    $retriedCount = $action->retryPending();

    expect($retriedCount)->toBe(1);
    expect($dueDelivery->fresh()->status)->toBe('delivered');
    expect($futureDelivery->fresh()->status)->toBe('failed');

    // Test replay
    $deadLetter = WebhookDelivery::create([
        'endpoint_id' => $endpoint->id,
        'event_type' => 'shipment.dead',
        'payload' => ['id' => 12],
        'idempotency_key' => 'key:dead',
        'status' => 'dead_letter',
        'attempt' => 8,
    ]);

    $action->replay($deadLetter);

    expect($deadLetter->fresh()->status)->toBe('delivered');
    expect($deadLetter->fresh()->attempt)->toBe(1);
});
