<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Controllers\ActivityFeedController;
use Modules\Core\Http\Controllers\HealthCheckController;
use Modules\Core\Http\Controllers\NotificationController;
use Modules\Core\Http\Controllers\PassportController;
use Modules\Core\Http\Controllers\RbacController;

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

        // RBAC Management
        Route::get('/rbac', [RbacController::class, 'index'])->name('admin.rbac.index');
        Route::get('/rbac/role/{role}', [RbacController::class, 'showRole'])->name('admin.rbac.role');
        Route::post('/rbac/role/{role}/toggle-permission', [RbacController::class, 'togglePermission'])->name('admin.rbac.toggle-permission');
        Route::post('/rbac/assign', [RbacController::class, 'assignRole'])->name('admin.rbac.assign');
        Route::post('/rbac/revoke', [RbacController::class, 'revokeRole'])->name('admin.rbac.revoke');
        Route::get('/rbac/user/{user}', [RbacController::class, 'showUser'])->name('admin.rbac.user');
    });
});
