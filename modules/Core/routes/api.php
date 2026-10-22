<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Controllers\Api\V3\EnterpriseExecutiveController;
use Modules\Core\Http\Controllers\Api\V3\MobileSyncController;
use Modules\Core\Http\Controllers\Api\V3\PartnerWebhookController;

Route::prefix('api/v3')->group(function () {
    // Executive analytics & KPI endpoints
    Route::get('/executive/kpis', [EnterpriseExecutiveController::class, 'kpis'])->name('api.v3.executive.kpis');
    Route::get('/executive/snapshot', [EnterpriseExecutiveController::class, 'snapshot'])->name('api.v3.executive.snapshot');

    // Partner webhook ingestion & DLQ replay
    Route::get('/partner/webhooks', [PartnerWebhookController::class, 'index'])->name('api.v3.partner.webhooks.index');
    Route::post('/partner/webhooks', [PartnerWebhookController::class, 'store'])->name('api.v3.partner.webhooks.store');
    Route::post('/partner/webhooks/{id}/replay', [PartnerWebhookController::class, 'replay'])->name('api.v3.partner.webhooks.replay');

    // Mobile offline-first delta synchronization
    Route::post('/mobile/sync/pull', [MobileSyncController::class, 'pull'])->name('api.v3.mobile.sync.pull');
    Route::post('/mobile/sync/push', [MobileSyncController::class, 'push'])->name('api.v3.mobile.sync.push');
    Route::get('/mobile/sync/conflicts', [MobileSyncController::class, 'conflicts'])->name('api.v3.mobile.sync.conflicts');
});
