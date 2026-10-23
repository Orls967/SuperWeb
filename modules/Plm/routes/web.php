<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Plm\Http\Controllers\PlmController;
use Modules\Plm\Http\Controllers\PlmOperationsController;

Route::middleware(['web', 'auth', 'role:admin,rnd_specialist'])->prefix('plm')->group(function () {
    Route::get('/', [PlmController::class, 'index'])->name('plm.index');

    Route::prefix('operations')->name('plm.operations.')->group(function () {
        Route::get('/', [PlmOperationsController::class, 'index'])->name('index');
        Route::post('/stage-gate/{project}', [PlmOperationsController::class, 'advanceStage'])->name('stage.advance');
        Route::post('/mbom/{ebom}', [PlmOperationsController::class, 'convertMbom'])->name('mbom.convert');
        Route::post('/eco/{ebom}', [PlmOperationsController::class, 'executeEco'])->name('eco.execute');
        Route::post('/lab-test/{project}', [PlmOperationsController::class, 'recordLabTest'])->name('lab.test');
    });
});
