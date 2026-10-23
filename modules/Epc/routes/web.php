<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Epc\Http\Controllers\EpcController;
use Modules\Epc\Http\Controllers\EpcOperationsController;

Route::middleware(['web', 'auth', 'role:admin,epc_manager,asset_manager'])->prefix('epc')->name('epc.')->group(function () {
    Route::get('/', [EpcController::class, 'index'])->name('index');

    Route::get('/dashboard', [EpcOperationsController::class, 'dashboard'])->name('dashboard');

    Route::get('/claims', [EpcOperationsController::class, 'claimsIndex'])->name('claims.index');
    Route::post('/claims/record', [EpcOperationsController::class, 'recordClaims'])->name('claims.record');

    Route::get('/bast', [EpcOperationsController::class, 'bastIndex'])->name('bast.index');
    Route::post('/bast/issue', [EpcOperationsController::class, 'issueBast'])->name('bast.issue');

    Route::get('/capitalization', [EpcOperationsController::class, 'capitalizationIndex'])->name('capitalization.index');
    Route::post('/capitalization/capitalize', [EpcOperationsController::class, 'capitalize'])->name('capitalization.capitalize');
});
