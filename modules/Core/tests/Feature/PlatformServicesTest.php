<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Modules\Core\Application\Services\ActivityLogger;
use Modules\Core\Application\Services\NotificationService;
use Modules\Core\Domain\Models\ActivityLog;
use Modules\Core\Domain\Models\PlatformNotification;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->customer = User::create([
        'name' => 'Test Customer',
        'email' => 'testcust@test.com',
        'phone' => '081111111111',
        'role' => 'customer',
        'password' => Hash::make('password'),
        'email_verified_at' => now(),
    ]);
    // Ensure wallet exists for layout rendering
    $this->customer->walletAccount('IDR');
});

// ============================================================
// NOTIFICATION SERVICE
// ============================================================

test('can send a notification', function () {
    $service = app(NotificationService::class);

    $notif = $service->send(
        userId: $this->customer->id,
        type: 'test_event',
        title: 'Test Title',
        body: 'Test notification body',
        icon: 'success',
        actionUrl: '/test-url',
        actionLabel: 'Go',
        meta: ['key' => 'value'],
    );

    expect($notif)->toBeInstanceOf(PlatformNotification::class)
        ->and($notif->user_id)->toBe($this->customer->id)
        ->and($notif->type)->toBe('test_event')
        ->and($notif->title)->toBe('Test Title')
        ->and($notif->icon)->toBe('success')
        ->and($notif->action_url)->toBe('/test-url')
        ->and($notif->meta)->toBe(['key' => 'value'])
        ->and($notif->read_at)->toBeNull()
        ->and($notif->uuid)->not->toBeNull();
});

test('unread count is correct', function () {
    $service = app(NotificationService::class);

    $service->send($this->customer->id, 'a', 'T1', 'B1');
    $service->send($this->customer->id, 'b', 'T2', 'B2');
    $service->send($this->customer->id, 'c', 'T3', 'B3');

    expect($service->unreadCount($this->customer->id))->toBe(3);

    // Mark one as read
    $notif = PlatformNotification::forUser($this->customer->id)->first();
    $notif->markAsRead();

    expect($service->unreadCount($this->customer->id))->toBe(2);
});

test('mark all read clears unread count', function () {
    $service = app(NotificationService::class);

    $service->send($this->customer->id, 'a', 'T1', 'B1');
    $service->send($this->customer->id, 'b', 'T2', 'B2');

    $marked = $service->markAllRead($this->customer->id);

    expect($marked)->toBe(2)
        ->and($service->unreadCount($this->customer->id))->toBe(0);
});

test('recent returns latest notifications', function () {
    $service = app(NotificationService::class);

    for ($i = 1; $i <= 12; $i++) {
        $service->send($this->customer->id, 'test', "Title {$i}", "Body {$i}");
    }

    $recent = $service->recent($this->customer->id, 5);

    expect($recent)->toHaveCount(5);

    // All 12 exist in DB
    expect(PlatformNotification::forUser($this->customer->id)->count())->toBe(12);
});

// ============================================================
// ACTIVITY LOGGER
// ============================================================

test('can log an activity', function () {
    $logger = app(ActivityLogger::class);

    $log = $logger->log(
        module: 'autoserve',
        event: 'booking_created',
        description: 'Test booking created',
        userId: $this->customer->id,
        properties: ['booking_id' => 1],
    );

    expect($log)->toBeInstanceOf(ActivityLog::class)
        ->and($log->module)->toBe('autoserve')
        ->and($log->event)->toBe('booking_created')
        ->and($log->user_id)->toBe($this->customer->id)
        ->and($log->properties)->toBe(['booking_id' => 1])
        ->and($log->uuid)->not->toBeNull();
});

test('recent activities are returned for user', function () {
    $logger = app(ActivityLogger::class);

    $logger->log('autoserve', 'e1', 'Event 1', $this->customer->id);
    $logger->log('banking', 'e2', 'Event 2', $this->customer->id);
    $logger->log('store', 'e3', 'Event 3', null); // System event

    $userActivities = $logger->recentForUser($this->customer->id, 10);
    expect($userActivities)->toHaveCount(2);

    $allActivities = $logger->recentAll(10);
    expect($allActivities)->toHaveCount(3);
});

