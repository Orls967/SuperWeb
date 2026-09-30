<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Controllers\ActivityFeedController;
use Modules\Core\Http\Controllers\HealthCheckController;
use Modules\Core\Http\Controllers\NotificationController;
use Modules\Core\Http\Controllers\PassportController;

Route::middleware(['web'])->group(function () {
    Route::get('/passport/{uuid}', [PassportController::class, 'show'])->name('passport.show');
});

Route::middleware(['web', 'auth', 'verified'])->group(function () {
    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/recent', [NotificationController::class, 'recent'])->name('notifications.recent');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');
    Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllRead'])->name('notifications.markAllRead');
    Route::post('/notifications/mark-all-read-json', [NotificationController::class, 'markAllReadJson'])->name('notifications.markAllReadJson');

    // Activity Feed
    Route::get('/activity', [ActivityFeedController::class, 'index'])->name('activity.index');

    // Admin System Health & Observability
    Route::middleware('role:admin')->prefix('admin')->group(function () {
        Route::get('/health', [HealthCheckController::class, 'index'])->name('admin.health.index');
        Route::post('/health/run', [HealthCheckController::class, 'run'])->name('admin.health.run');
    });
});
