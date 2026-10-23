<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Med\Http\Controllers\MedAdCampaignController;
use Modules\Med\Http\Controllers\MedIpLicensingController;
use Modules\Med\Http\Controllers\MedStudioProductionController;

Route::middleware(['web', 'auth', 'role:admin,producer,studio_ops,ip_manager,talent_mgmt'])->prefix('media')->group(function () {
    Route::get('/', [MedStudioProductionController::class, 'index'])->name('med.studios.index');
    Route::get('/overview', [MedStudioProductionController::class, 'index'])->name('media.index');
    Route::get('/studios/{id}', [MedStudioProductionController::class, 'show'])->name('med.studios.show');
    Route::post('/projects/{id}/greenlight', [MedStudioProductionController::class, 'greenlight'])->name('med.studios.greenlight');

    Route::get('/licensing', [MedIpLicensingController::class, 'index'])->name('med.licensing.index');
    Route::get('/licensing/{id}', [MedIpLicensingController::class, 'show'])->name('med.licensing.show');
    Route::post('/licensing/grant', [MedIpLicensingController::class, 'license'])->name('med.licensing.grant');

    Route::get('/royalties', [MedIpLicensingController::class, 'royaltiesIndex'])->name('med.royalties.index');
    Route::post('/royalties/projects/{id}/distribute', [MedIpLicensingController::class, 'distributeBoxOffice'])->name('med.royalties.distribute');
    Route::post('/royalties/talent/{contractId}/settle', [MedIpLicensingController::class, 'settleTalent'])->name('med.royalties.talent.settle');

    Route::get('/ads', [MedAdCampaignController::class, 'index'])->name('med.ads.index');
    Route::post('/ads/book', [MedAdCampaignController::class, 'book'])->name('med.ads.book');
});
