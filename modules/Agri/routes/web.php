<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Agri\Http\Controllers\AgriController;
use Modules\Agri\Http\Controllers\AgriOperationsController;

Route::middleware(['web', 'auth', 'role:admin,farmer,procurement'])->prefix('agri')->name('agri.')->group(function () {
    Route::get('/', [AgriController::class, 'index'])->name('index');

    Route::get('/dashboard', [AgriOperationsController::class, 'dashboard'])->name('dashboard');

    Route::get('/contracts', [AgriOperationsController::class, 'contractsIndex'])->name('contracts.index');
    Route::post('/contracts/register', [AgriOperationsController::class, 'registerContract'])->name('contracts.register');

    Route::get('/grading', [AgriOperationsController::class, 'gradingIndex'])->name('grading.index');
    Route::post('/grading/evaluate', [AgriOperationsController::class, 'evaluateGrading'])->name('grading.evaluate');

    Route::get('/payout', [AgriOperationsController::class, 'payoutIndex'])->name('payout.index');
    Route::post('/payout/settle', [AgriOperationsController::class, 'settlePayout'])->name('payout.settle');
});
