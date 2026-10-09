<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Modules\Core\Contracts\OutboxBusInterface;
use Modules\Core\Domain\Models\OutboxDispatch;
use Modules\Core\Domain\Models\OutboxMessage;
use Modules\Core\Domain\Models\OutboxSubscription;

uses(RefreshDatabase::class);

test('outbox bus records event idempotently', function () {
    $bus = app(OutboxBusInterface::class);

    $msg1 = $bus->record(
        eventType: 'order.placed',
        payload: ['order_id' => 101, 'amount' => 500000],
        idempotencyKey: 'outbox_test_101'
    );

    expect($msg1->id)->not->toBeNull()
        ->and($msg1->status)->toBe('pending')
        ->and($msg1->event_type)->toBe('order.placed')
        ->and($msg1->idempotency_key)->toBe('outbox_test_101');

    // Duplicate call with same idempotency key returns existing
    $msg2 = $bus->record(
        eventType: 'order.placed',
        payload: ['order_id' => 101, 'amount' => 500000],
        idempotencyKey: 'outbox_test_101'
    );

    expect($msg2->id)->toBe($msg1->id)
        ->and(OutboxMessage::count())->toBe(1);
});

test('outbox message dispatches to matching active subscriptions with HMAC signature', function () {
    Http::fake([
        'https://partner.example.com/webhook' => Http::response(['status' => 'ok'], 200),
    ]);

    OutboxSubscription::create([
        'name' => 'Partner Webhook',
        'target_type' => 'webhook',
        'target' => 'https://partner.example.com/webhook',
        'secret' => 'supersecret123',
        'events' => ['shipment.created', 'shipment.delivered'],
        'is_active' => true,
    ]);

    $bus = app(OutboxBusInterface::class);
    $msg = $bus->record(
        eventType: 'shipment.delivered',
        payload: ['tracking_number' => 'SRX999'],
        idempotencyKey: 'msg_ship_999'
    );

    $dispatched = $bus->dispatchPending();

    expect($dispatched)->toBe(1);

    $msg->refresh();
    expect($msg->status)->toBe('dispatched')
        ->and($msg->dispatched_at)->not->toBeNull();

    $dispatch = OutboxDispatch::where('outbox_id', $msg->id)->first();
    expect($dispatch)->not->toBeNull()
        ->and($dispatch->status)->toBe('success')
        ->and($dispatch->response_code)->toBe(200);

    Http::assertSent(function ($request) {
        return $request->url() === 'https://partner.example.com/webhook'
            && $request->hasHeader('X-Webhook-Signature')
            && $request->header('X-Event-Type')[0] === 'shipment.delivered';
    });
});

test('outbox marks failure and dead letters after max retries with replay capability', function () {
    $shouldSucceed = false;

    Http::fake([
        'https://flaky.example.com/endpoint*' => function () use (&$shouldSucceed) {
            return $shouldSucceed
                ? Http::response(['status' => 'ok'], 200)
                : Http::response('Internal Server Error', 500);
        },
    ]);

    OutboxSubscription::create([
        'name' => 'Flaky Webhook',
        'target_type' => 'webhook',
        'target' => 'https://flaky.example.com/endpoint',
        'secret' => 'testsecret',
        'events' => ['*'],
        'is_active' => true,
    ]);

    $bus = app(OutboxBusInterface::class);
    $msg = $bus->record(
        eventType: 'invoice.generated',
        payload: ['invoice_number' => 'INV-001'],
        idempotencyKey: 'msg_inv_001'
    );

    // Initial dispatch fails
    $bus->dispatchPending();
    $msg->refresh();
    expect($msg->status)->toBe('failed')
        ->and($msg->attempts)->toBe(1)
        ->and($msg->next_retry_at)->not->toBeNull();

    // Fast-forward attempts to reach dead letter
    $msg->update([
        'attempts' => OutboxMessage::MAX_RETRIES - 1,
        'next_retry_at' => now()->subMinute(),
    ]);

    $bus->retryDue();
    $msg->refresh();
    expect($msg->status)->toBe('dead_letter')
        ->and($msg->attempts)->toBe(OutboxMessage::MAX_RETRIES);

    // Now server recovers, test replay
    $shouldSucceed = true;

    $replayed = $bus->replay($msg);
    expect($replayed)->toBeTrue();

    $msg->refresh();
    expect($msg->status)->toBe('dispatched');
});

test('process outbox console command executes cleanly', function () {
    $bus = app(OutboxBusInterface::class);
    $bus->record(
        eventType: 'user.registered',
        payload: ['email' => 'test@example.com'],
        idempotencyKey: 'user_reg_99'
    );

    $this->artisan('core:process-outbox', ['--limit' => 10])
        ->expectsOutputToContain('Dispatched 1 outbox event(s).')
        ->assertSuccessful();

    $msg = OutboxMessage::where('idempotency_key', 'user_reg_99')->first();
    expect($msg->status)->toBe('dispatched');
});