test('recent activities for module', function () {
    $logger = app(ActivityLogger::class);

    $logger->log('autoserve', 'e1', 'AutoServe 1', $this->customer->id);
    $logger->log('autoserve', 'e2', 'AutoServe 2', $this->customer->id);
    $logger->log('banking', 'e3', 'Banking 1', $this->customer->id);

    $autoserve = $logger->recentForModule('autoserve', 10);
    expect($autoserve)->toHaveCount(2);

    $banking = $logger->recentForModule('banking', 10);
    expect($banking)->toHaveCount(1);
});

// ============================================================
// NOTIFICATION ROUTES
// ============================================================

test('notifications index page renders', function () {
    $service = app(NotificationService::class);
    $service->send($this->customer->id, 'test', 'Test', 'Body');

    $response = $this->actingAs($this->customer)->get(route('notifications.index'));

    $response->assertStatus(200)
        ->assertSee('Test')
        ->assertSee('Body');
});

test('notifications recent JSON endpoint', function () {
    $service = app(NotificationService::class);
    $service->send($this->customer->id, 'test', 'JSON Title', 'JSON Body');

    $response = $this->actingAs($this->customer)->getJson(route('notifications.recent'));

    $response->assertStatus(200)
        ->assertJsonStructure(['unread_count', 'notifications'])
        ->assertJsonFragment(['title' => 'JSON Title']);
});

test('mark notification read redirects to action_url', function () {
    $service = app(NotificationService::class);
    $notif = $service->send($this->customer->id, 'test', 'T', 'B', actionUrl: route('dashboard'));

    $response = $this->actingAs($this->customer)->post(route('notifications.read', $notif));

    $response->assertRedirect(route('dashboard'));

    $notif->refresh();
    expect($notif->read_at)->not->toBeNull();
});

test('mark all read via JSON', function () {
    $service = app(NotificationService::class);
    $service->send($this->customer->id, 'a', 'T1', 'B1');
    $service->send($this->customer->id, 'b', 'T2', 'B2');

    $response = $this->actingAs($this->customer)
        ->postJson(route('notifications.markAllReadJson'));

    $response->assertStatus(200)
        ->assertJson(['marked' => 2]);

    expect($service->unreadCount($this->customer->id))->toBe(0);
});

test('cannot read another users notification', function () {
    $other = User::create([
        'name' => 'Other',
        'email' => 'other@test.com',
        'phone' => '082222222222',
        'role' => 'customer',
        'password' => Hash::make('password'),
        'email_verified_at' => now(),
    ]);
    $other->walletAccount('IDR');

    $service = app(NotificationService::class);
    $notif = $service->send($other->id, 'test', 'T', 'B');

    $response = $this->actingAs($this->customer)->post(route('notifications.read', $notif));

    $response->assertStatus(403);
});

// ============================================================
// ACTIVITY FEED ROUTE
// ============================================================

test('activity feed page renders', function () {
    $logger = app(ActivityLogger::class);
    $logger->log('autoserve', 'test', 'Activity feed test', $this->customer->id);

    $response = $this->actingAs($this->customer)->get(route('activity.index'));

    $response->assertStatus(200)
        ->assertSee('Activity feed test');
});

// ============================================================
// DASHBOARDS
// ============================================================

test('customer dashboard renders', function () {
    $response = $this->actingAs($this->customer)->get(route('dashboard'));

    $response->assertStatus(200)
        ->assertSee('Booking Saya');
});

test('admin dashboard renders', function () {
    $admin = User::create([
        'name' => 'Admin',
        'email' => 'admin-test@test.com',
        'phone' => '083333333333',
        'role' => 'admin',
        'password' => Hash::make('password'),
        'email_verified_at' => now(),
    ]);
    $admin->walletAccount('IDR');

    $response = $this->actingAs($admin)->get(route('dashboard'));

    $response->assertStatus(200)
        ->assertSee('Booking Terbaru');
});

test('mekanik dashboard renders', function () {
    $mekanik = User::create([
        'name' => 'Mekanik',
        'email' => 'mek-test@test.com',
        'phone' => '084444444444',
        'role' => 'mekanik',
        'password' => Hash::make('password'),
        'email_verified_at' => now(),
    ]);
    $mekanik->walletAccount('IDR');

    $response = $this->actingAs($mekanik)->get(route('dashboard'));

    $response->assertStatus(200)
        ->assertSee('Antrian Pending');
});
