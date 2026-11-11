<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Controllers\Api\V3\EnterpriseExecutiveController;
use Modules\Core\Http\Controllers\Api\V3\MobileSyncController;
use Modules\Core\Http\Controllers\Api\V3\PartnerWebhookController;
use Modules\Core\Http\Controllers\ApiV2BulkController;
use Modules\Core\Http\Controllers\ApiV2EdiController;
use Modules\Core\Http\Controllers\ApiV2ResourceController;
use Modules\Core\Http\Controllers\ApiV2WebhookController;
use Modules\Core\Http\Middleware\ApiV2GatewayMiddleware;

Route::prefix('api/v2')->middleware([ApiV2GatewayMiddleware::class])->group(function () {
    Route::get('/webhooks', [ApiV2WebhookController::class, 'index'])->name('api.v2.webhooks.index');
    Route::post('/webhooks', [ApiV2WebhookController::class, 'store'])->name('api.v2.webhooks.store');
    Route::post('/webhooks/{id}/rotate-secret', [ApiV2WebhookController::class, 'rotateSecret'])->name('api.v2.webhooks.rotate-secret');

    Route::post('/edi/interchange', [ApiV2EdiController::class, 'interchange'])->name('api.v2.edi.interchange');

    Route::post('/bulk/import', [ApiV2BulkController::class, 'import'])->name('api.v2.bulk.import');
    Route::post('/bulk/export', [ApiV2BulkController::class, 'export'])->name('api.v2.bulk.export');

    Route::get('/{resource}', [ApiV2ResourceController::class, 'index'])->name('api.v2.resource.index');
    Route::get('/{resource}/{id}', [ApiV2ResourceController::class, 'show'])->name('api.v2.resource.show');
    Route::post('/{resource}/actions/{action}', [ApiV2ResourceController::class, 'action'])->name('api.v2.resource.action');
});

Route::get('api/v2/bulk/export/{job}/download', [ApiV2BulkController::class, 'download'])->name('api.v2.bulk.export.download');

Route::prefix('api/v3')->group(function () {
    // Executive analytics & KPI endpoints
    Route::get('/executive/kpis', [EnterpriseExecutiveController::class, 'kpis'])->name('api.v3.executive.kpis');
    Route::get('/executive/snapshot', [EnterpriseExecutiveController::class, 'snapshot'])->name('api.v3.executive.snapshot');

    // Partner webhook ingestion & DLQ replay
    Route::get('/partner/webhooks', [PartnerWebhookController::class, 'index'])->name('api.v3.partner.webhooks.index');
    Route::post('/partner/webhooks', [PartnerWebhookController::class, 'store'])->name('api.v3.partner.webhooks.store');
    Route::post('/partner/webhooks/{id}/replay', [PartnerWebhookController::class, 'replay'])
        ->middleware('role:admin')
        ->name('api.v3.partner.webhooks.replay');

    // Mobile offline-first delta synchronization
    Route::post('/mobile/sync/pull', [MobileSyncController::class, 'pull'])->name('api.v3.mobile.sync.pull');
    Route::post('/mobile/sync/push', [MobileSyncController::class, 'push'])->name('api.v3.mobile.sync.push');
    Route::get('/mobile/sync/conflicts', [MobileSyncController::class, 'conflicts'])->name('api.v3.mobile.sync.conflicts');
});
