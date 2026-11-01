<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Med\Http\Controllers\MedAdCampaignController;
use Modules\Med\Http\Controllers\MediaMvpController;
use Modules\Med\Http\Controllers\MedIpLicensingController;
use Modules\Med\Http\Controllers\MedStudioProductionController;

// Media MVP Routes (fine-grained role guards)
Route::middleware(['web', 'auth', 'role:admin,auditor,media_admin,rights_manager,studio_manager'])->prefix('media')->group(function () {
    Route::get('/', [MediaMvpController::class, 'dashboard'])->name('med.mvp.dashboard');
});

Route::middleware(['web', 'auth', 'role:admin,media_admin,studio_manager'])->prefix('media')->group(function () {
    Route::get('/studios', [MediaMvpController::class, 'studios'])->name('med.mvp.studios');
});

Route::middleware(['web', 'auth', 'role:admin,media_admin,rights_manager'])->prefix('media')->group(function () {
    Route::get('/licensing', [MediaMvpController::class, 'licensing'])->name('med.mvp.licensing');
    Route::get('/royalties/{id}', [MediaMvpController::class, 'royaltyStatementDetail'])->name('med.mvp.royalties.detail');
});

// Legacy media routes (preserves exact baseline route names and roles)
Route::middleware(['web', 'auth', 'role:admin,ip_manager,producer,studio_ops,talent_mgmt'])->prefix('media/production')->group(function () {
    Route::get('/studios-legacy', [MedStudioProductionController::class, 'index'])->name('med.studios.index');
    Route::get('/overview', [MedStudioProductionController::class, 'index'])->name('media.index');
    Route::get('/studios/{id}', [MedStudioProductionController::class, 'show'])->name('med.studios.show');
    Route::post('/projects/{id}/greenlight', [MedStudioProductionController::class, 'greenlight'])->name('med.studios.greenlight');

    Route::get('/licensing-legacy', [MedIpLicensingController::class, 'index'])->name('med.licensing.index');
    Route::get('/licensing/{id}', [MedIpLicensingController::class, 'show'])->name('med.licensing.show');
    Route::post('/licensing/grant', [MedIpLicensingController::class, 'license'])->name('med.licensing.grant');

    Route::get('/royalties', [MedIpLicensingController::class, 'royaltiesIndex'])->name('med.royalties.index');
    Route::post('/royalties/projects/{id}/distribute', [MedIpLicensingController::class, 'distributeBoxOffice'])->name('med.royalties.distribute');
    Route::post('/royalties/talent/{contractId}/settle', [MedIpLicensingController::class, 'settleTalent'])->name('med.royalties.talent.settle');

    Route::get('/ads', [MedAdCampaignController::class, 'index'])->name('med.ads.index');
    Route::post('/ads/book', [MedAdCampaignController::class, 'book'])->name('med.ads.book');
});
